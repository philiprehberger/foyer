import Link from "next/link";
import { CodeBlock } from "../../../components/CodeBlock";
import { DocsLayout } from "../../../components/DocsLayout";

export const metadata = { title: "Twilio setup" };

export default function TwilioSetup() {
  return (
    <DocsLayout
      current="/docs/twilio-setup"
      title="Twilio setup"
      description="Account, number, webhook, status callback. The bits that have to be wired before any inbound message will land. 10DLC brand and campaign registration are a separate, longer-running track — see the 10DLC walkthrough."
    >
      <h2>1. The account</h2>
      <p>
        Foyer needs a Twilio account with Programmable SMS and Programmable
        Messaging enabled. The account SID and a restricted auth token go in{" "}
        <code>.env</code>:
      </p>
      <CodeBlock language="bash">
{`TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=...
TWILIO_WEBHOOK_HOST=https://api.foyer.example.com   # the URL Twilio will POST to`}
      </CodeBlock>
      <p>
        Restricted tokens with only the messaging scope are preferred over the
        primary auth token. Document the rotation cadence — six months is the
        floor Foyer assumes.
      </p>

      <h2>2. Provision a number</h2>
      <p>
        In Twilio Console, buy a number with SMS and MMS capability in a region
        that matches the business. Toll-free, long code, and short code all
        work — the choice has carrier-filtering implications but no impact on
        Foyer code paths. For 10DLC-registered long codes see the{" "}
        <Link href="/docs/twilio-10dlc">10DLC walkthrough</Link>.
      </p>

      <h2>3. Configure the inbound webhook</h2>
      <p>
        Point the number&rsquo;s inbound webhook at{" "}
        <code>POST /v1/sms/inbound</code> on the API host. Twilio will sign the
        request with <code>X-Twilio-Signature</code>; Foyer validates the
        signature on every inbound and fails closed on mismatch — never
        falls back to accepting unsigned posts.
      </p>
      <CodeBlock language="text">
{`A MESSAGE COMES IN  →  Webhook
URL                 →  https://api.foyer.example.com/v1/sms/inbound
Method              →  HTTP POST`}
      </CodeBlock>
      <p>
        The webhook is queue-fast-ack — it dedupes on{" "}
        <code>MessageSid</code> in the messages table, dispatches the agent
        turn to Redis, and returns <code>200</code> within 500ms regardless of
        LLM latency. Twilio retries on 5xx and on a response over 15 seconds;
        keeping the response tight protects delivery rates.
      </p>

      <h2>4. Configure the status callback</h2>
      <p>
        Set the messaging service&rsquo;s status callback URL to{" "}
        <code>POST /v1/twilio/status</code>. Delivery, undelivered, and failed
        callbacks land in the <code>message_deliveries</code> table; carrier
        filter (<code>30007</code>) and unknown error (<code>30008</code>)
        codes raise alerts and pause that destination number for 24 hours.
      </p>

      <h2>5. Register the number with the business</h2>
      <p>
        Map the Twilio number to the Foyer business in the admin. Foyer keys
        consent state on the <code>(customer phone, twilio number)</code>{" "}
        pair, so one Twilio number per business is the simplest topology and
        the one the tests assume.
      </p>

      <h2>6. Confirm with a real inbound</h2>
      <p>
        Text the number. The webhook should return 200 immediately; the
        outbound reply should land within seconds. If nothing comes back, the
        usual suspects:
      </p>
      <ul>
        <li>
          The signature check is failing — confirm the public host in{" "}
          <code>.env</code> exactly matches the URL Twilio is hitting,
          including trailing slash and scheme.
        </li>
        <li>
          The agent worker is not running — Horizon is up but{" "}
          <code>workers/agent</code> uvicorn is not.
        </li>
        <li>
          The destination number is STOP&rsquo;d for this business — check the{" "}
          <code>consent_state</code> table.
        </li>
        <li>
          Quiet hours are in effect — outbound is queued for the next allowed
          window rather than dropped.
        </li>
      </ul>

      <h2>7. After the smoke test</h2>
      <p>
        Move on to{" "}
        <Link href="/docs/twilio-10dlc">10DLC registration</Link>. Toll-free
        numbers and unregistered long codes work for sandbox testing, but US
        carriers will throttle and eventually block unregistered conversational
        traffic. Start the brand and campaign registration before the demo
        depends on it — turnaround is a few business days.
      </p>
    </DocsLayout>
  );
}
