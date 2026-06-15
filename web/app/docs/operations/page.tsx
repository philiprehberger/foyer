import { CodeBlock } from "../../../components/CodeBlock";
import { DocsLayout } from "../../../components/DocsLayout";

export const metadata = { title: "Operations runbook" };

export default function Operations() {
  return (
    <DocsLayout
      current="/docs/operations"
      title="Operations runbook"
      description="The handful of things that go wrong in a booking agent, and what to do about each. Kill switch, calendar drift, cost ceiling, backup and restore. Most pages here translate to a Filament admin toggle or a single shell command."
    >
      <h2>Kill switch</h2>
      <p>
        Every business has a per-tenant kill switch. Toggling it via{" "}
        <code>POST /v1/businesses/:id/kill-switch</code> or the Filament admin
        puts the tenant into read-only mode:
      </p>
      <ul>
        <li>The agent stops replying.</li>
        <li>
          Inbound texts get a static message — &ldquo;we are temporarily
          handling this manually; someone will reach out shortly&rdquo; —
          configurable per business.
        </li>
        <li>
          STOP/START/HELP still work; they are caught before the kill-switch
          check.
        </li>
        <li>
          Pending slot holds and bookings are preserved; nothing is canceled.
        </li>
      </ul>
      <p>
        Use it when: a misconfigured persona is producing bad replies, the
        owner is unreachable for the day, or you need to drain the queue
        without committing anything new.
      </p>

      <h2>Calendar drift</h2>
      <p>
        The push channel (<code>events.watch</code>) notifies Foyer when a
        managed event is moved or deleted out-of-band. If the channel expires
        or a push is missed, the five-minute fallback poll catches the gap.
        The owner dashboard surfaces a sync-health indicator if drift exceeds
        threshold.
      </p>
      <p>
        Manual reconciliation:
      </p>
      <CodeBlock language="bash">
{`php artisan foyer:calendar-reconcile --business=01J4YQX9MA0RBPV6N7K8WJ6XYZ`}
      </CodeBlock>
      <p>
        The reconcile job is idempotent — running it twice in a row produces
        the same result as running it once.
      </p>

      <h2>Cost ceiling</h2>
      <p>
        Each business has a per-day LLM spend budget. Today&rsquo;s spend is
        rolled into <code>llm_cost_daily</code> on every turn-result write.
        When the budget hits 80%, an alert fires; at 100%, Foyer downshifts to
        the cheap-mode model (Haiku / GPT-4o-mini). If the cheap-mode spend
        also exceeds the budget, the kill switch flips for that business and
        the owner is notified.
      </p>
      <CodeBlock language="bash">
{`# Inspect today's spend
php artisan foyer:cost-report --business=... --date=today

# Reset a stuck budget
php artisan foyer:cost-reset --business=...`}
      </CodeBlock>

      <h2>Twilio outbound failures</h2>
      <p>
        The <code>message_deliveries</code> table records every status
        callback. The two codes that matter:
      </p>
      <ul>
        <li>
          <code>30007</code> — carrier filtered. The destination network
          rejected the message; usually a content / pattern flag.
        </li>
        <li>
          <code>30008</code> — unknown error. Often a transient issue but
          treated as a sign to pause that destination.
        </li>
      </ul>
      <p>
        Both raise an alert and pause that destination number for 24 hours
        — no further outbound. If the pattern repeats across destinations,
        check the campaign for content drift since registration.
      </p>

      <h2>Backup and restore</h2>
      <p>
        Postgres nightly dumps to the S3 backup bucket (same bucket the other
        portfolio projects use). Retention is 30 days. The cron is in{" "}
        <code>scripts/backup.sh</code> and runs at 03:00 server-local under
        cron.
      </p>
      <CodeBlock language="bash">
{`# List backups
aws s3 ls s3://philiprehberger-backups/foyer/

# Restore the most recent
./scripts/restore.sh --from-latest

# Restore a specific date
./scripts/restore.sh --from=2026-06-12`}
      </CodeBlock>
      <p>
        The restore script restores into a separate database name, runs the
        sanity checks (row counts per table, last booking date, last
        conversation date), and swaps the connection only after the operator
        confirms. It is not silently destructive.
      </p>

      <h2>Slot-hold backlog</h2>
      <p>
        The <code>slot-cleanup</code> worker runs every 60 seconds and expires
        stale holds. If it falls behind — Horizon dashboard shows queue depth
        climbing — the customer-side effect is that the &ldquo;hold expired&rdquo;
        text is delayed. The fix is normally Horizon worker capacity, not a
        Foyer code change. The Horizon{" "}
        <code>slot-cleanup</code> pool has <code>processes</code> set to 1 by
        default; bump to 2 if depth stays high.
      </p>

      <h2>Health endpoints</h2>
      <CodeBlock language="bash">
{`curl https://api.foyer.example.com/v1/healthz
# {"healthy":true,"db":"ok","redis":"ok","agent_worker":"ok"}`}
      </CodeBlock>
      <p>
        BetterStack polls this every 30 seconds; a sustained 5xx pages the
        on-call.
      </p>

      <h2>When in doubt</h2>
      <p>
        Toggle the kill switch first, then diagnose. A wrong reply that goes
        out is a customer-trust failure; a temporary silence with a static
        fallback is recoverable.
      </p>
    </DocsLayout>
  );
}
