export function WedgeBanner() {
  return (
    <section className="border-y border-(--color-paper-dim) bg-(--color-paper-dim)/40">
      <div className="mx-auto max-w-5xl px-6 py-10">
        <p className="text-xs uppercase tracking-widest text-(--color-warm-deep) mb-3">
          The wedge
        </p>
        <p className="text-lg md:text-xl leading-relaxed text-(--color-ink)">
          Every &ldquo;AI booking bot&rdquo; demo on the market either
          auto-books — which turns the shop into a ghost-appointment factory —
          or chats without touching a calendar, which produces nothing. Foyer is
          the honest middle. It holds the slot for fifteen minutes against a
          Postgres exclusion constraint, asks the owner to confirm, then locks
          it in Calendar. The owner stays in the loop, the customer gets a real
          confirmation, the shop stops paying for no-shows.
        </p>
      </div>
    </section>
  );
}
