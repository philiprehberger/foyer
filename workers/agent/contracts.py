"""Pydantic v2 mirrors of ``infra/contracts/*.schema.json``.

Keep these models 1:1 with the JSON Schemas — both PHP and Python codegen
from the same source of truth, and the CI parity check will catch drift.
The hand-written models exist so the FastAPI worker gets pydantic
validation + IDE typing without depending on a codegen pipeline at runtime.

If you change a field here, update the JSON Schema in the same commit.
"""
from __future__ import annotations

import re
from datetime import datetime
from typing import Annotated, Any, Literal

from pydantic import BaseModel, ConfigDict, Field, field_validator

from state_machine import Phase

# ULID: Crockford base-32, 26 chars, no I/L/O/U.
_ULID_RE = re.compile(r"^[0-9A-HJKMNP-TV-Z]{26}$")

UlidStr = Annotated[str, Field(pattern=_ULID_RE.pattern, min_length=26, max_length=26)]


class StrictModel(BaseModel):
    """Base class that rejects unknown fields + freezes after construction.

    Worker payloads cross a process boundary — silently accepting unknown
    fields would mask schema-version drift between Laravel + the worker.
    """

    model_config = ConfigDict(
        extra="forbid",
        frozen=True,
        str_strip_whitespace=False,
        populate_by_name=False,
    )


# ---------- AgentTurn (job payload Laravel -> worker) ----------


class AgentTurnJob(StrictModel):
    """The inbound job. Mirrors ``agent-turn.schema.json``."""

    schema_version: Literal[1]
    conversation_id: UlidStr
    message_id: UlidStr
    business_id: UlidStr
    channel: Literal["sms", "web"]
    enqueued_at: datetime


# ---------- AgentTurnResponse (worker validates LLM output) ----------


class Intent(StrictModel):
    """A structured action the orchestrator must execute."""

    name: Annotated[str, Field(min_length=1, max_length=64)]
    args: dict[str, Any]


class AgentTurnResponse(StrictModel):
    """Strict structured-output shape the LLM must return.

    Mirrors ``agent-turn-response.schema.json``. The worker rejects any
    response that fails this validation and triggers the retry loop.
    """

    next_phase: Phase
    reply_text: Annotated[str, Field(min_length=1, max_length=1600)]
    intents: list[Intent]
    confidence: Annotated[float, Field(ge=0.0, le=1.0)]
    tokens_in: Annotated[int, Field(ge=0)]
    tokens_out: Annotated[int, Field(ge=0)]
    cost_micros: Annotated[int, Field(ge=0)]
    model: Annotated[str, Field(min_length=1, max_length=128)]

    @field_validator("reply_text")
    @classmethod
    def _normalize_newlines(cls, v: str) -> str:
        # SMS rendering across carriers is happier without CRLF.
        return v.replace("\r\n", "\n")


# ---------- Turn context (response from Laravel internal API) ----------


class BusinessConfig(StrictModel):
    """The slice of business config the agent worker needs per turn.

    Laravel builds this from the ``businesses`` row + scope JSON; the
    worker never reads from Postgres directly.
    """

    id: UlidStr
    name: Annotated[str, Field(min_length=1, max_length=200)]
    timezone: Annotated[str, Field(min_length=1, max_length=64)]
    persona: Literal["professional", "casual", "gentle"]
    system_prompt_suffix: Annotated[str, Field(default="", max_length=2000)] = ""
    service_types: list[str] = []
    service_area_description: Annotated[str, Field(default="", max_length=2000)] = ""
    business_hours_description: Annotated[
        str, Field(default="", max_length=2000)
    ] = ""
    human_handoff_threshold: Annotated[float, Field(ge=0.0, le=1.0)] = 0.5
    cost_ceiling_micros: Annotated[int, Field(ge=0)] = 500_000
    cheap_mode_model: Annotated[str, Field(min_length=1, max_length=128)] = (
        "claude-haiku"
    )


class TurnMessage(StrictModel):
    """A single message in the rolling conversation window."""

    role: Literal["customer", "agent", "owner", "system", "tool"]
    text: Annotated[str, Field(max_length=4000)]
    phase: Phase | None = None


class TurnContext(StrictModel):
    """The full per-turn payload Laravel returns to the worker.

    The worker hits ``GET /_internal/conversations/{id}/turn-context`` to
    fetch this; everything the LLM call needs is here so the worker does
    not chain extra HTTP round trips.
    """

    conversation_id: UlidStr
    business: BusinessConfig
    current_phase: Phase
    messages: list[TurnMessage]
    last_user_message: Annotated[str, Field(max_length=4000)]
    cumulative_cost_micros_today: Annotated[int, Field(ge=0)] = 0


# ---------- Turn result (worker -> Laravel) ----------


class TurnResultPayload(StrictModel):
    """Body posted to ``POST /_internal/conversations/{id}/turn-result``.

    Laravel persists this into ``messages`` (agent row), updates the
    derived phase, and enqueues any follow-on jobs based on ``intents``.
    """

    conversation_id: UlidStr
    next_phase: Phase
    reply_text: Annotated[str, Field(min_length=1, max_length=1600)]
    intents: list[Intent]
    confidence: Annotated[float, Field(ge=0.0, le=1.0)]
    tokens_in: Annotated[int, Field(ge=0)]
    tokens_out: Annotated[int, Field(ge=0)]
    cost_micros: Annotated[int, Field(ge=0)]
    model: Annotated[str, Field(min_length=1, max_length=128)]
    degraded_to_cheap_mode: bool = False


class EscalatePayload(StrictModel):
    """Body posted to ``POST /_internal/conversations/{id}/escalate``."""

    conversation_id: UlidStr
    reason: Literal[
        "parse_failure_exhausted",
        "cost_ceiling_exceeded",
        "low_confidence",
        "scope_violation",
        "poison_message",
    ]
    detail: Annotated[str, Field(default="", max_length=2000)] = ""
