"""Property-based invariants for the state machine.

Hypothesis generates random walks over the transition graph and asserts:

- ``completed`` is unreachable without passing through ``propose_slot``.
- Walks from terminal phases never produce a follow-on step.
- Any active phase can drop into ``abandon`` in a single step.

If you add a phase, extend ``_phase_strategy`` so the property suite
covers it.
"""
from __future__ import annotations

from hypothesis import HealthCheck, given, settings, strategies as st

from state_machine import (
    ACTIVE,
    Phase,
    TERMINAL,
    allowed_next,
    is_terminal,
)

_phase_strategy = st.sampled_from(list(Phase))
_active_phase_strategy = st.sampled_from(list(ACTIVE))


def _walk(start: Phase, choices: list[int], max_steps: int = 30) -> list[Phase]:
    """Drive a deterministic walk using Hypothesis-supplied indices.

    Each step picks ``choices[i] % len(allowed_next(current))``; once a
    terminal phase is hit the walk stops short.
    """
    path: list[Phase] = [start]
    current = start
    for i, c in enumerate(choices[:max_steps]):
        nxt_set = allowed_next(current)
        if not nxt_set:
            break
        ordered = sorted(nxt_set, key=lambda p: p.value)
        chosen = ordered[c % len(ordered)]
        path.append(chosen)
        current = chosen
        if is_terminal(current):
            break
    return path


@given(
    # Only GREET makes the invariant load-bearing — real conversations always
    # start there. Starting from CONFIRM_TO_CUSTOMER and stepping to COMPLETED
    # is one legal hop and doesn't violate the property we actually care about,
    # which is "no agent ever reaches `completed` without first holding a slot
    # in `propose_slot`."
    choices=st.lists(st.integers(min_value=0, max_value=100), min_size=1, max_size=30),
)
@settings(
    max_examples=400,
    deadline=None,
    suppress_health_check=[HealthCheck.too_slow],
)
def test_completed_requires_propose_slot(choices: list[int]) -> None:
    path = _walk(Phase.GREET, choices)
    if Phase.COMPLETED in path:
        assert Phase.PROPOSE_SLOT in path, (
            f"path reached completed without propose_slot: "
            f"{[p.value for p in path]}"
        )


@given(start=st.sampled_from(list(TERMINAL)))
@settings(max_examples=20)
def test_terminal_phases_produce_zero_step_walk(start: Phase) -> None:
    path = _walk(start, [0, 0, 0, 0, 0])
    assert path == [start]


@given(start=_active_phase_strategy)
@settings(max_examples=50)
def test_every_active_phase_can_reach_abandon_in_one_step(start: Phase) -> None:
    assert Phase.ABANDON in allowed_next(start)


@given(start=_active_phase_strategy)
@settings(max_examples=50)
def test_every_active_phase_can_reach_human_handoff_in_one_step(start: Phase) -> None:
    assert Phase.HUMAN_HANDOFF in allowed_next(start)


@given(start=_active_phase_strategy)
@settings(max_examples=50)
def test_every_active_phase_can_reach_out_of_scope_in_one_step(start: Phase) -> None:
    assert Phase.OUT_OF_SCOPE in allowed_next(start)


@given(
    start=_active_phase_strategy,
    choices=st.lists(st.integers(min_value=0, max_value=100), min_size=1, max_size=30),
)
@settings(max_examples=200, deadline=None)
def test_walks_only_use_legal_edges(start: Phase, choices: list[int]) -> None:
    path = _walk(start, choices)
    for prev, nxt in zip(path, path[1:]):
        assert nxt in allowed_next(prev), (
            f"illegal step in random walk: {prev.value} -> {nxt.value}"
        )
