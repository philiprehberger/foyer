import Link from "next/link";
import { CodeBlock } from "../../../components/CodeBlock";
import { DocsLayout } from "../../../components/DocsLayout";

export const metadata = { title: "Quickstart" };

export default function Quickstart() {
  return (
    <DocsLayout
      current="/docs/quickstart"
      title="Quickstart"
      description="The half-hour path — clone, configure a business, point Twilio and Google Calendar at it, send a test SMS, watch the agent reply. Assumes Postgres 16, PHP 8.3, Python 3.12, Node 22 already in place."
    >
      <h2>1. Clone and install</h2>
      <CodeBlock language="bash">
{`git clone https://github.com/philiprehberger/foyer.git
cd foyer
composer install
npm --prefix web install
npm --prefix widget install
python -m venv workers/agent/.venv
workers/agent/.venv/bin/pip install -r workers/agent/requirements.txt`}
      </CodeBlock>
      <p>
        The PHP half is the API plus Filament admin plus queue workers. The
        Next.js half is this docs site plus the live demo. The Python half is
        the agent worker — it never opens a Postgres connection, only talks to
        the loopback-bound internal API.
      </p>

      <h2>2. Postgres schema</h2>
      <p>
        Foyer needs Postgres 16 with the <code>btree_gist</code> extension —
        the slot-hold and bookings exclusion constraints are the load-bearing
        guard against double-booking.
      </p>
      <CodeBlock language="bash">
{`createuser foyer --pwprompt
createdb foyer --owner=foyer
psql -d foyer -c "CREATE EXTENSION IF NOT EXISTS btree_gist;"
cp .env.example .env
# Edit .env: DB_*, REDIS_*, FOYER_INTERNAL_SECRET, TWILIO_*, GOOGLE_*
php artisan migrate`}
      </CodeBlock>

      <h2>3. Create a business</h2>
      <p>
        A business is the tenant boundary — services, hours, area, quiet hours,
        the Twilio number, the Calendar. The seeder loads the Anchor Plumbing
        fixture under <code>fixtures/anchor-plumbing/config.json</code>.
      </p>
      <CodeBlock language="bash">
{`php artisan db:seed --class=AnchorPlumbingSeeder
# Or create via the admin:
php artisan serve
# visit http://localhost:8000/admin and create a user`}
      </CodeBlock>

      <h2>4. Wire Twilio and Calendar</h2>
      <p>
        The setup pages walk both of these end to end —{" "}
        <Link href="/docs/twilio-setup">Twilio</Link> for the inbound webhook
        and status callback,{" "}
        <Link href="/docs/twilio-10dlc">10DLC</Link> for brand and campaign
        registration, and{" "}
        <Link href="/docs/google-calendar">Google Calendar</Link> for the
        OAuth flow scoped to <code>calendar.events</code>.
      </p>

      <h2>5. Start the processes</h2>
      <p>Four things run in development:</p>
      <CodeBlock language="bash">
{`# Terminal 1 — API + admin
php artisan serve

# Terminal 2 — Horizon (queues: agent, twilio-outbound, slot-cleanup, calendar-sync)
php artisan horizon

# Terminal 3 — FastAPI agent worker
workers/agent/.venv/bin/uvicorn workers.agent.main:app --port 9101

# Terminal 4 — Docs + demo (optional in dev)
npm --prefix web run dev`}
      </CodeBlock>

      <h2>6. Text the number</h2>
      <p>
        With Twilio pointed at <code>POST /v1/sms/inbound</code> and ngrok or
        Cloudflare Tunnel exposing the local API, text the business&rsquo;s
        Twilio number. The webhook fast-acks within 500ms, an{" "}
        <code>AgentTurn</code> job lands on Redis, the FastAPI worker picks it
        up, calls the LLM with the configured scope, and posts the result back
        through the loopback-bound HMAC-protected internal API. Within seconds
        you should get a reply.
      </p>

      <h2>What to read next</h2>
      <ul>
        <li>
          <Link href="/docs/scope-guardrails">Scope guardrails</Link> — what
          the agent will and will not commit to.
        </li>
        <li>
          <Link href="/docs/internal-api">Internal API</Link> — the
          loopback-bound HMAC seam between Laravel and FastAPI.
        </li>
        <li>
          <Link href="/docs/operations">Operations runbook</Link> — kill
          switch, calendar drift, cost ceilings, backup and restore.
        </li>
      </ul>
    </DocsLayout>
  );
}
