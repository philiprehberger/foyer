import { ChatPreview } from "../components/ChatPreview";
import { Faq } from "../components/Faq";
import { Hero } from "../components/Hero";
import { HowItWorks } from "../components/HowItWorks";
import { LiveDemoCTA } from "../components/LiveDemoCTA";
import { OwnerInboxPreview } from "../components/OwnerInboxPreview";
import { Pricing } from "../components/Pricing";
import { WedgeBanner } from "../components/WedgeBanner";

export default function Home() {
  return (
    <div>
      <Hero />
      <WedgeBanner />

      <section className="mx-auto max-w-5xl px-6 py-20">
        <div className="grid md:grid-cols-2 gap-12 items-center">
          <div>
            <p className="text-xs uppercase tracking-widest text-(--color-warm-deep) mb-3">
              Customer side
            </p>
            <h2 className="text-2xl md:text-3xl font-semibold text-(--color-ink) mb-3">
              Plain text — no app, no link, no signup.
            </h2>
            <p className="text-(--color-ink-dim) leading-relaxed">
              Local-service customers don&rsquo;t install apps. They text. Foyer
              runs the intake over SMS or web chat with the same agent
              behind both — service type, address, timing, photos if the issue
              needs them — then proposes a slot and stops. The owner is the
              only thing that can turn a hold into a booking.
            </p>
          </div>
          <div>
            <ChatPreview />
          </div>
        </div>
      </section>

      <HowItWorks />

      <section className="mx-auto max-w-5xl px-6 py-20 border-t border-(--color-paper-dim)">
        <p className="text-xs uppercase tracking-widest text-(--color-warm-deep) mb-3">
          Owner side
        </p>
        <h2 className="text-2xl md:text-3xl font-semibold text-(--color-ink) mb-3">
          The card that turns a hold into a booking.
        </h2>
        <p className="text-(--color-ink-dim) leading-relaxed max-w-2xl mb-8">
          One pending card per request, with the transcript, the geocoded
          address, the customer&rsquo;s photos, and the slot the agent
          proposed. Confirm fires an idempotent request — double-clicks collapse
          to one Calendar update and one SMS. Reject releases the slot and
          re-engages the agent with alternatives.
        </p>
        <OwnerInboxPreview />
      </section>

      <LiveDemoCTA />
      <Pricing />
      <Faq />
    </div>
  );
}
