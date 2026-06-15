# Scope guardrails

How the agent refuses what you have not configured it to accept.
Configurable per business, validated at write time, enforced at agent
time and again at write time on every action that touches the calendar.

## The shape of a scope config

Every business has a scope object stored against its row. The fields are
flat, validated as a unit on write, read on every turn by the agent
worker.

```json
{
  "service_types": [
    {
      "key": "drain-clear",
      "label": "Drain clearing",
      "description": "Kitchen / bath / outdoor drain clogs.",
      "est_duration_min": 90,
      "requires_photos": false
    },
    {
      "key": "leak-repair",
      "label": "Leak repair",
      "description": "Visible leaks under sinks, behind walls, around fixtures.",
      "est_duration_min": 120,
      "requires_photos": true
    },
    {
      "key": "water-heater-install",
      "label": "Water heater install",
      "description": "Replacement install for existing heaters.",
      "est_duration_min": 240,
      "requires_photos": true
    }
  ],
  "service_area": {
    "kind": "zip-list",
    "zips": ["80301", "80302", "80303", "80304", "80305", "80306", "80307", "80308", "80309", "80310"]
  },
  "business_hours": {
    "mon": [["08:00", "17:00"]],
    "tue": [["08:00", "17:00"]],
    "wed": [["08:00", "17:00"]],
    "thu": [["08:00", "17:00"]],
    "fri": [["08:00", "17:00"]],
    "sat": [],
    "sun": []
  },
  "blocked_dates": ["2026-07-04", "2026-12-25"],
  "min_lead_minutes": 120,
  "max_lead_days": 21,
  "quiet_hours_start": "21:00",
  "quiet_hours_end": "08:00",
  "timezone": "America/Denver",
  "human_handoff_phone": "+13035550100",
  "human_handoff_threshold": 0.55
}
```

`service_area` supports two kinds:

```json
{ "kind": "zip-list", "zips": ["80301", "..."] }
{ "kind": "radius",   "center_lat": 40.015, "center_lng": -105.27, "radius_km": 25 }
```

## Validation at write time

`PATCH /v1/businesses/:id/scope` applies these checks before anything is
persisted, and surfaces them inline in the Filament UI:

- `service_types` non-empty; `key` unique within the catalog; durations
  positive.
- `service_area` non-empty — at least one ZIP, or a center point and a
  positive radius.
- `business_hours` non-empty across the week; intervals well-formed,
  end time after start time, no overlapping intervals on a single day.
- `min_lead_minutes` < `max_lead_days * 1440`.
- `quiet_hours_start` and `quiet_hours_end` form a valid wrapping or
  non-wrapping range; both required if either is set.
- `timezone` resolves to a real IANA zone.
- `human_handoff_threshold` between 0 and 1 inclusive; default 0.55 if
  unset. Below this confidence the agent escalates rather than
  guessing.
- `human_handoff_phone` E.164 if set.

Garbage configs cannot be saved. The owner does not get to send the
agent into the field with no business hours and a negative lead time.

Validation errors return RFC 7807 with per-field errors:

```json
{
  "type": "https://foyer.philiprehberger.com/problems/scope-validation",
  "title": "Scope config rejected",
  "status": 422,
  "errors": {
    "business_hours.mon": ["end time must be after start time"],
    "min_lead_minutes": ["must be less than max_lead_days * 1440"]
  }
}
```

## Enforcement at agent time

The agent is a state machine constrained by the scope. The phases are
documented in `docs/architecture/state-machine.md`. Two specific
enforcement points:

- **Phase enum is closed.** The LLM cannot emit a phase that is not in
  the documented enum. The parser rejects it, retry up to twice, third
  failure escalates to `human_handoff` and dead-letters the turn.
- **Out-of-scope is reachable from every non-terminal phase.** The
  guard is universal; the agent does not have a state where it cannot
  refuse.

The system prompt explicitly bounds the agent to the configured scope.
Per-business system-prompt suffixes are length-capped and run through
a prompt-injection screen before save — the owner cannot save a suffix
that says "ignore the scope" or "book anything the customer asks for."

## Enforcement at write time

The agent proposing an out-of-scope slot is one failure mode; the
agent *persisting* an out-of-scope booking is another. Foyer defends in
depth:

1. **Slot search filters before the LLM.** Business hours, blocked
   dates, lead-time bounds, quiet hours all apply at the search step
   before any candidate slot reaches the LLM. The LLM only chooses
   among slots that already satisfy the scope.
2. **Hold creation re-validates.** The proposed slot is inside
   business hours, the address is in the service area, the service
   type is in the catalog. Mismatch is a 422 with a problem document
   and the agent re-tries from `propose_slot`.
3. **Confirm endpoint re-validates one more time.** A stale hold that
   drifted out of policy (e.g. the owner changed business hours
   during the hold window) is rejected at confirm time. The customer
   is texted an apology and the agent re-engages with new alternatives.

## Quiet hours specifically

Quiet hours apply to **outbound, not inbound**. A customer can text at
midnight; the agent will queue the response for the next allowed
window. Confirmation messages obey the same rule — a 9pm confirmation
becomes an 8am text. The default 21:00 – 08:00 customer-local range is
FCC-aligned; per-business override is supported.

Outbound queueing respects quiet hours via Twilio's `ScheduledMessage`
when the next-allowed-window is more than 15 minutes away; for closer
windows the outbound worker just sleeps and re-tries at the boundary.

## The out-of-scope log

Requests that hit the fallback are persisted to `out_of_scope_log` with
the inferred reason:

- `out_of_area` — address geocoded outside service area
- `out_of_hours` — requested time outside business hours
- `service_not_offered` — requested service not in catalog
- `quiet_hours` — requested outbound time inside quiet hours (rare;
  usually the agent reschedules silently)
- `request_unparseable` — agent could not extract a service type
  after several turns
- `consent_blocked` — STOP'd number tried to start a new conversation
- `agent_low_confidence` — confidence below the handoff threshold

The Filament admin surfaces patterns as service-expansion suggestions
— if a third of refused requests are for water-heater installs, the
dashboard says so. The clustering is basic keyword grouping; the
suggestion is for the owner to think about, not for the agent to act
on.

## Kill switch and cost ceiling — the higher-order overrides

Beyond scope, two operational overrides exist. Both are documented in
the operations runbook:

- **Kill switch** — per-business read-only mode. Agent stops replying;
  inbound texts get a static fallback. STOP/START/HELP still work.
- **Cost ceiling** — per-business per-day LLM budget. At 80% the
  dashboard warns; at 100% Foyer downshifts to a cheap-mode model; at
  150% the kill switch flips.

Neither overrides the scope; they sit on top of it. Even with the kill
switch off and budget healthy, an out-of-scope request is still
refused. The layers are independent.
