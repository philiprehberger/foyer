"""Pluggable LLM provider interface.

Three providers:

- ``mock``    — deterministic; drives every test in this package. Honours
                special triggers in the customer message (``OUTSIDE_AREA``,
                ``STOP_TEST``, ``BAD_JSON``) so tests can exercise the
                out-of-scope, human-handoff, and parse-failure paths
                without any model call.
- ``anthropic`` — real Claude provider. Stubbed; not wired. ``call`` raises
                ``LLMNotConfigured`` until real credentials + an HTTP
                client land in a follow-up.
- ``openai``  — real GPT provider. Same stub posture as Anthropic.

The adapter contract is the smallest surface area that lets the
orchestrator stay provider-agnostic: ``call(model, system, user) ->
LLMRawResponse``. Parse + schema-validate happens upstream in ``app.py``.
"""
from __future__ import annotations

import json
import os
import re
from dataclasses import dataclass
from typing import Protocol

from state_machine import Phase, allowed_next

PROVIDER_ENV = "FOYER_LLM_PROVIDER"
DEFAULT_MODEL_ENV = "FOYER_LLM_MODEL"


class LLMNotConfigured(RuntimeError):
    """Raised when a real provider is selected without credentials wired."""


@dataclass(frozen=True)
class LLMRawResponse:
    """Wire-level response from a provider.

    ``text`` is the raw model output (expected to be a JSON object; the
    orchestrator parses + validates). ``model`` is the actual model used
    (post cheap-mode degradation, post-router selection). Token counts +
    estimated cost are provider-reported when available, else 0.
    """

    text: str
    model: str
    tokens_in: int
    tokens_out: int
    cost_micros: int


class LLMProvider(Protocol):
    """The narrow contract every adapter satisfies."""

    name: str

    async def call(
        self,
        *,
        model: str,
        system: str,
        user: str,
    ) -> LLMRawResponse: ...


# ---------- Mock provider ----------


# Happy-path advance map. Each non-terminal phase advances to its single
# successor, except PROPOSE_SLOT which advances to REQUEST_HUMAN_CONFIRM
# (matching the canonical booking flow).
_HAPPY_PATH_ADVANCE: dict[Phase, Phase] = {
    Phase.GREET: Phase.IDENTIFY_SERVICE,
    Phase.IDENTIFY_SERVICE: Phase.COLLECT_ADDRESS,
    Phase.COLLECT_ADDRESS: Phase.COLLECT_TIMING,
    Phase.COLLECT_TIMING: Phase.COLLECT_DETAILS,
    Phase.COLLECT_DETAILS: Phase.PROPOSE_SLOT,
    Phase.PROPOSE_SLOT: Phase.REQUEST_HUMAN_CONFIRM,
    Phase.REQUEST_HUMAN_CONFIRM: Phase.CONFIRM_TO_CUSTOMER,
    Phase.CONFIRM_TO_CUSTOMER: Phase.COMPLETED,
}


# Trigger tokens the mock provider recognises in the user prompt. Tests
# inject these via the last_user_message field; the orchestrator's
# scope-guard test rides on OUTSIDE_AREA, the human-handoff test on
# STOP_TEST, and the structured-output retry test on BAD_JSON.
_TRIGGER_OUTSIDE_AREA = "OUTSIDE_AREA"
_TRIGGER_HUMAN_HANDOFF = "STOP_TEST"
_TRIGGER_BAD_JSON = "BAD_JSON"


