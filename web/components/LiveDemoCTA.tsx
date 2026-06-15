import Link from "next/link";

export function LiveDemoCTA() {
  return (
    <section className="mx-auto max-w-5xl px-6 py-20">
      <div className="rounded-2xl border border-(--color-paper-deep) bg-(--color-paper-dim)/40 px-8 py-10 md:px-12 md:py-14">
        <p className="text-xs uppercase tracking-widest text-(--color-warm-deep) mb-3">
          Try it
        </p>
        <h2 className="text-2xl md:text-3xl font-semibold text-(--color-ink) mb-3">
          Text the demo number, or chat with Anchor Plumbing in the browser.
        </h2>
        <p className="text-(--color-ink-dim) max-w-2xl mb-8 leading-relaxed">
          Both channels run the same agent, the same scope guard, the same
          slot-hold semantics. The demo is bound to a fictional plumber in
          Boulder, CO — Mon-Fri 8am-5pm, drain-clear / leak-repair /
          water-heater-install. Out-of-area, out-of-hours, and out-of-scope
          requests all hit the fallback without committing.
        </p>
        <div className="grid sm:grid-cols-2 gap-4 mb-8">
          <div className="rounded-lg border border-(--color-paper-deep) bg-white/70 p-5">
            <p className="text-xs uppercase tracking-widest text-(--color-ink-faint) mb-1">
              SMS
            </p>
            <p className="text-2xl font-mono text-(--color-ink) tracking-tight">
              +1 (720) 555-0199
            </p>
            <p className="text-xs text-(--color-ink-dim) mt-2">
              US numbers only — international toll fraud destinations blocked.
              Three conversations per day per source number.
            </p>
          </div>
          <div className="rounded-lg border border-(--color-paper-deep) bg-white/70 p-5">
            <p className="text-xs uppercase tracking-widest text-(--color-ink-faint) mb-1">
              Web chat
            </p>
            <p className="text-(--color-ink) leading-snug">
              The same widget that ships to customer sites — embedded directly
              on the demo page.
            </p>
            <Link
              href="/demo"
              className="inline-block mt-3 text-sm text-(--color-warm-deep) underline underline-offset-4"
            >
              Open the demo →
            </Link>
          </div>
        </div>
        <p className="text-xs text-(--color-ink-faint)">
          Abuse protections — per-IP and per-number rate limits, profanity and
          prompt-injection filter, Twilio spend ceiling, hourly demo state reset.
        </p>
      </div>
    </section>
  );
}
