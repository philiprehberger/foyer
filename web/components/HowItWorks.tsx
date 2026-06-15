type Step = {
  channel: string;
  title: string;
  body: string;
};

const STEPS: Step[] = [
  {
    channel: "SMS",
    title: "Customer texts the shop's number.",
    body: "Twilio webhook validates the signature, dedupes on MessageSid, dispatches a turn job to Redis, and returns 200 in under 500ms. STOP, START, and HELP are caught before any agent dispatch.",
  },
  {
    channel: "Web widget",
    title: "Embedded chat on the shop's site.",
    body: "Preact widget under 30KB gzipped, Shadow-DOM isolated so the host page's CSS can't leak in or out. Cross-channel resume requires phone OTP — number-match alone is a spoofing vector.",
  },
  {
    channel: "Owner inbox",
    title: "One click confirms. One click rejects.",
    body: "Filament admin shows the transcript, the address with a map preview, the photos, and the proposed slot. Confirm fires an Idempotency-Key-guarded request; the slot locks in Calendar, the customer gets a confirmation SMS, the conversation completes.",
  },
];

export function HowItWorks() {
  return (
    <section className="mx-auto max-w-5xl px-6 py-20">
      <h2 className="text-2xl md:text-3xl font-semibold mb-3 text-(--color-ink)">
        Three surfaces, one conversation.
      </h2>
      <p className="text-(--color-ink-dim) mb-10 max-w-2xl">
        The customer texts or types. The agent collects what it needs, proposes
        a slot, and stops. The owner is the only thing that turns a hold into a
        booking.
      </p>
      <div className="grid md:grid-cols-3 gap-5">
        {STEPS.map((s) => (
          <div
            key={s.channel}
            className="rounded-lg border border-(--color-paper-dim) bg-white/40 p-6"
          >
            <p className="text-xs uppercase tracking-widest text-(--color-warm-deep) mb-3">
              {s.channel}
            </p>
            <p className="text-base font-semibold text-(--color-ink) mb-2">
              {s.title}
            </p>
            <p className="text-sm text-(--color-ink-dim) leading-relaxed">
              {s.body}
            </p>
          </div>
        ))}
      </div>
    </section>
  );
}
