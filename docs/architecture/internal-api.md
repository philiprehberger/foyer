# Internal API — Laravel ↔ FastAPI agent worker

The seam between the Laravel API and the FastAPI agent worker. Bound to
`127.0.0.1` only. Every request HMAC-signed with a shared secret. Three
endpoints, RFC 7807 problem documents on error.

This document is the authoritative contract. Both halves of the codebase
generate types from the JSON Schemas referenced below; CI gates parity.

## Where this fits

```
   Laravel API (php-fpm)              FastAPI agent worker (uvicorn)
   ┌─────────────────────┐            ┌─────────────────────────┐
   │ ingest, enqueue,    │   Redis    │ consume AgentTurn jobs  │
   │ Filament admin,     │ ◀────────▶ │ build LLM prompt        │
   │ Twilio outbound     │            │ call LLM, validate JSON │
   │ Postgres writes     │ ◀──────────│ HTTP back to            │
   │ (sole authority)    │  HMAC      │ 127.0.0.1:80/_internal  │
   └─────────────────────┘  loopback  └─────────────────────────┘
```

The agent worker never opens a Postgres connection. Anything it needs to
read about a conversation it pulls via `turn-context`; anything it needs
to write it posts via `turn-result` or `escalate`. The Laravel side owns
the schema, the migrations, the writes, and the queue producers.

## Authentication

Every request carries two things:

- A source IP that resolves to `127.0.0.1` or `::1`. Enforced by the
  `internal.loopback` middleware; 403 otherwise.
- An `X-Foyer-Internal-Sig` header equal to
  `hmac_sha256(FOYER_INTERNAL_SECRET, raw_request_body)`. Enforced by
  the `internal.hmac` middleware with constant-time comparison; 403
  otherwise.

`FOYER_INTERNAL_SECRET` is the same value on both sides, set in `.env`
on Laravel and as an environment variable on the supervised FastAPI
worker. Rotate by deploying the same new value to both halves in the same
release.

The Apache vhost on the API host does not proxy `/_internal` — Laravel's
router serves it, and a misconfiguration that exposed the route to the
public internet would still hit the loopback check and 403. Defence in
depth.

## Endpoints

| Method | Path                                                    | Used by                                                                |
| ------ | ------------------------------------------------------- | ---------------------------------------------------------------------- |
| GET    | `/_internal/conversations/{id}/turn-context`            | FastAPI loads last N messages, scope, persona, current phase.          |
| POST   | `/_internal/conversations/{id}/turn-result`             | FastAPI posts LLM output: next phase, reply text, intents, tokens, cost. |
| POST   | `/_internal/conversations/{id}/escalate`                | FastAPI gives up after structured-output retries; switch to human-handoff. |

### GET `/_internal/conversations/{id}/turn-context`

Loads the context the agent worker needs to build a prompt. Read-only on
the Laravel side; the response is a JSON envelope:

```json
{
  "schema_version": 1,
  "conversation": {
    "id": "01J4YQX9MA0RBPV6N7K8WJ6XYZ",
    "business_id": "01J4YQX9MA0RBPV6N7K8WJ6ANCH",
    "channel": "sms",
    "current_phase": "collect_address",
    "state": { "service_type": "drain-clear" }
  },
  "business": {
    "name": "Anchor Plumbing",
    "timezone": "America/Denver",
    "persona": "professional",
    "system_prompt_suffix": "",
    "scope": { /* same shape as docs/safety/scope-guardrails.md */ }
  },
  "messages": [
    {
      "id": "01J4YQX9MA0RBPV6N7K8WJ6M01",
      "role": "customer",
      "text": "hi can you clear my kitchen drain",
      "attachments": [],
      "created_at": "2026-06-15T15:46:02Z"
    }
  ]
}
```

The `messages` array is bounded — the last 30 messages or the last hour,
whichever is smaller. The agent worker does not get an unbounded
transcript; if it needs more it asks via a separate (future) endpoint.

### POST `/_internal/conversations/{id}/turn-result`

The agent worker posts the LLM output. Validated against
`infra/contracts/agent-turn-response.schema.json` on receive.

```json
{
  "schema_version": 1,
  "message_id": "01J4YQX9MA0RBPV6N7K8WJ6M01",
  "next_phase": "collect_address",
  "reply_text": "Got it. What's the service address?",
  "intents": [
    { "kind": "ack_service", "service_type_key": "drain-clear" }
  ],
  "model": "claude-3-5-sonnet-20241022",
  "tokens_in": 612,
  "tokens_out": 38,
  "cost_micros": 1840
}
```

