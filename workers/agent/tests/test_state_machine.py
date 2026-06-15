"""Phase transitions + dead-state assertions for the state machine."""
from __future__ import annotations

import pytest

from state_machine import (
    ACTIVE,
    InvalidTransition,
    Phase,
    TERMINAL,
    allowed_next,
    assert_transition,
    is_terminal,
    is_transition_allowed,
)


def test_terminal_phases_have_no_outgoing_edges() -> None:
    for phase in TERMINAL:
        assert allowed_next(phase) == frozenset()
        assert is_terminal(phase)


def test_active_phases_are_not_terminal() -> None:
    for phase in ACTIVE:
        assert not is_terminal(phase)


def test_terminal_and_active_partition_phase_enum() -> None:
    assert TERMINAL.isdisjoint(ACTIVE)
    assert set(TERMINAL) | set(ACTIVE) == set(Phase)


def test_happy_path_is_legal_step_by_step() -> None:
    happy = [
        Phase.GREET,
        Phase.IDENTIFY_SERVICE,
        Phase.COLLECT_ADDRESS,
        Phase.COLLECT_TIMING,
        Phase.COLLECT_DETAILS,
        Phase.PROPOSE_SLOT,
        Phase.REQUEST_HUMAN_CONFIRM,
        Phase.CONFIRM_TO_CUSTOMER,
        Phase.COMPLETED,
    ]
    for current, nxt in zip(happy, happy[1:]):
        assert is_transition_allowed(current, nxt), (
            f"happy path step {current.value} -> {nxt.value} must be legal"
        )


def test_abandon_is_reachable_from_every_active_phase() -> None:
    for phase in ACTIVE:
        assert Phase.ABANDON in allowed_next(phase)


def test_out_of_scope_and_human_handoff_are_reachable_from_every_active_phase() -> None:
    for phase in ACTIVE:
        assert Phase.OUT_OF_SCOPE in allowed_next(phase)
        assert Phase.HUMAN_HANDOFF in allowed_next(phase)


def test_skip_propose_slot_is_illegal() -> None:
    # The skip-the-confirmation-loop short-circuit must not be allowed
    # — completed has to come through request_human_confirm.
    assert not is_transition_allowed(Phase.COLLECT_DETAILS, Phase.COMPLETED)
    assert not is_transition_allowed(Phase.COLLECT_DETAILS, Phase.CONFIRM_TO_CUSTOMER)
    assert not is_transition_allowed(Phase.PROPOSE_SLOT, Phase.COMPLETED)


def test_transitions_from_terminal_phases_are_illegal() -> None:
    for terminal in TERMINAL:
        for target in Phase:
            assert not is_transition_allowed(terminal, target)


def test_assert_transition_raises_on_illegal_edge() -> None:
    with pytest.raises(InvalidTransition) as excinfo:
        assert_transition(Phase.GREET, Phase.COMPLETED)
    assert excinfo.value.current == Phase.GREET
    assert excinfo.value.proposed == Phase.COMPLETED


def test_assert_transition_silent_on_legal_edge() -> None:
    assert_transition(Phase.GREET, Phase.IDENTIFY_SERVICE)


def test_propose_slot_can_loop_for_alternative_search() -> None:
    assert Phase.PROPOSE_SLOT in allowed_next(Phase.PROPOSE_SLOT)


def test_request_human_confirm_can_drop_back_to_propose_slot_on_owner_reject() -> None:
    assert Phase.PROPOSE_SLOT in allowed_next(Phase.REQUEST_HUMAN_CONFIRM)
