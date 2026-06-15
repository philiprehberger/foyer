# Service contracts

Three processes, one Postgres. Who owns what.

## Process boundaries

| Process | Owns | Reads via | Writes via |
|---|---|---|---|
| **Laravel (php-fpm)** | DB schema, migrations, owner auth (Sanctum), queue jobs, Filament UI | Postgres (direct) | Postgres (direct) |
| **Horizon workers** | SMS dispatch, slot-cleanup, calendar-sync, photo-sanitize | Postgres (via Eloquent) | Postgres (via Eloquent), Twilio (HTTP), Google Calendar (HTTP), S3 (HTTP) |
| **FastAPI agent worker** | LLM orchestration, structured-output validation, cost ceiling | `_internal/*` (HTTP, HMAC) | `_internal/*` (HTTP, HMAC) — never Postgres |

## Queue topology

| Queue | Producer | Consumer | Job |
|---|---|---|---|
| `agent` | Laravel inbound webhook | Horizon (PHP) → posts to worker | `DispatchAgentTurn` |
| `twilio-outbound` | Agent's turn-result + booking actions | Horizon (PHP) | `SendOutboundSms` |
| `calendar-sync` | Google push webhook + 5-min poll | Horizon (PHP) | `ReconcileCalendarDrift` |
| `photo-sanitize` | Inbound MMS / widget upload | Horizon (PHP) | `SanitizePhoto` (Phase 7) |
| `default` | misc | Horizon (PHP) | misc |

## Authority

- **DB schema:** Laravel migrations only. The FastAPI worker never runs
  migrations and never opens a Postgres connection.
- **HMAC secret:** lives in both processes' env; rotation requires
  stopping both, replacing, restarting.
- **Twilio auth token:** Laravel only — the worker never calls Twilio.
- **Google OAuth tokens:** Laravel only — the worker never calls Google
  Calendar or Geocoding. (The worker can request the *outcome* of a slot
  search via the `turn-context` endpoint, but the search runs in the
  Laravel-side `SlotSearchService`.)
- **LLM API keys:** FastAPI worker only — Laravel never calls
  Anthropic / OpenAI directly.

## What this buys us

- A single process boundary to audit for credential leakage (no LLM key
  on the PHP side, no Twilio token on the Python side).
- Failures in one process don't degrade the other — the inbound webhook
  returns 200 even if the agent worker is down (the agent job just sits
  in the queue and retries).
- One DB connection pool, one migration story, one source of truth.

## When this breaks

- If you find Laravel code that calls Anthropic directly — that's wrong;
  move it into the worker.
- If you find Python code that opens a Postgres connection — that's
  wrong; route it through the `_internal/*` API.
- If you find owner-facing HTTP endpoints under `_internal/*` — that's
  wrong; the internal API is for the worker only, never the public.
