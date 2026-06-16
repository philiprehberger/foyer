"""Pluggable LLM provider interface.

Three providers:

- ``mock``      — deterministic; drives every test in this package. Honours
                  special triggers (``OUTSIDE_AREA``, ``STOP_TEST``,
                  ``BAD_JSON``) so tests can exercise out-of-scope,
                  human-handoff, and parse-failure paths without an HTTP
                  call.
- ``anthropic`` — real Claude provider. POSTs to the Messages API; reports
                  token + cost. Raises ``LLMNotConfigured`` if
                  ``ANTHROPIC_API_KEY`` is missing.
- ``openai``    — real GPT provider. Still stubbed pending review of the
                  HTTP shape + retry policy.

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

import httpx

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
            # Intentionally malformed. The trigger string is echoed inside the
            # broken output so the worker's repair prompt — which embeds the
            # verbatim previous output — carries BAD_JSON forward into the
            # next user message. That keeps the trigger sticky across retries
            # so tests can exercise the full parse-failure exhaustion path.
            text = "{BAD_JSON: not valid json, oops"
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


# Anthropic Messages API pricing in micros-per-token. 1_000_000 micros = $1.
# Sonnet 4.x: $3 / $15 per M = 3 / 15 micros per token.
# Haiku 4.x: $0.80 / $4 per M = 0.8 / 4 micros per token (rounded up).
# Opus 4.x: $15 / $75 per M = 15 / 75 micros per token.
# Unknown models fall back to Sonnet pricing as the safe upper bound.
_ANTHROPIC_PRICING_MICROS = {
    "sonnet": (3, 15),
    "haiku": (1, 4),
    "opus": (15, 75),
}


def _price_micros(model: str, tokens_in: int, tokens_out: int) -> int:
    family = "sonnet"
    for key in _ANTHROPIC_PRICING_MICROS:
        if key in model.lower():
            family = key
            break
    in_rate, out_rate = _ANTHROPIC_PRICING_MICROS[family]
    return tokens_in * in_rate + tokens_out * out_rate


class AnthropicProvider:
    """Calls the Anthropic Messages API.

    Requires ``ANTHROPIC_API_KEY`` in env. Uses the JSON shape from
    https://docs.anthropic.com/en/api/messages — POSTs system + a single
    user turn, returns the assistant text (which the orchestrator
    validates against the strict JSON output schema).

    Token + cost accounting comes from the ``usage`` block on the response;
    cost_micros is derived from the model family rather than echoed back
    (Anthropic does not report dollar cost in the response).
    """

    name = "anthropic"

    # Anthropic's stable max_tokens default per their docs.
    _DEFAULT_MAX_TOKENS = 1024
    _API_URL = "https://api.anthropic.com/v1/messages"
    _API_VERSION = "2023-06-01"
    _TIMEOUT_SECONDS = 30.0

    async def call(
        self,
        *,
        model: str,
        system: str,
        user: str,
    ) -> LLMRawResponse:
        key = os.environ.get("ANTHROPIC_API_KEY", "")
        if not key:
            raise LLMNotConfigured(
                "ANTHROPIC_API_KEY not set; cannot call real Anthropic provider"
            )

        body = {
            "model": model,
            "max_tokens": self._DEFAULT_MAX_TOKENS,
            "system": system,
            "messages": [{"role": "user", "content": user}],
        }

        async with httpx.AsyncClient(timeout=self._TIMEOUT_SECONDS) as client:
            response = await client.post(
                self._API_URL,
                headers={
                    "x-api-key": key,
                    "anthropic-version": self._API_VERSION,
                    "content-type": "application/json",
                },
                json=body,
            )

        if response.status_code != 200:
            raise LLMNotConfigured(
                f"Anthropic API returned {response.status_code}: "
                f"{response.text[:500]}"
            )

        payload = response.json()
        text_blocks = [
            block.get("text", "")
            for block in payload.get("content", [])
            if block.get("type") == "text"
        ]
        text = "".join(text_blocks)
        usage = payload.get("usage", {})
        tokens_in = int(usage.get("input_tokens", 0))
        tokens_out = int(usage.get("output_tokens", 0))
        actual_model = payload.get("model", model)

        return LLMRawResponse(
            text=text,
            model=actual_model,
            tokens_in=tokens_in,
            tokens_out=tokens_out,
            cost_micros=_price_micros(actual_model, tokens_in, tokens_out),
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
