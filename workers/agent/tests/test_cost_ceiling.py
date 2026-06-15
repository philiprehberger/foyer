"""Cost-ceiling pre-flight + cheap-mode + killswitch tests.

Two layers of assertions:

- :class:`cost.CostTracker` unit-level — the mode transitions are
  arithmetic and worth testing in isolation.
- :func:`app.run_turn` integration — degrading model selection +
  escalation flow.
"""
from __future__ import annotations

import json

import pytest

from app import run_turn
from cost import CostMode, CostTracker
from llm_adapter import LLMRawResponse
from state_machine import Phase
from tests._fakes import (
    FakeInternalClient,
    ScriptedProvider,
    make_business,
    make_context,
    make_job,
)


# ---------- CostTracker unit tests ----------


def test_normal_mode_at_zero_spend() -> None:
    t = CostTracker()
    d = t.preflight(
        business_id="b1",
        default_model="sonnet",
        ceiling_micros=1000,
        cheap_mode_model="haiku",
    )
    assert d.mode == CostMode.NORMAL
    assert d.chosen_model == "sonnet"
    assert not d.should_escalate


def test_record_pushes_into_cheap_mode_at_ceiling() -> None:
    t = CostTracker()
    d = t.record(
        business_id="b1",
        cost_micros=1000,
        default_model="sonnet",
        ceiling_micros=1000,
        cheap_mode_model="haiku",
    )
    assert d.mode == CostMode.CHEAP
    assert d.chosen_model == "haiku"
    assert not d.should_escalate


def test_record_pushes_into_killed_at_one_point_five_x_ceiling() -> None:
    t = CostTracker()
    # First push into cheap mode.
    t.record(
        business_id="b1",
        cost_micros=1000,
        default_model="sonnet",
        ceiling_micros=1000,
        cheap_mode_model="haiku",
    )
    # Now push to 1.5x ceiling.
    d = t.record(
        business_id="b1",
        cost_micros=500,
        default_model="sonnet",
        ceiling_micros=1000,
        cheap_mode_model="haiku",
    )
    assert d.mode == CostMode.KILLED
    assert d.should_escalate


def test_preflight_uses_already_spent_seed() -> None:
    t = CostTracker()
    d = t.preflight(
        business_id="b1",
        default_model="sonnet",
        ceiling_micros=1000,
        cheap_mode_model="haiku",
        already_spent_micros=2000,  # 2x ceiling
    )
    # 2x ceiling — pre-flight should escalate immediately.
    assert d.mode == CostMode.KILLED
    assert d.should_escalate


def test_ceiling_is_per_business() -> None:
    t = CostTracker()
    t.record(
        business_id="b1",
        cost_micros=999_999,
        default_model="sonnet",
        ceiling_micros=1_000_000,
        cheap_mode_model="haiku",
    )
    d2 = t.preflight(
        business_id="b2",
        default_model="sonnet",
        ceiling_micros=1_000_000,
        cheap_mode_model="haiku",
    )
    assert d2.mode == CostMode.NORMAL


def test_snapshot_returns_copy_not_reference() -> None:
    t = CostTracker()
    t.record(
        business_id="b1",
        cost_micros=100,
        default_model="sonnet",
        ceiling_micros=1000,
        cheap_mode_model="haiku",
    )
    snap = t.snapshot("b1")
    assert snap is not None
    snap.spent_micros = 9_999_999  # type: ignore[misc]
    # Original should not be mutated.
    again = t.snapshot("b1")
    assert again is not None
    assert again.spent_micros == 100


# ---------- Integration with run_turn ----------


def _good_response(
    next_phase: Phase, *, cost_micros: int, model: str
) -> LLMRawResponse:
    payload = {
        "next_phase": next_phase.value,
        "reply_text": "all good",
        "intents": [],
        "confidence": 0.9,
        "tokens_in": 100,
        "tokens_out": 50,
        "cost_micros": cost_micros,
        "model": model,
    }
    return LLMRawResponse(
        text=json.dumps(payload),
        model=model,
        tokens_in=100,
        tokens_out=50,
        cost_micros=cost_micros,
    )


