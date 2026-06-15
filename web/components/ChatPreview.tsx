type Turn = {
  from: "customer" | "agent";
  text: string;
};

const SCRIPT: Turn[] = [
  { from: "customer", text: "hi can you clear my kitchen drain" },
  {
    from: "agent",
    text:
      "Hi, this is the booking line for Anchor Plumbing. I can help with that. What's the service address?",
  },
  { from: "customer", text: "1432 Oak St, Boulder CO" },
  {
    from: "agent",
    text:
      "Got it — 1432 Oak St, Boulder CO 80301. When would you like us to come out? We're open Mon-Fri 8am-5pm; the earliest available is Thursday 6/19 at 10am.",
  },
  { from: "customer", text: "thursday works" },
  {
    from: "agent",
    text:
      "Holding Thursday 6/19 at 10am for you. I'll text once Sam confirms — usually within fifteen minutes during business hours.",
  },
];

export function ChatPreview() {
  return (
    <div className="rounded-[28px] border border-(--color-paper-deep) bg-(--color-paper) shadow-[0_18px_40px_-20px_rgba(123,58,16,0.35)] overflow-hidden max-w-sm mx-auto">
      <div className="px-5 pt-4 pb-3 border-b border-(--color-paper-dim) flex items-center justify-between">
        <div>
          <p className="text-[10px] uppercase tracking-widest text-(--color-ink-faint)">
            Messages
          </p>
          <p className="text-sm font-medium text-(--color-ink)">
            (303) 555-0142
          </p>
        </div>
        <p className="text-xs text-(--color-ink-faint)">9:47 AM</p>
      </div>
      <div className="px-4 py-5 space-y-2 bg-white/50 max-h-[480px] overflow-y-auto">
        {SCRIPT.map((turn, i) => (
          <div
            key={i}
            className={
              turn.from === "customer"
                ? "flex justify-end"
                : "flex justify-start"
            }
          >
            <div
              className={
                turn.from === "customer"
                  ? "max-w-[78%] rounded-2xl rounded-br-md px-3.5 py-2 bg-(--color-warm) text-white text-sm leading-snug"
                  : "max-w-[78%] rounded-2xl rounded-bl-md px-3.5 py-2 bg-(--color-paper-dim) text-(--color-ink) text-sm leading-snug"
              }
            >
              {turn.text}
            </div>
          </div>
        ))}
      </div>
      <div className="px-4 py-3 border-t border-(--color-paper-dim) flex items-center gap-2 bg-(--color-paper)">
        <div className="flex-1 rounded-full bg-(--color-paper-dim) px-3 py-1.5 text-xs text-(--color-ink-faint)">
          Text Message
        </div>
        <div className="w-7 h-7 rounded-full bg-(--color-warm)/20" />
      </div>
    </div>
  );
}
