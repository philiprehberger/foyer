import Link from "next/link";
import { CodeBlock } from "../../../components/CodeBlock";
import { DocsLayout } from "../../../components/DocsLayout";

export const metadata = { title: "Scope guardrails" };

export default function ScopeGuardrails() {
  return (
    <DocsLayout
      current="/docs/scope-guardrails"
      title="Scope guardrails"
      description="How the agent refuses what you have not configured it to accept. Configurable per business, validated at write time, enforced by the agent state machine and by server-side checks on every action that touches the calendar."
    >
      <h2>The shape of a scope config</h2>
      <p>
        Every business has a scope object stored against its row. The fields
        are flat, validated as a unit on write, and read on every turn by the
        agent worker.
      </p>
      <CodeBlock language="json">
{`{
  "service_types": [
    { "key": "drain-clear", "label": "Drain clearing", "est_duration_min": 90 },
    { "key": "leak-repair", "label": "Leak repair", "est_duration_min": 120 },
    { "key": "water-heater-install", "label": "Water heater install", "est_duration_min": 240 }
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
}`}
      </CodeBlock>

      <h2>Validation at write time</h2>
      <p>
        <code>PATCH /v1/businesses/:id/scope</code> applies these checks before
        anything is persisted, and surfaces them inline in the Filament UI:
      </p>
      <ul>
        <li><code>service_types</code> non-empty; keys unique; durations positive.</li>
        <li><code>service_area</code> non-empty — at least one ZIP, or a center point and a non-zero radius.</li>
        <li><code>business_hours</code> non-empty across the week; intervals well-formed, end after start.</li>
        <li><code>min_lead_minutes</code> &lt; <code>max_lead_days * 1440</code>.</li>
        <li><code>quiet_hours_start</code> and <code>quiet_hours_end</code> form a valid wrapping or non-wrapping range.</li>
        <li><code>timezone</code> resolves to a real IANA zone.</li>
        <li>
          <code>human_handoff_threshold</code> between 0 and 1; default 0.55 — below this the agent escalates rather than guessing.
        </li>
      </ul>
      <p>
        Garbage configs cannot be saved. The owner does not get to send the
        agent into the field with no business hours and a negative lead time.
      </p>

      <h2>Enforcement at agent time</h2>
      <p>
        The agent is a state machine constrained by the scope. The phases are:
      </p>
      <CodeBlock language="text">
{`greet
  → identify_service
  → collect_address
  → collect_timing
  → collect_details (optionally photos)
  → propose_slot
  → request_human_confirm
  → confirm_to_customer
  → completed

Any phase  → abandon
Any phase  → out_of_scope
Any phase  → human_handoff`}
      </CodeBlock>
      <p>
        At each turn the agent receives the scope and the prior conversation
        state. The structured output schema includes the chosen next phase. The
        agent <em>cannot</em> emit a phase that is not in the enum — parser
        rejects it, retry up to twice, third failure escalates to{" "}
        <code>human_handoff</code> and dead-letters the turn.
      </p>

      <h2>Enforcement at write time</h2>
      <p>
        The agent proposing an out-of-scope slot is one failure mode; the
        agent <em>persisting</em> an out-of-scope booking is another. Foyer
        defends in depth:
      </p>
      <ul>
        <li>
          Slot search filters by business hours, blocked dates, lead-time
          bounds, and quiet hours before any candidate goes to the LLM.
        </li>
        <li>
          The hold-creation path re-validates: the proposed slot is inside
          business hours, the address is in the service area, the service type
          is in the catalog. Mismatch is a 422 with a problem document — the
          agent re-tries from <code>propose_slot</code>.
        </li>
        <li>
          The confirm endpoint re-validates one more time before the Calendar
          lock. A stale hold that drifted out of policy is rejected.
        </li>
      </ul>

      <h2>Quiet hours specifically</h2>
      <p>
        Quiet hours apply to outbound, not inbound. A customer can text at
        midnight; the agent will queue the response for the next allowed
        window. Confirmation messages obey the same rule — a 9pm confirmation
        becomes an 8am text. The default 21:00 – 08:00 customer-local range
        is FCC-aligned; per-business override is supported.
      </p>

      <h2>The out-of-scope log</h2>
      <p>
        Requests that hit the fallback are persisted to{" "}
        <code>out_of_scope_log</code> with the inferred reason (out of area,
        out of hours, service not offered, request unparseable). The Filament
        admin surfaces patterns as service-expansion suggestions — if a third
        of refused requests are for water-heater installs, the dashboard says
        so. The clustering is basic keyword grouping; the suggestion is for the
        owner to think about, not for the agent to act on.
      </p>

      <p>
        See also{" "}
        <Link href="/docs/operations">Operations runbook</Link> for the
        kill-switch and per-business cost ceilings, which are the two
        higher-order overrides on top of scope.
      </p>
    </DocsLayout>
  );
}
