export function Pricing() {
  return (
    <section className="mx-auto max-w-5xl px-6 py-20">
      <p className="text-xs uppercase tracking-widest text-(--color-warm-deep) mb-3">
        Pricing — illustrative
      </p>
      <h2 className="text-2xl md:text-3xl font-semibold text-(--color-ink) mb-3">
        One shop, one number, one calendar.
      </h2>
      <p className="text-(--color-ink-dim) max-w-2xl mb-10 leading-relaxed">
        Foyer is a portfolio demo, not a product on sale. The numbers below
        sketch what a real engagement would look like — a fixed-fee setup that
        replaces the blank page with a working agent, then a monthly retainer
        sized to one shop with one Twilio number and one Google Calendar.
      </p>
      <div className="grid md:grid-cols-2 gap-5 max-w-3xl">
        <div className="rounded-lg border border-(--color-paper-deep) bg-white/60 p-6">
          <p className="text-xs uppercase tracking-widest text-(--color-ink-faint) mb-2">
            Setup
          </p>
          <p className="text-3xl font-semibold text-(--color-ink) mb-1">
            $1,800
          </p>
          <p className="text-sm text-(--color-ink-dim) mb-5">one-time</p>
          <ul className="space-y-2 text-sm text-(--color-ink-dim)">
            <li>— Twilio number provisioning + 10DLC walkthrough</li>
            <li>— Google Calendar OAuth wiring</li>
            <li>— Scope configuration — services, hours, area, lead time</li>
            <li>— Agent persona + welcome / confirm / out-of-scope copy</li>
            <li>— Owner inbox access, kill switch, runbook hand-off</li>
          </ul>
        </div>
        <div className="rounded-lg border border-(--color-paper-deep) bg-white/60 p-6">
          <p className="text-xs uppercase tracking-widest text-(--color-ink-faint) mb-2">
            Monthly
          </p>
          <p className="text-3xl font-semibold text-(--color-ink) mb-1">
            $240
          </p>
          <p className="text-sm text-(--color-ink-dim) mb-5">per shop, per month</p>
          <ul className="space-y-2 text-sm text-(--color-ink-dim)">
            <li>— Hosting, monitoring, uptime watch</li>
            <li>— LLM token cost passed through at cost — typical $4-$12 / mo</li>
            <li>— Twilio SMS cost passed through at cost</li>
            <li>— Scope and persona edits — one round per month</li>
            <li>— Quarterly review of out-of-scope log for service expansion</li>
          </ul>
        </div>
      </div>
      <p className="text-xs text-(--color-ink-faint) mt-6 max-w-2xl">
        These numbers assume one Twilio number, one Google Calendar, one human
        owner. Multi-location shops or shared-calendar shops are scoping work
        before they are pricing work.
      </p>
    </section>
  );
}
