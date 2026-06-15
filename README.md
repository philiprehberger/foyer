# Foyer

> Conversational booking agent for local-service businesses. SMS + web chat,
> 15-minute slot hold, DB-enforced no-overlap, human-confirm gate before any
> calendar lock.

[![Status](https://img.shields.io/badge/status-production--shaped%20portfolio%20demo-blue)](#status)
[![Live](https://img.shields.io/badge/live-foyer.philiprehberger.com-success)](https://foyer.philiprehberger.com)
[![PHP](https://img.shields.io/badge/php-8.3-777BB4)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/laravel-13-FF2D20)](https://laravel.com/)
[![Postgres](https://img.shields.io/badge/postgres-16-336791)](https://www.postgresql.org/)
[![FastAPI](https://img.shields.io/badge/fastapi-0.115-009688)](https://fastapi.tiangolo.com/)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## What it is

A buyer's Upwork listing reads "AI booking bot for my service business." The
demos on the market for that listing are uniformly bad — either no-code
chatbots that don't touch the calendar, or auto-bookers that turn the shop
into a ghost-appointment factory.

Foyer is the honest middle. It runs an intake conversation over SMS or an
embedded web widget, holds a tentative slot for 15 minutes against a Postgres
exclusion constraint, validates address and service area, and asks the shop
owner for one-click confirmation before locking the slot in Google Calendar.

## What ships

- **Two channels, one conversation.** SMS via Twilio (with MMS photo support)
  and a Preact widget (<= 30 KB gz, Shadow-DOM isolated). Cross-channel
  resume requires OTP — phone-number-match alone is a spoofing vector.
- **15-minute slot hold with DB-enforced no-overlap.** Postgres
  `EXCLUDE USING gist` constraint covers `bookings.status IN ('pending',
  'confirmed')` and `slot_holds.status='active'`. Two concurrent customers
  for the same slot → second insert fails, agent re-searches.
- **Human-in-the-loop confirm.** Owner sees pending bookings in a Filament
  inbox with transcript, address (with map preview), photos, proposed slot.
  One click confirms; `POST /v1/bookings/:id/confirm` requires an
  `Idempotency-Key` header — double-clicks collapse to one Calendar update +
  one SMS.
- **Scope guardrails are configurable but enforced.** Service types, service
  area, business hours, blocked dates, lead-time bounds, quiet hours
  (FCC-aligned 21:00–08:00 default), human-handoff threshold, kill switch.
  Validated server-side; garbage configs cannot be saved.
- **Calendar drift detection.** Google Calendar `events.watch` push channel
  per linked calendar; 5-minute fallback poll if the watch expires.
- **STOP / START / HELP win, no override.** Consent state keyed on
  `(customer, twilio_number)` pair. STOP'd numbers cannot resume via web
  either — widget returns `consent_blocked`.

## Architecture

Three processes, one Postgres:

- **Laravel 13** — API, Filament admin, sole DB writer, sole queue
  producer. Auth via Sanctum + `BusinessPolicy`.
- **FastAPI agent worker** — LLM state-machine orchestrator. Reads turn
  context and posts results to a 127.0.0.1-bound internal API guarded by
  an HMAC shared secret. Never opens a Postgres connection directly.
- **Horizon queue workers** — Twilio outbound, slot-hold cleanup,
  calendar-sync drift reconciliation, photo sanitization.

Webhook ingestion is queue-fast-ack: validate, dedupe on `external_id`,
dispatch `AgentTurn` to Redis, return 200 within 500 ms. The LLM work runs
out-of-band.

```
SMS / web   ->  Laravel /v1/{sms,web}/inbound (fast-ack)  ->  AgentTurn job
                                v                                 v
                            Postgres                       FastAPI worker
                                ^                                 v
                                +---- HMAC'd internal API --------+
```

## Repo layout

```
/                Laravel root
  app/          Models, controllers, policies, Filament resources
  config/       Laravel config
  database/     Migrations (Postgres 16 with btree_gist)
  routes/       api.php (/v1/*), web.php (Filament)
  tests/        PHPUnit
  openapi/      Hand-authored OpenAPI 3.1 spec
  workers/agent/  FastAPI agent worker (Python 3.12)
  connectors/   Twilio, Google Calendar, Google Geocoding modules
  sdks/         TypeScript + PHP SDKs (embed helper, verify-webhook helper)
  widget/       Preact + Shadow-DOM chat widget (<= 30 KB gz CI gate)
  web/          Next.js 16 docs + marketing + live demo
  infra/        Apache vhosts, supervisord conf, JSON-Schema contracts
  docs/         Architecture, agent state machine, runbooks
  scripts/      Deploy (atomic release for Laravel; rsync for web/)
  e2e/          Playwright + load tests
  fixtures/     Demo business config (anchor-plumbing)
```

## Status

Phase 0-5 shipped:

- Phase 0 — Foundations: auth, policy, idempotency middleware, internal-API
  HMAC, Sentry, cross-tenant authz suite.
- Phase 1 — Scaffold + Postgres schema with EXCLUDE constraints + FastAPI
  worker + Preact widget + Next.js docs.
- Phase 2 — Twilio inbound webhook + STOP/START/HELP + quiet hours +
  outbound queue worker.
- Phase 3 — Agent state machine + structured-output retry + DLQ + personas
  + per-business cost ceiling.
- Phase 4 — Google Calendar + Geocoding + slot integrity (EXCLUDE
  exercised under concurrency).
- Phase 5 — Filament owner inbox + idempotent confirm + 15-minute hold
  expiry cron + kill switch.

Phases 6-10 (web chat widget OTP, photo pipeline, SDKs, live demo, load
tests) are in flight. See [`.scratch/plans/foyer_booking_agent_portfolio.md`](
https://github.com/philiprehberger/income-ops/blob/main/.scratch/plans/foyer_booking_agent_portfolio.md)
in the income-ops workspace for the running checklist.

## License

MIT.
