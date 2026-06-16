import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Terms of service — Foyer",
  description:
    "Terms of service for the Foyer booking-agent demo at foyer.philiprehberger.com.",
};

export default function TermsPage() {
  return (
    <main className="mx-auto max-w-3xl px-6 py-16">
      <h1 className="text-3xl md:text-4xl font-semibold tracking-tight text-(--color-ink) mb-2">
        Terms of service
      </h1>
      <p className="text-sm text-(--color-ink-faint) mb-10">
        Last updated: 15 June 2026
      </p>

      <section className="prose">
        <h2>What Foyer is</h2>
        <p>
          Foyer is a public-facing portfolio demonstration by Philip Rehberger
          of a conversational SMS + web booking agent. It is not a production
          booking service. The demo brand (&quot;Anchor Plumbing&quot;) is
          fictional and no actual plumbing service will be dispatched in
          response to a conversation with the demo.
        </p>
        <p>
          By texting the published demo phone number or using the embedded web
          chat at foyer.philiprehberger.com, you agree to these terms.
        </p>

        <h2>Eligibility</h2>
        <p>
          You must be at least 18 years old and located in the United States to
          use the demo. The demo is restricted to US mobile numbers; messages
          from international numbers will not be accepted.
        </p>

        <h2>SMS program terms</h2>
        <ul>
          <li>
            <strong>Opt-in.</strong> By sending the first SMS message to the
            demo phone number, you consent to receive automated reply messages
            from the demo for the duration of that booking session.
          </li>
          <li>
            <strong>Opt-out.</strong> Reply <strong>STOP</strong> at any time
            to opt out. You will receive a single acknowledgement message and
            no further SMS until you reply <strong>START</strong> to resume.
          </li>
          <li>
            <strong>Help.</strong> Reply <strong>HELP</strong> for a brief
            help message and contact information.
          </li>
          <li>
            <strong>Message frequency.</strong> Frequency is conversational and
            reply-only — typically 4-10 messages per booking session. The demo
            does not send unsolicited messages and does not run marketing or
            promotional campaigns.
          </li>
          <li>
            <strong>Rates.</strong> Message and data rates may apply based on
            your mobile plan. Foyer does not charge any fee for the demo
            itself.
          </li>
          <li>
            <strong>Supported carriers.</strong> The demo works on most US
            mobile carriers. Delivery is not guaranteed; carrier filtering,
            registration status, or network issues may prevent message
            delivery.
          </li>
        </ul>

        <h2>Acceptable use</h2>
        <p>You agree not to:</p>
        <ul>
          <li>
            Use the demo for any unlawful purpose or in any way that could harm
            the service or its users.
          </li>
          <li>
            Attempt to overload, scan, or otherwise probe the service in a
            manner inconsistent with normal conversational use.
          </li>
          <li>
            Submit content that is harassing, hateful, sexually explicit, or
            otherwise inappropriate.
          </li>
          <li>
            Provide real-world personal information about anyone other than
            yourself.
          </li>
        </ul>
        <p>
          Foyer reserves the right to block any phone number or IP address that
          violates these terms. The public demo enforces additional abuse
          protections: per-source-number daily conversation limits and
          per-IP rate limits on the embedded web chat.
        </p>

        <h2>No real bookings</h2>
        <p>
          The demo does not result in a real service appointment. Conversation
          state is reset hourly and the demo&apos;s simulated business owner
          will not respond to confirmations made through the demo. Do not rely
          on the demo for actual scheduling.
        </p>

        <h2>Disclaimer</h2>
        <p>
          The demo is provided &quot;as is&quot;, without warranty of any kind,
          either express or implied. Foyer does not warrant that the demo will
          be uninterrupted, error-free, or secure, or that any defects will be
          corrected.
        </p>

        <h2>Limitation of liability</h2>
        <p>
          To the maximum extent permitted by law, Foyer and Philip Rehberger
          will not be liable for any indirect, incidental, special,
          consequential, or punitive damages arising from your use of, or
          inability to use, the demo.
        </p>

        <h2>Privacy</h2>
        <p>
          Use of the demo is also governed by the{" "}
          <a
            href="/privacy"
            className="text-(--color-warm-deep) underline"
          >
            privacy policy
          </a>
          .
        </p>

        <h2>Changes</h2>
        <p>
          Foyer may update these terms as the demo evolves. The &quot;Last
          updated&quot; date at the top indicates when the most recent change
          took effect. Continued use after a change constitutes acceptance of
          the revised terms.
        </p>

        <h2>Contact</h2>
        <p>
          Questions about these terms can be sent to{" "}
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
