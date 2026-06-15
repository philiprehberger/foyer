# Why a Python sidecar?

The Foyer agent runtime is a FastAPI worker — not a Laravel job, not a
Node sidecar. This is a deliberate choice with documented tradeoffs.

## Three reasons it's Python

1. **Portfolio coverage.** Foyer is part of a portfolio that spans Laravel,
   Next.js, WordPress, and (across other demos) Go, Ruby, and Elixir. A
   Python sidecar is the cheapest way to demonstrate cross-runtime service
   contracts — HMAC'd HTTP between two languages, both observing the same
   JSON-Schema.
2. **Async LLM streaming becomes additive.** FastAPI's `StreamingResponse`
   and Anthropic / OpenAI SDKs' streaming generators compose naturally. If
   Phase 6+ wires a streaming chat widget, the worker grows a
   `POST /run-turn-stream` endpoint without re-plumbing PHP.
3. **LLM retry / backoff / circuit-breaker logic stays out of the request
   cycle.** Laravel's webhook handler returns 200 within 500 ms. The LLM
   call — which can take several seconds and may need 1-2 retries on
   parse failure — happens out-of-band in the worker. The PHP process
   never blocks on an LLM round-trip.

## What it's not

This is not a recommendation that every Laravel project running an agent
needs Python. It's the shape that fits Foyer's portfolio role. If you fork
Foyer for a single-stack PHP shop, the fallback path below works.

## Single-process fallback path

For a buyer who wants the Foyer architecture but not the Python deploy:

1. Move `run_turn` into a Laravel queue job (`App\Jobs\RunAgentTurn`).
2. Use an Anthropic or OpenAI PHP SDK from inside the job.
3. Keep the structured-output JSON Schema as a PHP validator
   (`ext-json` + a tiny `json-schema/json-schema` validator).
4. Keep the cost ceiling logic in `App\Services\CostTracker`.
5. Drop `workers/agent/`, `_internal/*` routes, and the HMAC middleware.

The downside is the long-running LLM call inside the Horizon worker. With
a 60-second job timeout and a single LLM round-trip averaging 1-3
seconds, this fits comfortably — but parse-failure retries can push to
6-10 seconds per turn, which may need a longer per-queue timeout.

## Service contract is the load-bearing piece

The Python sidecar and the single-process fallback both consume the same
`AgentTurn` job payload and produce the same `TurnResult` output. The
schemas in `infra/contracts/` are versioned and codegen'd into both
languages — that contract, not the runtime choice, is what makes this
architecture portable.

## Sentry-side note

Sentry releases are stamped per-process: `foyer-php@x.y.z` and
`foyer-agent@x.y.z` are separate releases. Errors in one don't pollute
the other's regression history.
