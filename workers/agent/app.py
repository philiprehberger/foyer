"""Foyer FastAPI agent worker.

Endpoints:

- POST ``/run-turn``      run one ``AgentTurn`` end-to-end and POST the
                          result back to Laravel via the internal API
- POST ``/reload-config`` clear the in-process cost ledger (re-seeds from
                          Laravel's durable rollup on the next turn)
- GET  ``/healthz``       liveness probe

The worker is invoked one of two ways:

1. Laravel POSTs the ``AgentTurn`` job payload directly to ``/run-turn``
   (the shape we ship with — simpler, no queue plumbing on the Python side).
2. Future: a Python-side Redis consumer dequeues ``AgentTurn`` jobs and
   calls the same ``run_turn`` pipeline. Both shapes share the same code
   path; only the entry point changes.

Authentication: every non-healthz endpoint requires
``X-Foyer-Internal-Sig: hmac_sha256(secret, body)`` matching the inbound
body. Same primitive both directions across the internal API boundary.
"""
from __future__ import annotations

import json
import os
from contextlib import asynccontextmanager
from typing import AsyncIterator

import structlog
from fastapi import Depends, FastAPI, Header, HTTPException, Request, status
from pydantic import ValidationError

from contracts import (
    AgentTurnJob,
    AgentTurnResponse,
    EscalatePayload,
    Intent,
    TurnContext,
    TurnResultPayload,
)
from cost import CostMode, CostTracker
from internal_api import (
    InternalAPIClient,
    InternalAPIConfig,
    InternalAPIError,
    verify_signature,
)
from llm_adapter import (
    LLMNotConfigured,
    LLMProvider,
    default_model,
    make_provider,
)
from prompts import (
    OUT_OF_SCOPE_FALLBACK,
    PromptInjectionDetected,
    build_repair_prompt,
    build_system_prompt,
    build_user_prompt,
)
from state_machine import InvalidTransition, Phase, allowed_next, is_terminal

MAX_PARSE_RETRIES = 2  # plus the initial call = 3 attempts total
SIG_HEADER = "X-Foyer-Internal-Sig"


structlog.configure(
    processors=[
        structlog.processors.add_log_level,
        structlog.processors.TimeStamper(fmt="iso", utc=True),
        structlog.processors.JSONRenderer(),
    ]
)
log = structlog.get_logger("foyer.agent")


# ---------- Lifespan + DI ----------


@asynccontextmanager
async def _lifespan(app: FastAPI) -> AsyncIterator[None]:
    """Wire shared state at startup, tear down on shutdown.

    The cost ledger + provider live on ``app.state`` so handlers can
    swap them in tests via dependency overrides without touching globals.
    """
    app.state.cost_tracker = CostTracker()
    app.state.provider = make_provider()
    app.state.internal_config = InternalAPIConfig.from_env()
    log.info(
        "agent_worker.startup",
        provider=app.state.provider.name,
        default_model=default_model(),
        internal_base_url=app.state.internal_config.base_url,
    )
    yield
    log.info("agent_worker.shutdown")


app = FastAPI(
    title="Foyer Agent Worker",
    description=(
        "LLM orchestration sidecar for Foyer. Consumes AgentTurn jobs, "
        "validates structured output against a strict JSON schema, retries "
        "on parse failure, enforces per-business per-day cost ceilings, "
        "and posts results to the Laravel internal API."
    ),
    version="0.1.0",
    lifespan=_lifespan,
)


def get_provider(request: Request) -> LLMProvider:
    return request.app.state.provider


def get_cost_tracker(request: Request) -> CostTracker:
    return request.app.state.cost_tracker


def get_internal_config(request: Request) -> InternalAPIConfig:
    return request.app.state.internal_config


async def require_internal_signature(
    request: Request,
    x_foyer_internal_sig: str = Header(default="", alias=SIG_HEADER),
    config: InternalAPIConfig = Depends(get_internal_config),
) -> bytes:
    """HMAC gate for inbound internal-API calls.

    The raw body is re-used downstream (FastAPI consumed it here) so the
    handler can re-parse without a second read.
    """
    body = await request.body()
    if not x_foyer_internal_sig or not verify_signature(
        config.secret, body, x_foyer_internal_sig
    ):
        log.warning(
            "agent_worker.signature_mismatch",
            path=request.url.path,
            body_bytes=len(body),
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="invalid internal signature",
        )
    return body


# ---------- Endpoints ----------


@app.get("/healthz")
def healthz(request: Request) -> dict[str, str]:
    return {
        "status": "healthy",
        "provider": request.app.state.provider.name,
        "default_model": default_model(),
    }


