import { h } from "preact";
import { useEffect, useMemo, useState } from "preact/hooks";
import { Composer } from "./Composer";
import { MessageList } from "./MessageList";
import { OtpPrompt } from "./OtpPrompt";
import { Transport, uuidv7 } from "./transport";
import type { Config, WidgetMessage } from "./types";

type Props = {
  config: Config;
  businessName: string;
  onClose: () => void;
};

type View = "otp" | "chat";

export function ChatPanel({ config, businessName, onClose }: Props) {
  const transport = useMemo(() => new Transport(config), [config]);
  const [view, setView] = useState<View>("otp");
  const [messages, setMessages] = useState<WidgetMessage[]>([]);
  const [sending, setSending] = useState(false);

  useEffect(() => {
    if (view !== "chat") return;
    let unsub: (() => void) | undefined;
    transport
      .mintSession()
      .then(() => {
        unsub = transport.subscribe((m) => {
          setMessages((prev) => (prev.find((p) => p.id === m.id) ? prev : [...prev, m]));
        });
      })
      .catch(() => undefined);
    return () => {
      if (unsub) unsub();
    };
  }, [view, transport]);

  async function handleSend(text: string, files: File[]) {
    const optimistic: WidgetMessage = {
      id: uuidv7(),
      role: "customer",
      text,
      attachments: files.map((f) => ({ content_type: f.type, url: "", name: f.name })),
      created_at: new Date().toISOString(),
    };
    setMessages((prev) => [...prev, optimistic]);
    setSending(true);
    try {
      const sent = await transport.sendInbound(text, files);
      setMessages((prev) => prev.map((m) => (m.id === optimistic.id ? sent : m)));
    } catch {
      setMessages((prev) =>
        prev.map((m) =>
          m.id === optimistic.id
            ? { ...m, text: m.text + "\n[failed to send — tap to retry]" }
            : m,
        ),
      );
    } finally {
      setSending(false);
    }
  }

  return (
    <div class={`panel ${config.position === "bottom-left" ? "left" : "right"}`}>
      <div class="header">
        <div>
          <div class="title">{businessName}</div>
          <div class="subtitle">Booking line — usually replies in a few minutes</div>
        </div>
        <button type="button" class="close" onClick={onClose} aria-label="Close chat">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>
      {view === "otp" ? (
        <OtpPrompt
          transport={transport}
          onResumed={() => setView("chat")}
          onSkip={() => setView("chat")}
        />
      ) : (
        <>
          <MessageList messages={messages} businessName={businessName} />
          <Composer onSend={handleSend} disabled={sending} />
        </>
      )}
    </div>
  );
}