class MockProvider:
    """Deterministic provider used by every test in this package.

    The provider sniffs the user prompt for both the current phase line
    (emitted by :mod:`prompts`) and any embedded trigger token, then
    returns the matching canned JSON response.
    """

    name = "mock"

    _PHASE_LINE_RE = re.compile(r"^Current phase: (?P<phase>[a-z_]+)$", re.MULTILINE)

    async def call(
        self,
        *,
        model: str,
        system: str,
        user: str,
    ) -> LLMRawResponse:
        current = self._extract_phase(system)
        bad_json = _TRIGGER_BAD_JSON in user
        outside = _TRIGGER_OUTSIDE_AREA in user
        handoff = _TRIGGER_HUMAN_HANDOFF in user

        if bad_json:
            # Intentionally malformed; the worker should reject + retry.
            text = "{not valid json, oops"
            return LLMRawResponse(
                text=text, model=model, tokens_in=10, tokens_out=8, cost_micros=120
            )

        if outside:
            response = self._terminal_response(
                phase=Phase.OUT_OF_SCOPE,
                reply=(
                    "I can only help with booking inside our service area. "
                    "Want me to have someone call you back?"
                ),
                model=model,
            )
            return self._wrap(response, model)

        if handoff:
            response = self._terminal_response(
                phase=Phase.HUMAN_HANDOFF,
                reply=(
                    "I'll have a person reach out shortly — hang tight."
                ),
                model=model,
            )
            return self._wrap(response, model)

        nxt = _HAPPY_PATH_ADVANCE.get(current, current)
        # Defensive: if the mapping ever produced an illegal edge, fall
        # back to a self-loop so the orchestrator doesn't reject the mock.
        if nxt not in allowed_next(current):
            nxt = current

        response = {
            "next_phase": nxt.value,
            "reply_text": f"[mock] phase {current.value} -> {nxt.value}",
            "intents": [],
            "confidence": 0.92,
            "tokens_in": 42,
            "tokens_out": 24,
            "cost_micros": 180,
            "model": model,
        }
        return self._wrap(response, model)

    # ----- helpers -----

    def _extract_phase(self, system: str) -> Phase:
        m = self._PHASE_LINE_RE.search(system)
        if not m:
            return Phase.GREET
        try:
            return Phase(m.group("phase"))
        except ValueError:
            return Phase.GREET

    def _terminal_response(
        self, *, phase: Phase, reply: str, model: str
    ) -> dict[str, object]:
        return {
            "next_phase": phase.value,
            "reply_text": reply,
            "intents": [],
            "confidence": 0.99,
            "tokens_in": 32,
            "tokens_out": 16,
            "cost_micros": 140,
            "model": model,
        }

    def _wrap(self, payload: dict[str, object], model: str) -> LLMRawResponse:
        return LLMRawResponse(
            text=json.dumps(payload),
            model=model,
            tokens_in=int(payload.get("tokens_in", 0) or 0),
            tokens_out=int(payload.get("tokens_out", 0) or 0),
            cost_micros=int(payload.get("cost_micros", 0) or 0),
        )


# ---------- Real-provider stubs ----------


class AnthropicProvider:
    """Stub. Wire when ``ANTHROPIC_API_KEY`` is provisioned + reviewed."""

    name = "anthropic"

    async def call(
        self,
        *,
        model: str,
        system: str,
        user: str,
    ) -> LLMRawResponse:
        if not os.environ.get("ANTHROPIC_API_KEY"):
            raise LLMNotConfigured(
                "ANTHROPIC_API_KEY not set; cannot call real Anthropic provider"
            )
        raise LLMNotConfigured(
            "anthropic provider is stubbed — wire the HTTP call in a follow-up"
        )


class OpenAIProvider:
    """Stub. Wire when ``OPENAI_API_KEY`` is provisioned + reviewed."""

    name = "openai"

    async def call(
        self,
        *,
        model: str,
        system: str,
        user: str,
    ) -> LLMRawResponse:
        if not os.environ.get("OPENAI_API_KEY"):
            raise LLMNotConfigured(
                "OPENAI_API_KEY not set; cannot call real OpenAI provider"
            )
        raise LLMNotConfigured(
            "openai provider is stubbed — wire the HTTP call in a follow-up"
        )


# ---------- Factory ----------


def make_provider(name: str | None = None) -> LLMProvider:
    """Resolve the active provider.

    Honours the ``FOYER_LLM_PROVIDER`` env var when ``name`` is omitted.
    Unknown names fall back to ``mock`` — keeps the worker bootable in
    smoke environments where credentials are intentionally absent.
    """
    selected = (name or os.environ.get(PROVIDER_ENV, "mock")).lower()
    if selected == "anthropic":
        return AnthropicProvider()
    if selected == "openai":
        return OpenAIProvider()
    return MockProvider()


def default_model() -> str:
    """The configured default model name (env-driven)."""
    return os.environ.get(DEFAULT_MODEL_ENV, "mock-default")