@app.post("/reload-config", dependencies=[Depends(require_internal_signature)])
def reload_config(request: Request) -> dict[str, str]:
    """Clear the in-process cost ledger.

    Called by Laravel after a scope-config change (the ceiling moved or
    cheap-mode model changed). The next turn re-seeds from Laravel's
    ``llm_cost_daily`` rollup.
    """
    request.app.state.cost_tracker = CostTracker()
    log.info("agent_worker.reload_config")
    return {"status": "reloaded"}


@app.post("/run-turn")
async def run_turn_endpoint(
    request: Request,
    body: bytes = Depends(require_internal_signature),
    provider: LLMProvider = Depends(get_provider),
    cost_tracker: CostTracker = Depends(get_cost_tracker),
    config: InternalAPIConfig = Depends(get_internal_config),
) -> dict[str, object]:
    """Run one ``AgentTurn`` synchronously.

    Returns a small status doc; the actual result is POSTed back to
    Laravel via the internal API inside this handler.
    """
    try:
        job = AgentTurnJob.model_validate_json(body)
    except ValidationError as e:
        log.warning("agent_worker.bad_job", errors=e.errors())
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail="invalid AgentTurn payload",
        ) from e

    async with InternalAPIClient(config=config) as client:
        outcome = await run_turn(
            job=job,
            provider=provider,
            cost_tracker=cost_tracker,
            client=client,
        )
    return outcome


# ---------- Core pipeline ----------


