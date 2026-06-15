import Link from "next/link";

export function Hero() {
  return (
    <section className="mx-auto max-w-5xl px-6 pt-20 pb-16">
      <p className="text-xs uppercase tracking-widest text-(--color-warm-deep) mb-4">
        SMS plus web chat. Postgres-enforced no-overlap. Owner confirms before the lock.
      </p>
      <h1 className="text-4xl sm:text-5xl md:text-6xl font-semibold tracking-tight leading-[1.05] mb-6 max-w-3xl text-(--color-ink)">
        A booking agent that holds the slot for fifteen minutes — and asks before it commits.
      </h1>
      <p className="text-lg text-(--color-ink-dim) leading-relaxed max-w-2xl mb-10">
        Foyer runs an intake conversation over SMS or an embedded web widget,
        validates the address and service area, proposes a slot inside business
        hours, holds it against a Postgres <code className="font-mono bg-(--color-paper-dim) px-1.5 py-0.5 rounded text-sm">EXCLUDE</code> constraint, and waits for one-click
        approval from the shop owner before locking it in Google Calendar. No
        auto-booking. No ghost appointments. No drift.
      </p>
      <div className="flex flex-wrap gap-3">
        <Link
          href="/demo"
          className="rounded-md bg-(--color-warm) text-white hover:bg-(--color-warm-deep) transition-colors px-4 py-2 text-sm font-medium no-underline"
        >
          Try the live demo →
        </Link>
        <Link
          href="/docs/quickstart"
          className="rounded-md border border-(--color-paper-deep) hover:border-(--color-ink-faint) transition-colors px-4 py-2 text-sm text-(--color-ink) no-underline"
        >
          Quickstart
        </Link>
        <Link
          href="https://github.com/philiprehberger/foyer"
          className="rounded-md border border-(--color-paper-dim) hover:border-(--color-paper-deep) transition-colors px-4 py-2 text-sm text-(--color-ink-dim) no-underline"
        >
          View source
        </Link>
      </div>
    </section>
  );
}
