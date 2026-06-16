import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Privacy policy — Foyer",
  description:
    "Privacy policy for the Foyer booking-agent demo at foyer.philiprehberger.com.",
};

export default function PrivacyPage() {
  return (
    <main className="mx-auto max-w-3xl px-6 py-16">
      <h1 className="text-3xl md:text-4xl font-semibold tracking-tight text-(--color-ink) mb-2">
        Privacy policy
      </h1>
      <p className="text-sm text-(--color-ink-faint) mb-10">
        Last updated: 15 June 2026
      </p>

      <section className="prose">
        <h2>What this covers</h2>
        <p>
          This policy describes how the Foyer booking-agent demo operated by
          Philip Rehberger (referred to here as &quot;Foyer&quot;) collects,
          uses, and retains information sent to it via SMS or the embedded web
          chat at foyer.philiprehberger.com.
        </p>

        <h2>What we collect</h2>
        <p>
          When you text the demo phone number or use the embedded web chat,
          Foyer collects:
        </p>
        <ul>
          <li>Your mobile phone number, in E.164 format.</li>
          <li>
            The full text of messages you send to the number and any photos you
            attach (MMS).
          </li>
          <li>
            Service-request details you provide during the conversation —
            service type, address, preferred timing.
          </li>
          <li>
            Twilio delivery metadata (message SIDs, delivery status, carrier
            error codes) returned by the carrier.
          </li>
        </ul>

        <h2>How we use it</h2>
        <p>
          The information above is used only to (a) run the demo conversation,
          (b) hold a tentative appointment slot, (c) present the request to the
          demo&apos;s simulated business owner for confirmation, and (d) send
          you a confirmation or cancellation reply. The demo does not result in
          a real service appointment — it is a portfolio piece.
        </p>

        <h2>What we do not do</h2>
        <p>
          <strong>
            We do not sell, rent, share, or otherwise disclose your mobile
            number or opt-in information to any third party for marketing or
            promotional purposes.
          </strong>{" "}
          Mobile numbers and SMS opt-in data are never made available to
          unaffiliated parties.
        </p>
        <p>
          We do not use your phone number to send promotional or marketing
          messages. The demo only replies inside the booking conversation you
          initiated.
        </p>

        <h2>Message frequency and rates</h2>
        <p>
          Message frequency is conversational and reply-only. A typical booking
          session runs 4-10 messages. Foyer does not send unsolicited messages
          and does not send broadcast or scheduled SMS campaigns.
        </p>
        <p>
          <strong>Message and data rates may apply.</strong> Standard carrier
          rates for SMS and MMS apply based on your mobile plan.
        </p>

        <h2>Opt-out</h2>
        <p>
          Reply <strong>STOP</strong> at any time to opt out. You will receive a
          single confirmation message acknowledging the opt-out and will not
          receive further SMS from the Foyer demo number. The opt-out state is
          recorded per (mobile number, business number) pair and is permanent
          until you reply <strong>START</strong> to resume.
        </p>
        <p>
          Reply <strong>HELP</strong> for a brief help message and contact
          information.
        </p>

        <h2>Retention</h2>
        <p>
          Conversation messages and attachments are retained for up to 18
          months in operational storage, after which the message body is
          anonymized (phone numbers are hashed, message text is scrubbed) and
          attachments are deleted. Opt-out records are retained indefinitely as
          required by TCPA for dispute defense.
        </p>
        <p>
          The public Anchor Plumbing demo state is reset hourly — any data
          older than one hour is purged. Audit-log records of opt-in and
          opt-out events are kept on a separate longer retention schedule for
          compliance purposes.
        </p>

        <h2>Security</h2>
        <p>
          All traffic between your device, Twilio, and Foyer&apos;s servers is
          encrypted in transit. Foyer&apos;s database is hosted in a private
          subnet on Amazon Web Services and is not exposed to the public
          internet. Photos uploaded via MMS are re-encoded, EXIF metadata is
          stripped, and the files are stored under random keys with private
          ACLs.
        </p>

        <h2>Children</h2>
        <p>
          The demo is not directed at and is not intended for use by anyone
          under the age of 18. Do not text the demo number if you are under 18.
        </p>

        <h2>Changes</h2>
        <p>
          We may update this policy as the demo&apos;s scope evolves. The
          &quot;Last updated&quot; date at the top of this page indicates when
          the most recent change took effect.
        </p>

        <h2>Contact</h2>
        <p>
          Questions about this policy or about data collected from a
          conversation with the demo can be sent to{" "}
          <a
            href="mailto:admin@philiprehberger.com"
            className="text-(--color-warm-deep) underline"
          >
            admin@philiprehberger.com
          </a>
          .
        </p>
      </section>
    </main>
  );
}
