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
          Anchor Plumbing
        </p>
        <h1 className="text-3xl md:text-4xl font-semibold text-(--color-ink) mb-3 tracking-tight">
          Anchor Plumbing — Boulder, CO
        </h1>
        <p className="text-(--color-ink-dim) max-w-2xl leading-relaxed">
          Drain clearing, leak repair, and water heater install in Boulder, CO
          (ZIPs 80301-80310). Open Mon-Fri 8am-5pm Mountain. Text our booking
          line below to schedule, or use the web chat — both reach the same
          booking assistant.
        </p>
      </header>

      <div className="grid lg:grid-cols-2 gap-8 mb-10">
        <div className="rounded-lg border border-(--color-paper-deep) bg-white/60 p-6">
          <p className="text-xs uppercase tracking-widest text-(--color-ink-faint) mb-2">
            Text to book service
          </p>
          <a
            href="sms:+19704698922"
            className="block text-2xl font-mono text-(--color-ink) tracking-tight mb-3 no-underline"
          >
            (970) 469-8922
          </a>
          <p className="text-sm text-(--color-ink-dim) leading-relaxed mb-3">
            Send any message describing the service you need. The booking
            assistant will ask for the service type, address, and preferred
            time, then send a confirmation once the slot is held.
          </p>
          <p className="text-xs text-(--color-ink-dim) leading-snug">
            Reply <strong>HELP</strong> for help, <strong>STOP</strong> to
            opt out at any time. Message frequency is conversational and
            reply-only (typically 4-10 messages per booking session). Message
            and data rates may apply. By texting this number you agree to
            our{" "}
            <a
              href="/terms"
              className="text-(--color-warm-deep) underline underline-offset-2"
            >
              terms
            </a>{" "}
            and{" "}
            <a
              href="/privacy"
              className="text-(--color-warm-deep) underline underline-offset-2"
            >
              privacy policy
            </a>
            .
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