async def run_turn(
    *,
    job: AgentTurnJob,
    provider: LLMProvider,
    cost_tracker: CostTracker,
    client: InternalAPIClient,
) -> dict[str, object]:
    """Run one turn end-to-end.

    1. Fetch context from Laravel.
    2. Build the prompt.
    3. Pre-flight cost check; pick the model.
    4. If KILLED -> escalate immediately, do not call the LLM.
    5. Call the LLM; up to 2 retries on parse failure.
    6. On 3rd parse failure -> escalate ``parse_failure_exhausted``.
    7. Validate transition against the state machine; treat illegal edge
       as a parse failure (same retry loop).
    8. Record cost, post turn result back to Laravel.
    9. If the recorded cost now exceeds the kill threshold, escalate.
    """
    bound = log.bind(
        conversation_id=job.conversation_id,
        message_id=job.message_id,
        business_id=job.business_id,
        channel=job.channel,
    )
    try:
        ctx = await client.fetch_turn_context(job.conversation_id)
    except InternalAPIError as e:
        bound.error("agent_worker.context_fetch_failed", status=e.status_code)
        raise HTTPException(status_code=502, detail="turn-context fetch failed") from e

    if is_terminal(ctx.current_phase):
        # Laravel should not be dispatching turns on terminal conversations;
        # bail rather than silently advance.
        bound.warning(
            "agent_worker.terminal_dispatch",
            current_phase=ctx.current_phase.value,
        )
        return {"status": "skipped_terminal", "phase": ctx.current_phase.value}

    # Pre-flight cost decision.
    pre = cost_tracker.preflight(
        business_id=ctx.business.id,
        default_model=default_model(),
        ceiling_micros=ctx.business.cost_ceiling_micros,
        cheap_mode_model=ctx.business.cheap_mode_model,
        already_spent_micros=ctx.cumulative_cost_micros_today,
    )

    if pre.should_escalate:
        bound.warning(
            "agent_worker.cost_killed_preflight",
            spent_micros=pre.spent_micros,
            ceiling_micros=pre.ceiling_micros,
        )
        await client.post_escalate(
            job.conversation_id,
            EscalatePayload(
                conversation_id=job.conversation_id,
                reason="cost_ceiling_exceeded",
                detail=(
                    f"spent {pre.spent_micros} of {pre.ceiling_micros} micros; "
                    "killed pre-flight"
                ),
            ),
        )
        return {"status": "escalated", "reason": "cost_ceiling_exceeded"}

    # Prompt build.
    try:
        system_prompt = build_system_prompt(ctx.business, ctx.current_phase)
    except PromptInjectionDetected as e:
        bound.warning("agent_worker.prompt_injection_blocked", detail=str(e))
        await client.post_escalate(
            job.conversation_id,
            EscalatePayload(
                conversation_id=job.conversation_id,
                reason="scope_violation",
                detail=str(e),
            ),
        )
        return {"status": "escalated", "reason": "prompt_injection_blocked"}

    user_prompt = build_user_prompt(ctx)
    model = pre.chosen_model

    # Call + retry loop.
    parsed: AgentTurnResponse | None = None
    last_error: str = ""
    last_text: str = ""
    last_raw_tokens_in = 0
    last_raw_tokens_out = 0
    last_raw_cost_micros = 0

    for attempt in range(MAX_PARSE_RETRIES + 1):
        try:
            raw = await provider.call(
                model=model, system=system_prompt, user=user_prompt
            )
        except LLMNotConfigured as e:
            bound.error("agent_worker.llm_not_configured", detail=str(e))
            await client.post_escalate(
                job.conversation_id,
                EscalatePayload(
                    conversation_id=job.conversation_id,
                    reason="poison_message",
                    detail=str(e),
                ),
            )
            return {"status": "escalated", "reason": "llm_not_configured"}

        last_text = raw.text
        last_raw_tokens_in = raw.tokens_in
        last_raw_tokens_out = raw.tokens_out
        last_raw_cost_micros = raw.cost_micros

        try:
            parsed_json = json.loads(raw.text)
            candidate = AgentTurnResponse.model_validate(parsed_json)
            # Re-check the transition against the live state machine,
            # not just the JSON-schema enum membership.
            if candidate.next_phase not in allowed_next(ctx.current_phase):
                raise InvalidTransition(
                    current=ctx.current_phase, proposed=candidate.next_phase
                )
            parsed = candidate
            break
        except (json.JSONDecodeError, ValidationError, InvalidTransition) as e:
            last_error = str(e)
            bound.info(
                "agent_worker.parse_retry",
                attempt=attempt,
                error=last_error[:200],
            )
            # Build a focused repair prompt for the next attempt.
            user_prompt = build_repair_prompt(raw.text, last_error)
            continue

    if parsed is None:
        bound.warning(
            "agent_worker.parse_failure_exhausted",
            attempts=MAX_PARSE_RETRIES + 1,
            last_error=last_error[:200],
        )
        await client.post_escalate(
            job.conversation_id,
            EscalatePayload(
                conversation_id=job.conversation_id,
                reason="parse_failure_exhausted",
                detail=last_error[:1900],
            ),
        )
        return {"status": "escalated", "reason": "parse_failure_exhausted"}

    # Scope guard: even if the JSON validated, an out_of_scope phase means
    # we substitute the canonical fallback reply text so the LLM can't
    # leak off-script chat through the reply_text field.
    if parsed.next_phase == Phase.OUT_OF_SCOPE:
        parsed = parsed.model_copy(update={"reply_text": OUT_OF_SCOPE_FALLBACK})

    # Record cost; re-check ceiling.
    post = cost_tracker.record(
        business_id=ctx.business.id,
        cost_micros=parsed.cost_micros,
        default_model=default_model(),
        ceiling_micros=ctx.business.cost_ceiling_micros,
        cheap_mode_model=ctx.business.cheap_mode_model,
    )

    degraded = pre.mode == CostMode.NORMAL and post.mode != CostMode.NORMAL
    result = TurnResultPayload(
        conversation_id=job.conversation_id,
        next_phase=parsed.next_phase,
        reply_text=parsed.reply_text,
        intents=[Intent(name=i.name, args=i.args) for i in parsed.intents],
        confidence=parsed.confidence,
        tokens_in=parsed.tokens_in or last_raw_tokens_in,
        tokens_out=parsed.tokens_out or last_raw_tokens_out,
        cost_micros=parsed.cost_micros or last_raw_cost_micros,
        model=parsed.model or model,
        degraded_to_cheap_mode=degraded,
    )

    try:
        await client.post_turn_result(job.conversation_id, result)
    except InternalAPIError as e:
        bound.error("agent_worker.result_post_failed", status=e.status_code)
        raise HTTPException(
            status_code=502, detail="turn-result post failed"
        ) from e

    if post.mode == CostMode.KILLED:
        # Spending this turn pushed us into the kill band; escalate the
        # next-turn dispatch so Laravel can flip the kill switch.
        await client.post_escalate(
            job.conversation_id,
            EscalatePayload(
                conversation_id=job.conversation_id,
                reason="cost_ceiling_exceeded",
                detail=(
                    f"post-call: spent {post.spent_micros} of "
                    f"{post.ceiling_micros} micros"
                ),
            ),
        )

    bound.info(
        "agent_worker.turn_complete",
        next_phase=result.next_phase.value,
        model=result.model,
        cost_micros=result.cost_micros,
        cost_mode=post.mode.value,
        degraded=degraded,
    )
    return {
        "status": "ok",
        "next_phase": result.next_phase.value,
        "model": result.model,
        "cost_mode": post.mode.value,
        "degraded_to_cheap_mode": degraded,
    }


# Re-export for tests that want to inject mocked clients without going
# through HTTP.
__all__ = [
    "app",
    "run_turn",
    "MAX_PARSE_RETRIES",
]
