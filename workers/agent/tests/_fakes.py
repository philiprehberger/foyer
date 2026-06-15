"""Shared in-memory fakes for orchestrator tests.

Both ``test_structured_output``, ``test_cost_ceiling``, and
``test_scope_guard`` exercise :func:`app.run_turn` with these fakes
substituted for the real LLM provider + internal-API client so the
tests stay hermetic.
"""
from __future__ import annotations

from datetime import datetime, timezone
from typing import Iterable, Iterator

from contracts import (
    AgentTurnJob,
    BusinessConfig,
    EscalatePayload,
    TurnContext,
    TurnMessage,
    TurnResultPayload,
)
from llm_adapter import LLMRawResponse
from state_machine import Phase

ULID_A = "01HZ00000000000000000000AA"
ULID_B = "01HZ00000000000000000000BB"
ULID_C = "01HZ00000000000000000000CC"


def make_job(
    *,
    conversation_id: str = ULID_A,
    message_id: str = ULID_B,
    business_id: str = ULID_C,
    channel: str = "sms",
) -> AgentTurnJob:
    return AgentTurnJob.model_validate(
        {
            "schema_version": 1,
            "conversation_id": conversation_id,
            "message_id": message_id,
            "business_id": business_id,
            "channel": channel,
            "enqueued_at": datetime.now(tz=timezone.utc).isoformat(),
        }
    )


def make_business(
    *,
    cost_ceiling_micros: int = 500_000,
    cheap_mode_model: str = "claude-haiku",
) -> BusinessConfig:
    return BusinessConfig.model_validate(
        {
            "id": ULID_C,
            "name": "Anchor Plumbing",
            "timezone": "America/Denver",
            "persona": "professional",
            "system_prompt_suffix": "",
            "service_types": ["drain_clearing", "leak_repair"],
            "service_area_description": "Boulder, CO 80301-80304",
            "business_hours_description": "Mon-Fri 8am-5pm",
            "human_handoff_threshold": 0.5,
            "cost_ceiling_micros": cost_ceiling_micros,
            "cheap_mode_model": cheap_mode_model,
        }
    )


def make_context(
    *,
    current_phase: Phase = Phase.GREET,
    last_user_message: str = "hi can you clear my kitchen drain",
    cumulative_cost_micros_today: int = 0,
    business: BusinessConfig | None = None,
) -> TurnContext:
    return TurnContext(
        conversation_id=ULID_A,
        business=business or make_business(),
        current_phase=current_phase,
        messages=[
            TurnMessage(role="customer", text=last_user_message, phase=None),
        ],
        last_user_message=last_user_message,
        cumulative_cost_micros_today=cumulative_cost_micros_today,
    )


class FakeInternalClient:
    """Drop-in replacement for :class:`internal_api.InternalAPIClient`.

    Implements the ``async with`` shape + the three RPC methods the
    orchestrator uses. Records every call so tests can assert on them.
    """

    def __init__(self, context: TurnContext) -> None:
        self._context = context
        self.turn_results: list[TurnResultPayload] = []
        self.escalations: list[EscalatePayload] = []
        self.context_fetches: list[str] = []

    async def __aenter__(self) -> "FakeInternalClient":
        return self

    async def __aexit__(self, exc_type, exc, tb) -> None:
        return None

    async def fetch_turn_context(self, conversation_id: str) -> TurnContext:
        self.context_fetches.append(conversation_id)
        return self._context

    async def post_turn_result(
        self, conversation_id: str, payload: TurnResultPayload
    ) -> None:
        self.turn_results.append(payload)

    async def post_escalate(
        self, conversation_id: str, payload: EscalatePayload
    ) -> None:
        self.escalations.append(payload)


class ScriptedProvider:
    """LLM provider that returns canned responses from a list.

    Each call pops the next entry; tests use this to script
    parse-failure-then-recovery sequences without touching the mock
    provider's trigger-token logic.
    """

    name = "scripted"

    def __init__(self, responses: Iterable[LLMRawResponse]) -> None:
        self._iter: Iterator[LLMRawResponse] = iter(list(responses))
        self.calls: list[tuple[str, str, str]] = []

    async def call(
        self, *, model: str, system: str, user: str
    ) -> LLMRawResponse:
        self.calls.append((model, system, user))
        try:
            return next(self._iter)
        except StopIteration as e:
            raise AssertionError(
                "ScriptedProvider exhausted — too few canned responses"
            ) from e
