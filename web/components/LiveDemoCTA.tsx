import Link from "next/link";

export function LiveDemoCTA() {
  return (
    <section className="mx-auto max-w-5xl px-6 py-20">
      <div className="rounded-2xl border border-(--color-paper-deep) bg-(--color-paper-dim)/40 px-8 py-10 md:px-12 md:py-14">
        <p className="text-xs uppercase tracking-widest text-(--color-warm-deep) mb-3">
          Try it
        </p>
        <h2 className="text-2xl md:text-3xl font-semibold text-(--color-ink) mb-3">
          Text Anchor Plumbing&apos;s booking line to schedule.
        </h2>
        <p className="text-(--color-ink-dim) max-w-2xl mb-8 leading-relaxed">
          Anchor Plumbing serves Boulder, CO (ZIPs 80301-80310). Open Mon-Fri
          8am-5pm Mountain. Services: drain clearing, leak repair, water
          heater install. Text the number below to request service — the
          booking assistant will ask for the service type, address, and
          preferred time, then send a confirmation once the slot is held.
        </p>
        <div className="grid sm:grid-cols-2 gap-4 mb-8">
          <div className="rounded-lg border border-(--color-paper-deep) bg-white/70 p-5">
            <p className="text-xs uppercase tracking-widest text-(--color-ink-faint) mb-1">
              Text to book
            </p>
            <a
              href="sms:+19704698922"
              className="block text-2xl font-mono text-(--color-ink) tracking-tight no-underline"
            >
              (970) 469-8922
            </a>
            <p className="text-sm text-(--color-ink-dim) mt-2 leading-snug">
              Send any message describing the service you need — the
              assistant will reply to gather the details. Reply HELP for
              help, STOP to opt out at any time.
            </p>
            <p className="text-xs text-(--color-ink-faint) mt-2">
              Message frequency is conversational and reply-only (typically
              4-10 messages per booking session). Msg &amp; data rates may
              apply. By texting this number you agree to the{" "}
              <Link
                href="/terms"
                className="text-(--color-warm-deep) underline underline-offset-2"
              >
                terms
              </Link>{" "}
              and{" "}
              <Link
                href="/privacy"
                className="text-(--color-warm-deep) underline underline-offset-2"
              >
                privacy policy
              </Link>
              .
            </p>
          </div>
          <div className="rounded-lg border border-(--color-paper-deep) bg-white/70 p-5">
            <p className="text-xs uppercase tracking-widest text-(--color-ink-faint) mb-1">
              Web chat
            </p>
            <p className="text-(--color-ink) leading-snug">
              The same widget that ships to customer sites — embedded
              directly on the demo page.
            </p>
            <Link
              href="/demo"
              className="inline-block mt-3 text-sm text-(--color-warm-deep) underline underline-offset-4"
            >
              Open the demo →
            </Link>
          </div>
        </div>
      </div>
    </section>
  );
}
