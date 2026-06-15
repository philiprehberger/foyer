export function OwnerInboxPreview() {
  return (
    <div className="rounded-lg border border-(--color-paper-deep) bg-white/60 overflow-hidden shadow-sm">
      <div className="px-5 py-3 border-b border-(--color-paper-dim) bg-(--color-paper-dim)/40 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <span className="text-xs uppercase tracking-widest text-(--color-warm-deep)">
            Pending booking
          </span>
          <span className="text-xs text-(--color-ink-faint)">— Anchor Plumbing</span>
        </div>
        <span className="text-xs text-(--color-ink-faint) font-mono">
          hold expires in 12:47
        </span>
      </div>
      <div className="px-5 py-4 grid sm:grid-cols-2 gap-4">
        <div>
          <p className="text-xs uppercase tracking-widest text-(--color-ink-faint) mb-1">
            Customer
          </p>
          <p className="text-sm text-(--color-ink) font-medium">
            +1 (303) 555-0142
          </p>
          <p className="text-xs text-(--color-ink-dim) mt-0.5">
            SMS — phone verified
          </p>
          <p className="text-xs uppercase tracking-widest text-(--color-ink-faint) mt-4 mb-1">
            Service
          </p>
          <p className="text-sm text-(--color-ink)">Drain clearing — kitchen</p>
          <p className="text-xs uppercase tracking-widest text-(--color-ink-faint) mt-4 mb-1">
            Proposed slot
          </p>
          <p className="text-sm text-(--color-ink) font-medium">
            Thu Jun 19, 10:00 – 11:30 AM (MDT)
          </p>
        </div>
        <div>
          <p className="text-xs uppercase tracking-widest text-(--color-ink-faint) mb-1">
            Address
          </p>
          <p className="text-sm text-(--color-ink)">1432 Oak St</p>
          <p className="text-sm text-(--color-ink)">Boulder, CO 80301</p>
          <p className="text-xs text-(--color-ink-dim) mt-0.5">
            Geocoded · in service area
          </p>
          <div className="mt-3 h-24 rounded bg-(--color-sage-soft) border border-(--color-paper-deep) flex items-center justify-center">
            <span className="text-xs text-(--color-sage) font-mono">map preview</span>
          </div>
        </div>
      </div>
      <div className="px-5 py-3 border-t border-(--color-paper-dim) bg-(--color-paper)/60 flex items-center justify-between">
        <span className="text-xs text-(--color-ink-faint)">
          Idempotency-Key required
        </span>
        <div className="flex items-center gap-2">
          <button
            type="button"
            className="rounded-md border border-(--color-paper-deep) text-(--color-ink-dim) px-3 py-1.5 text-xs"
            disabled
          >
            Reject
          </button>
          <button
            type="button"
            className="rounded-md bg-(--color-sage) text-white px-3 py-1.5 text-xs font-medium"
            disabled
          >
            Confirm booking
          </button>
        </div>
      </div>
    </div>
  );
}
