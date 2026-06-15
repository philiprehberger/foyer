import { DemoControls } from "../../components/DemoControls";
import { OwnerInboxPreview } from "../../components/OwnerInboxPreview";
import { WidgetEmbed } from "../../components/WidgetEmbed";

export const metadata = {
  title: "Live demo — Anchor Plumbing",
};

const SLOT_QUEUE = [
  {
    customer: "+1 (303) 555-0142",
    service: "Drain clearing — kitchen",
    slot: "Thu Jun 19, 10:00 AM",
    state: "pending owner confirm",
    age: "00:02:11",
  },
  {
    customer: "+1 (720) 555-0188",
    service: "Leak repair — bath",
    slot: "Fri Jun 20, 1:00 PM",
    state: "owner confirmed",
    age: "00:11:42",
  },
  {
    customer: "+1 (303) 555-0119",
    service: "Out of area — 80112",
    slot: "—",
    state: "fallback (out of scope)",
    age: "00:14:03",
  },
];

export default function Demo() {
  return (
    <div className="mx-auto max-w-6xl px-6 py-12">
      <header className="mb-10">
        <p className="text-xs uppercase tracking-widest text-(--color-warm-deep) mb-2">
          Live demo
        </p>
        <h1 className="text-3xl md:text-4xl font-semibold text-(--color-ink) mb-3 tracking-tight">
          Anchor Plumbing — Boulder, CO
        </h1>
        <p className="text-(--color-ink-dim) max-w-2xl leading-relaxed">
          Fictional shop, real agent. Drain-clear, leak-repair, water-heater-install.
          Service area is Boulder ZIPs 80301-80310. Open Mon-Fri 8am-5pm.
          Quiet hours 9pm-8am Mountain. Try the SMS number or the web widget
          below — both run the same agent against the same business config.
        </p>
      </header>

      <div className="grid lg:grid-cols-2 gap-8 mb-10">
        <div className="rounded-lg border border-(--color-paper-deep) bg-white/60 p-6">
          <p className="text-xs uppercase tracking-widest text-(--color-ink-faint) mb-2">
            SMS
          </p>
          <p className="text-2xl font-mono text-(--color-ink) tracking-tight mb-3">
            +1 (720) 555-0199
          </p>
          <p className="text-sm text-(--color-ink-dim) leading-relaxed mb-1">
            Text the number above. The agent will introduce itself, collect
            address and timing, and propose a slot. The proposed slot then
            shows up in the owner inbox panel on the right of this page.
          </p>
          <p className="text-xs text-(--color-ink-faint) mt-3">
            US numbers only. Three conversations per day per source number.
          </p>
        </div>
        <div className="rounded-lg border border-(--color-paper-deep) bg-white/60 p-6">
          <p className="text-xs uppercase tracking-widest text-(--color-ink-faint) mb-2">
            Web widget
          </p>
          <p className="text-(--color-ink) leading-relaxed mb-3">
            The same Preact widget that customer sites embed — mounted here so
            you can run the intake flow without a phone.
          </p>
          <WidgetEmbed />
        </div>
      </div>

      <section className="mb-10">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-xl font-semibold text-(--color-ink)">
            Owner inbox — read-only
          </h2>
          <DemoControls />
        </div>
        <p className="text-sm text-(--color-ink-dim) mb-6 max-w-2xl">
          What the shop owner sees in Filament. Pending cards show transcript,
          geocoded address, photos, and the proposed slot. Confirm fires an
          idempotent request; double-clicks collapse to one Calendar update
          and one SMS. Buttons here are disabled — this view is for the prospect.
        </p>
        <div className="space-y-4">
          <OwnerInboxPreview />
        </div>

        <div className="mt-8 rounded-lg border border-(--color-paper-dim) bg-(--color-paper-dim)/40 overflow-hidden">
          <div className="px-5 py-3 border-b border-(--color-paper-dim) bg-(--color-paper-dim)/60">
            <p className="text-xs uppercase tracking-widest text-(--color-ink-faint)">
              Recent slot activity
            </p>
          </div>
          <table className="w-full text-sm">
            <thead className="text-(--color-ink-faint) text-xs uppercase tracking-widest">
              <tr>
                <th className="text-left px-5 py-2 font-normal">Customer</th>
                <th className="text-left px-5 py-2 font-normal">Service</th>
                <th className="text-left px-5 py-2 font-normal">Slot</th>
                <th className="text-left px-5 py-2 font-normal">State</th>
                <th className="text-left px-5 py-2 font-normal">Age</th>
              </tr>
            </thead>
            <tbody className="text-(--color-ink-dim)">
              {SLOT_QUEUE.map((row) => (
                <tr key={row.customer} className="border-t border-(--color-paper-dim)">
                  <td className="px-5 py-2 font-mono text-xs">{row.customer}</td>
                  <td className="px-5 py-2">{row.service}</td>
                  <td className="px-5 py-2">{row.slot}</td>
                  <td className="px-5 py-2">{row.state}</td>
                  <td className="px-5 py-2 font-mono text-xs">{row.age}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <section className="rounded-lg border border-(--color-paper-deep) bg-(--color-warm-soft)/40 p-6">
        <p className="text-xs uppercase tracking-widest text-(--color-warm-deep) mb-2">
          What this demo will and will not do
        </p>
        <ul className="text-sm text-(--color-ink-dim) space-y-1.5 leading-relaxed">
          <li>— It will propose Boulder-area drain / leak / water-heater slots inside Mon-Fri 8am-5pm.</li>
          <li>— It will refuse out-of-area, out-of-hours, out-of-catalog, and out-of-quiet-hours requests with a documented fallback.</li>
          <li>— It will not actually dispatch a plumber to your house. There is no plumber.</li>
          <li>— It will not send marketing follow-ups. STOP wins, no override.</li>
          <li>— It resets hourly on a cron, plus on the &ldquo;Reset demo&rdquo; button above.</li>
        </ul>
      </section>
    </div>
  );
}