Laravel persists the agent message, updates the conversation phase via
the derived rule (the phase is `messages.phase` of the last message, not
a column on `conversations`), and enqueues the outbound message via the
Twilio outbound queue if `channel='sms'`, or fans it out to the SSE
stream if `channel='web'`.

Idempotent on `message_id` — a worker that posts the same result twice
(e.g. after a network blip and a retry) does not produce two outbound
messages. The duplicate insert is rejected by the
`messages.external_id` unique index and the response is `200 OK` with
the original row.

### POST `/_internal/conversations/{id}/escalate`

The worker signals a poison-message — structured output failed three
times in a row, or some other unrecoverable LLM problem. Laravel flips
the conversation to `human_handoff`, sends a static fallback to the
customer (quiet-hours-respecting), and notifies the owner via the
configured `human_handoff_phone`.

```json
{
  "schema_version": 1,
  "message_id": "01J4YQX9MA0RBPV6N7K8WJ6M01",
  "reason": "structured_output_retry_exhausted",
  "diagnostic": "Parser rejected output on three consecutive attempts."
}
```

The `reason` field is one of a fixed enum:
`structured_output_retry_exhausted`, `cost_ceiling_exceeded`,
`prompt_injection_detected`, `internal_error`.

## Error responses

RFC 7807 problem documents:

```json
{
  "type": "https://foyer.philiprehberger.com/problems/invalid-signature",
  "title": "Invalid internal signature",
  "status": 403,
  "detail": "X-Foyer-Internal-Sig header did not match the computed signature."
}
```

| Status | Type slug                  | When                                                      |
| ------ | -------------------------- | --------------------------------------------------------- |
| 403    | `invalid-signature`        | HMAC mismatch.                                            |
| 403    | `non-loopback-origin`      | Request did not originate from `127.0.0.1`/`::1`.         |
| 404    | `conversation-not-found`   | Conversation ID is unknown or belongs to a different schema. |
| 409    | `phase-transition-invalid` | `next_phase` is not allowed from the conversation's current phase. |
| 422    | `schema-validation-failed` | Body did not match the JSON Schema; per-field errors in `errors`. |
| 429    | `cost-ceiling-exceeded`    | Per-business budget hit; the worker should stop. |
| 500    | `internal-error`           | Unhandled Laravel exception.                             |

The agent worker treats any 4xx as terminal — do not retry on signature
mismatch, the secret is misconfigured; do not retry on
`schema-validation-failed`, the worker's output schema needs to be
updated. 5xx is retryable with exponential backoff up to two times,
after which the turn dead-letters.

## Schemas

The job and response shapes are versioned with `schema_version` and
described as JSON Schema draft 2020-12 in:

- `infra/contracts/agent-turn.schema.json` — the AgentTurn job that
  Laravel enqueues for the worker to consume.
- `infra/contracts/agent-turn-response.schema.json` — the payload the
  worker posts to `turn-result`.

PHP and Python both code-generate from these schemas; a CI step runs the
generators and gates parity. A drift fails the build.

## Loopback binding — implementation note

In Laravel the route group is:

```php
Route::prefix('_internal')
    ->middleware(['internal.loopback', 'internal.hmac'])
    ->group(function () {
        // …
    });
```

`internal.loopback` reads `$request->ip()` and aborts with 403 unless
the value is in `['127.0.0.1', '::1']`. It does *not* trust
`X-Forwarded-For` for this check — even if the Apache vhost is
misconfigured to forward, the source IP from PHP's perspective is the
loopback proxy, not the upstream. (For non-internal routes Foyer does
trust `X-Forwarded-For` via the trusted proxies config — the internal
routes are an explicit exception.)

`internal.hmac` reads the raw body via `$request->getContent()`,
computes the HMAC, and `hash_equals`-compares against the header. On
mismatch it returns 403 with the problem document above. The check
runs *before* request body parsing — middleware order matters; do not
rearrange this group.

## Clock skew

There is no nonce and no timestamp window. The HMAC covers the body
exactly; replays of the same body produce the same signature and would
be caught by the `messages.external_id` unique index for
`turn-result`. The trade-off is intentional: the seam is loopback-only,
the secret is shared in `.env`, and the body is idempotency-keyed at
the persistence layer. Adding a timestamp window would catch nothing
the existing layers do not already catch.

## Versioning

`schema_version` is the only versioning marker. The current value is
`1`. A breaking change to the job or the response shape bumps to `2`,
both sides reject anything they do not understand, and the producer +
consumer ship in lockstep (the deploy script does both halves in one
release window).
