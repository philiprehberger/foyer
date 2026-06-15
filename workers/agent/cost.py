"""Per-business per-day cost tracking + cheap-mode / killswitch logic.

Each turn's ``cost_micros`` is added to an in-process counter keyed by
``business_id``. The ceiling check is performed BEFORE the LLM call (with
the previously-spent total) and AGAIN after the response lands (so a
single big turn can flip the state mid-day).

State machine for cost mode:

    NORMAL --(over ceiling)--> CHEAP --(still over after grace)--> KILLED

In ``CHEAP`` the worker swaps the configured default model for
``business.cheap_mode_model``. In ``KILLED`` the worker stops calling the
LLM at all and emits an :func:`Escalation` so the orchestrator routes the
turn to ``human_handoff``.

The counter is *in-process* — not Redis, not Postgres — because Laravel
already maintains the durable ``llm_cost_daily`` rollup. The worker-side
counter is for fast pre-flight checks, refreshed from the durable view
at startup and on every ``reload-config`` POST.
"""
from __future__ import annotations

from dataclasses import dataclass, field
from datetime import date, datetime, timezone
from enum import Enum
from threading import Lock


class CostMode(str, Enum):
    NORMAL = "normal"
    CHEAP = "cheap"
    KILLED = "killed"


@dataclass
class BusinessCostState:
    """Per-business cost snapshot.

    ``ceiling_micros`` is the hard cap from business config (or the env
    default if the business hasn't overridden). ``cheap_mode_model`` is
    pulled from the same config — when the mode flips to CHEAP we use
    that model for the next call; if cost continues to climb we transition
    to KILLED on the next pre-flight check.
    """

    business_id: str
    day: date
    spent_micros: int = 0
    ceiling_micros: int = 500_000
    cheap_mode_model: str = "claude-haiku"
    mode: CostMode = CostMode.NORMAL

    def reset_for_day(self, day: date) -> None:
        if day != self.day:
            self.day = day
            self.spent_micros = 0
            self.mode = CostMode.NORMAL


@dataclass
class CostDecision:
    """Result of a pre-flight or post-call ceiling check.

    The orchestrator consults ``mode`` to pick the model for the next call
    and ``should_escalate`` to decide whether to skip the call entirely
    and route the turn to ``human_handoff``.
    """

    mode: CostMode
    should_escalate: bool
    chosen_model: str
    spent_micros: int
    ceiling_micros: int


class CostTracker:
    """In-process cost ledger, thread-safe.

    Single instance held by the FastAPI app; tests construct their own.
    """

    def __init__(self) -> None:
        self._states: dict[str, BusinessCostState] = {}
        self._lock = Lock()

    def _today(self) -> date:
        return datetime.now(tz=timezone.utc).date()

    def _ensure(
        self,
        business_id: str,
        ceiling_micros: int,
        cheap_mode_model: str,
    ) -> BusinessCostState:
        """Get-or-create the per-business state, rolling at midnight UTC."""
        today = self._today()
        state = self._states.get(business_id)
        if state is None:
            state = BusinessCostState(
                business_id=business_id,
                day=today,
                ceiling_micros=ceiling_micros,
                cheap_mode_model=cheap_mode_model,
            )
            self._states[business_id] = state
        state.reset_for_day(today)
        # Refresh from latest config — owner may have raised the ceiling.
        state.ceiling_micros = ceiling_micros
        state.cheap_mode_model = cheap_mode_model
        return state

    def preflight(
        self,
        *,
        business_id: str,
        default_model: str,
        ceiling_micros: int,
        cheap_mode_model: str,
        already_spent_micros: int | None = None,
    ) -> CostDecision:
        """Run the pre-call ceiling check.

        ``already_spent_micros`` lets the caller seed the counter from
        Laravel's durable rollup on the first call after a worker restart;
        ``None`` leaves the in-process counter alone.
        """
        with self._lock:
            state = self._ensure(business_id, ceiling_micros, cheap_mode_model)
            if already_spent_micros is not None:
                state.spent_micros = max(state.spent_micros, already_spent_micros)
            return self._decide(state, default_model)

    def record(
        self,
        *,
        business_id: str,
        cost_micros: int,
        default_model: str,
        ceiling_micros: int,
        cheap_mode_model: str,
    ) -> CostDecision:
        """Add ``cost_micros`` to the running total and re-evaluate.

        Returns the *next-call* decision — the mode after this turn
        completed. The orchestrator persists ``mode`` on the result so
        subsequent turns pick up the right model.
        """
        with self._lock:
            state = self._ensure(business_id, ceiling_micros, cheap_mode_model)
            state.spent_micros += max(0, cost_micros)
            return self._decide(state, default_model)

    def snapshot(self, business_id: str) -> BusinessCostState | None:
        with self._lock:
            state = self._states.get(business_id)
            if state is None:
                return None
            # Return a copy to avoid leaking the lock-protected reference.
            return BusinessCostState(
                business_id=state.business_id,
                day=state.day,
                spent_micros=state.spent_micros,
                ceiling_micros=state.ceiling_micros,
                cheap_mode_model=state.cheap_mode_model,
                mode=state.mode,
            )

    def _decide(
        self, state: BusinessCostState, default_model: str
    ) -> CostDecision:
        """Recompute the mode transition for ``state``.

        Two thresholds:

        - >= 100% of ceiling and currently NORMAL -> CHEAP
        - >= 150% of ceiling and currently CHEAP  -> KILLED

        The 1.5x grace band gives the cheap model a chance to bring the
        average back under without immediately killing the conversation.
        """
        ceiling = max(1, state.ceiling_micros)
        ratio = state.spent_micros / ceiling

        if state.mode == CostMode.NORMAL and ratio >= 1.0:
            state.mode = CostMode.CHEAP
        if state.mode == CostMode.CHEAP and ratio >= 1.5:
            state.mode = CostMode.KILLED

        if state.mode == CostMode.KILLED:
            chosen = state.cheap_mode_model
            should_escalate = True
        elif state.mode == CostMode.CHEAP:
            chosen = state.cheap_mode_model
            should_escalate = False
        else:
            chosen = default_model
            should_escalate = False

        return CostDecision(
            mode=state.mode,
            should_escalate=should_escalate,
            chosen_model=chosen,
            spent_micros=state.spent_micros,
            ceiling_micros=state.ceiling_micros,
        )