@pytest.mark.asyncio
async def test_first_turn_at_normal_mode_uses_default_model() -> None:
    provider = ScriptedProvider(
        [_good_response(Phase.IDENTIFY_SERVICE, cost_micros=10, model="mock-default")]
    )
    business = make_business(cost_ceiling_micros=1000)
    ctx = make_context(business=business)
    client = FakeInternalClient(ctx)
    tracker = CostTracker()

    out = await run_turn(
        job=make_job(), provider=provider, cost_tracker=tracker, client=client
    )

    assert out["status"] == "ok"
    assert out["cost_mode"] == CostMode.NORMAL.value
    assert not out["degraded_to_cheap_mode"]
    snap = tracker.snapshot(business.id)
    assert snap is not None
    assert snap.spent_micros == 10


@pytest.mark.asyncio
async def test_turn_that_crosses_ceiling_degrades_to_cheap_mode() -> None:
    # 600 + 400 = 1000 = ceiling. Pre-flight sees 600 (NORMAL),
    # post-call sees 1000 (CHEAP) — degraded_to_cheap_mode = True.
    provider = ScriptedProvider(
        [_good_response(Phase.IDENTIFY_SERVICE, cost_micros=400, model="mock-default")]
    )
    business = make_business(
        cost_ceiling_micros=1000, cheap_mode_model="claude-haiku"
    )
    ctx = make_context(
        business=business, cumulative_cost_micros_today=600
    )
    client = FakeInternalClient(ctx)
    tracker = CostTracker()

    out = await run_turn(
        job=make_job(), provider=provider, cost_tracker=tracker, client=client
    )

    assert out["status"] == "ok"
    assert out["cost_mode"] == CostMode.CHEAP.value
    assert out["degraded_to_cheap_mode"] is True
    assert client.turn_results[0].degraded_to_cheap_mode is True


@pytest.mark.asyncio
async def test_preflight_killswitch_blocks_llm_call_and_escalates() -> None:
    # Already at 2000 micros vs 1000 ceiling — pre-flight pushes to KILLED
    # immediately and the provider must not be called at all.
    provider = ScriptedProvider([])  # any call would explode (empty script)
    business = make_business(cost_ceiling_micros=1000)
    ctx = make_context(business=business, cumulative_cost_micros_today=2000)
    client = FakeInternalClient(ctx)

    out = await run_turn(
        job=make_job(),
        provider=provider,
        cost_tracker=CostTracker(),
        client=client,
    )

    assert out["status"] == "escalated"
    assert out["reason"] == "cost_ceiling_exceeded"
    assert provider.calls == []
    assert client.escalations[0].reason == "cost_ceiling_exceeded"


@pytest.mark.asyncio
async def test_recording_a_killing_turn_posts_result_then_escalates() -> None:
    # Pre-flight at 800/1000 — still NORMAL. Turn costs 800 -> ratio 1.6
    # which jumps NORMAL -> CHEAP -> KILLED on the same record() call.
    # The result still posts (the turn ran), but a follow-on escalate
    # signals Laravel to stop dispatching new turns.
    provider = ScriptedProvider(
        [_good_response(Phase.IDENTIFY_SERVICE, cost_micros=800, model="mock-default")]
    )
    business = make_business(cost_ceiling_micros=1000)
    ctx = make_context(business=business, cumulative_cost_micros_today=800)
    client = FakeInternalClient(ctx)

    out = await run_turn(
        job=make_job(),
        provider=provider,
        cost_tracker=CostTracker(),
        client=client,
    )

    assert out["status"] == "ok"
    assert out["cost_mode"] == CostMode.KILLED.value
    assert len(client.turn_results) == 1
    assert len(client.escalations) == 1
    assert client.escalations[0].reason == "cost_ceiling_exceeded"
