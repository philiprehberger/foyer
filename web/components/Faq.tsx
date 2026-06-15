type Qa = { q: string; a: string };

const QAS: Qa[] = [
  {
    q: "Why a fifteen-minute hold and not auto-book?",
    a: "Auto-booking is the failure mode for this category — the bot agrees to anything, the calendar fills with appointments the owner never sees, the shop ends up sending a tech to an address where nobody's home. The fifteen-minute hold sits in the middle: the slot is reserved against a Postgres exclusion constraint so a second customer can't take it, and the owner has a window to confirm or reject before it becomes a real booking.",
  },
  {
    q: "What happens if the owner doesn't confirm in fifteen minutes?",
    a: "The hold expires, the Calendar event is deleted, and the customer is texted that the slot is no longer available with an offer to check another time. Quiet hours apply — if the expiry would land at 11pm, the message is queued until morning.",
  },
  {
    q: "What stops the bot from agreeing to something out-of-scope?",
    a: "Scope is configured per business and validated at write time — service types, service area as ZIPs or radius, business hours, blocked dates, lead-time bounds, quiet hours. The agent is a state machine constrained to those rules and refuses out-of-scope requests with a documented fallback. Garbage configs cannot be saved.",
  },
  {
    q: "What about TCPA and 10DLC?",
    a: "STOP, START, HELP are caught before any agent dispatch and consent is keyed on the (customer phone, Twilio number) pair. Conversational reply is exempt from express-written-consent rules, but reactivation requires explicit START — no override. 10DLC brand and campaign registration are walked through in the setup docs.",
  },
  {
    q: "What happens if the owner moves the Calendar event from their phone?",
    a: "Google Calendar events.watch push subscription notices the change and a reconciliation job updates the booking. If the push channel expires, a five-minute fallback poll catches it. The owner dashboard surfaces a sync-health indicator if drift exceeds threshold.",
  },
  {
    q: "Why is there a Python sidecar?",
    a: "The agent state machine and LLM adapter live in FastAPI to keep retry, backoff, and circuit-breaker logic out of the request cycle, to make async LLM streaming additive without re-plumbing PHP, and because portfolio coverage of Python alongside PHP is the point. A documented Laravel-only fallback path is described in docs/architecture/why-fastapi.md for buyers who want to fork without the Python half.",
  },
];

export function Faq() {
  return (
    <section className="mx-auto max-w-5xl px-6 py-20">
      <h2 className="text-2xl md:text-3xl font-semibold text-(--color-ink) mb-10">
        Questions you'd ask before you'd buy.
      </h2>
      <div className="space-y-4">
        {QAS.map((qa) => (
          <details
            key={qa.q}
            className="rounded-lg border border-(--color-paper-deep) bg-white/50 px-5 py-4 group"
          >
            <summary className="cursor-pointer text-base font-medium text-(--color-ink) flex items-center justify-between gap-4 list-none">
              <span>{qa.q}</span>
              <span className="text-(--color-warm-deep) text-lg leading-none group-open:rotate-45 transition-transform">
                +
              </span>
            </summary>
            <p className="mt-3 text-sm text-(--color-ink-dim) leading-relaxed">
              {qa.a}
            </p>
          </details>
        ))}
      </div>
    </section>
  );
}
