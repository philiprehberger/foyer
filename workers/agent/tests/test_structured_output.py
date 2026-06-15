"""Parse-failure retry policy + 3rd-failure escalation."""
from __future__ import annotations

import json

import pytest

from app import MAX_PARSE_RETRIES, run_turn
from cost import CostTracker
from llm_adapter import LLMRawResponse, MockProvider
from state_machine import Phase
from tests._fakes import (
    FakeInternalClient,
    ScriptedProvider,
    make_context,
    make_job,
)


def _good_response(next_phase: Phase, model: str = "mock-default") -> LLMRawResponse:
    payload = {
        "next_phase": next_phase.value,
        "reply_text": "all good",
        "intents": [],
        "confidence": 0.9,
        "tokens_in": 40,
        "tokens_out": 20,
        "cost_micros": 150,
        "model": model,
    }
    return LLMRawResponse(
        text=json.dumps(payload),
        model=model,
        tokens_in=40,
        tokens_out=20,
        cost_micros=150,
    )


def _bad_json_response() -> LLMRawResponse:
    return LLMRawResponse(
        text="{not parseable",
        model="mock-default",
        tokens_in=10,
        tokens_out=5,
        cost_micros=80,
    )


@pytest.mark.asyncio
async def test_first_call_succeeds_no_retries_no_escalation() -> None:
    provider = ScriptedProvider([_good_response(Phase.IDENTIFY_SERVICE)])
    ctx = make_context(current_phase=Phase.GREET)
    client = FakeInternalClient(ctx)

    out = await run_turn(
        job=make_job(),
        provider=provider,
        cost_tracker=CostTracker(),
        client=client,
    )

    assert out["status"] == "ok"
    assert len(provider.calls) == 1
    assert len(client.turn_results) == 1
    assert client.turn_results[0].next_phase == Phase.IDENTIFY_SERVICE
    assert client.escalations == []


@pytest.mark.asyncio
async def test_two_parse_failures_then_recover_succeeds() -> None:
    # Two bad responses, then a clean one on the 3rd (final) attempt.
    provider = ScriptedProvider(
        [
            _bad_json_response(),
            _bad_json_response(),
            _good_response(Phase.IDENTIFY_SERVICE),
        ]
    )
    ctx = make_context(current_phase=Phase.GREET)
    client = FakeInternalClient(ctx)

    out = await run_turn(
        job=make_job(),
        provider=provider,
        cost_tracker=CostTracker(),
        client=client,
    )

    assert out["status"] == "ok"
    assert len(provider.calls) == MAX_PARSE_RETRIES + 1
    assert client.escalations == []
    assert len(client.turn_results) == 1


@pytest.mark.asyncio
async def test_three_parse_failures_escalate_and_dlq() -> None:
    # Three bad responses — the worker exhausts retries and escalates.
    provider = ScriptedProvider(
        [_bad_json_response() for _ in range(MAX_PARSE_RETRIES + 1)]
    )
    ctx = make_context(current_phase=Phase.GREET)
    client = FakeInternalClient(ctx)

    out = await run_turn(
        job=make_job(),
        provider=provider,
        cost_tracker=CostTracker(),
        client=client,
    )

    assert out["status"] == "escalated"
    assert out["reason"] == "parse_failure_exhausted"
    assert len(provider.calls) == MAX_PARSE_RETRIES + 1
    assert client.turn_results == []
    assert len(client.escalations) == 1
    assert client.escalations[0].reason == "parse_failure_exhausted"


@pytest.mark.asyncio
async def test_invalid_transition_treated_as_parse_failure_then_recovered() -> None:
    # GREET -> COMPLETED is illegal — the worker rejects, retries, then
    # accepts a legal edge on the next attempt.
    provider = ScriptedProvider(
        [
            _good_response(Phase.COMPLETED),  # illegal from GREET
            _good_response(Phase.IDENTIFY_SERVICE),
        ]
    )
    ctx = make_context(current_phase=Phase.GREET)
    client = FakeInternalClient(ctx)

    out = await run_turn(
        job=make_job(),
        provider=provider,
        cost_tracker=CostTracker(),
        client=client,
    )

    assert out["status"] == "ok"
    assert len(provider.calls) == 2
    assert client.turn_results[0].next_phase == Phase.IDENTIFY_SERVICE


@pytest.mark.asyncio
async def test_mock_provider_bad_json_trigger_drives_retry_path() -> None:
    # Real MockProvider this time — drives via the BAD_JSON trigger token.
    # The mock returns invalid JSON every time, so the worker escalates.
    provider = MockProvider()
    ctx = make_context(
        current_phase=Phase.GREET,
        last_user_message="hi BAD_JSON test",
    )
    client = FakeInternalClient(ctx)

    out = await run_turn(
        job=make_job(),
        provider=provider,
        cost_tracker=CostTracker(),
        client=client,
    )

    assert out["status"] == "escalated"
    assert out["reason"] == "parse_failure_exhausted"
    assert client.escalations[0].reason == "parse_failure_exhausted"
