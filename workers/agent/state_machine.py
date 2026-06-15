"""Foyer conversation state machine.

The agent is a finite-state machine, not a free-running LLM. Every turn the
LLM proposes a `next_phase`; the worker rejects transitions that aren't
explicitly allowed from the current phase and re-prompts the model.

Encoded invariants (asserted in tests):

- ``completed`` is unreachable without passing through ``propose_slot``
  (and the human-confirm gate immediately after).
- ``abandon`` is reachable from any non-terminal phase.
- ``out_of_scope`` and ``human_handoff`` are terminal — once entered, the
  conversation stops, the orchestrator hands off, and no further LLM turns
  fire on that conversation.
- ``completed`` is also terminal.

The transition table is intentionally narrow; future phases (e.g. a
``collect_payment`` insert between ``request_human_confirm`` and
``confirm_to_customer``) require an explicit edit here and a test bump.
"""
from __future__ import annotations

from enum import Enum


class Phase(str, Enum):
    """Conversation phase enum.

    Inherits from ``str`` so the enum values serialise cleanly through
    pydantic and JSON-Schema. Order is documentation only — transition
    legality is governed by :data:`_ALLOWED`.
    """

    GREET = "greet"
    IDENTIFY_SERVICE = "identify_service"
    COLLECT_ADDRESS = "collect_address"
    COLLECT_TIMING = "collect_timing"
    COLLECT_DETAILS = "collect_details"
    PROPOSE_SLOT = "propose_slot"
    REQUEST_HUMAN_CONFIRM = "request_human_confirm"
    CONFIRM_TO_CUSTOMER = "confirm_to_customer"
    COMPLETED = "completed"
    ABANDON = "abandon"
    OUT_OF_SCOPE = "out_of_scope"
    HUMAN_HANDOFF = "human_handoff"


# Phases the conversation cannot leave once entered.
TERMINAL: frozenset[Phase] = frozenset(
    {Phase.COMPLETED, Phase.ABANDON, Phase.OUT_OF_SCOPE, Phase.HUMAN_HANDOFF}
)


# Phases from which the agent will still take a turn. Anything not in this
# set means the orchestrator should not be dispatching new turns at all.
ACTIVE: frozenset[Phase] = frozenset(p for p in Phase if p not in TERMINAL)


def _common_terminal_transitions() -> set[Phase]:
    """Every non-terminal phase can fall to one of these terminals.

    Held out as a helper so the happy-path edges stay readable in
    :data:`_ALLOWED`.
    """
    return {Phase.ABANDON, Phase.OUT_OF_SCOPE, Phase.HUMAN_HANDOFF}


# Forward transitions, allowing self-loops on the data-collection phases so
# the agent can keep asking clarifying questions in the same phase. The only
# path into COMPLETED is through PROPOSE_SLOT -> REQUEST_HUMAN_CONFIRM ->
# CONFIRM_TO_CUSTOMER -> COMPLETED; the Hypothesis test rides on this.
_ALLOWED: dict[Phase, frozenset[Phase]] = {
    Phase.GREET: frozenset(
        {Phase.GREET, Phase.IDENTIFY_SERVICE} | _common_terminal_transitions()
    ),
    Phase.IDENTIFY_SERVICE: frozenset(
        {Phase.IDENTIFY_SERVICE, Phase.COLLECT_ADDRESS}
        | _common_terminal_transitions()
    ),
    Phase.COLLECT_ADDRESS: frozenset(
        {Phase.COLLECT_ADDRESS, Phase.COLLECT_TIMING}
        | _common_terminal_transitions()
    ),
    Phase.COLLECT_TIMING: frozenset(
        {Phase.COLLECT_TIMING, Phase.COLLECT_DETAILS}
        | _common_terminal_transitions()
    ),
    Phase.COLLECT_DETAILS: frozenset(
        {Phase.COLLECT_DETAILS, Phase.PROPOSE_SLOT}
        | _common_terminal_transitions()
    ),
    Phase.PROPOSE_SLOT: frozenset(
        # PROPOSE_SLOT can loop on itself when the first proposal is
        # rejected by the customer (re-search a new slot) and may fall back
        # to COLLECT_TIMING if the customer wants to change the window.
        {
            Phase.PROPOSE_SLOT,
            Phase.COLLECT_TIMING,
            Phase.REQUEST_HUMAN_CONFIRM,
        }
        | _common_terminal_transitions()
    ),
    Phase.REQUEST_HUMAN_CONFIRM: frozenset(
        # The owner-reject path drops back to PROPOSE_SLOT with alternatives.
        {
            Phase.REQUEST_HUMAN_CONFIRM,
            Phase.PROPOSE_SLOT,
            Phase.CONFIRM_TO_CUSTOMER,
        }
        | _common_terminal_transitions()
    ),
    Phase.CONFIRM_TO_CUSTOMER: frozenset(
        {Phase.CONFIRM_TO_CUSTOMER, Phase.COMPLETED}
        | _common_terminal_transitions()
    ),
    # Terminals — no outgoing edges.
    Phase.COMPLETED: frozenset(),
    Phase.ABANDON: frozenset(),
    Phase.OUT_OF_SCOPE: frozenset(),
    Phase.HUMAN_HANDOFF: frozenset(),
}


def allowed_next(current: Phase) -> frozenset[Phase]:
    """Return the set of phases reachable in one step from ``current``."""
    return _ALLOWED[current]


def is_terminal(phase: Phase) -> bool:
    """Whether the conversation has stopped at ``phase``."""
    return phase in TERMINAL


def is_transition_allowed(current: Phase, proposed: Phase) -> bool:
    """Gate an LLM-proposed transition.

    ``current`` must not be terminal — by invariant, terminal phases should
    never produce another turn dispatch — and ``proposed`` must be in the
    allowed-next set.
    """
    if is_terminal(current):
        return False
    return proposed in _ALLOWED[current]


def assert_transition(current: Phase, proposed: Phase) -> None:
    """Raise :class:`InvalidTransition` if the edge is not legal.

    Used inside the worker's turn pipeline; on failure the orchestrator
    treats the LLM output as a parse failure and triggers the retry path.
    """
    if not is_transition_allowed(current, proposed):
        raise InvalidTransition(current=current, proposed=proposed)


class InvalidTransition(Exception):
    """Raised when the LLM proposes an edge not present in ``_ALLOWED``."""

    def __init__(self, current: Phase, proposed: Phase) -> None:
        super().__init__(
            f"illegal transition: {current.value} -> {proposed.value}"
        )
        self.current = current
        self.proposed = proposed
