import { h } from "preact";
import { useEffect, useRef } from "preact/hooks";
import type { WidgetMessage } from "./types";

type Props = {
  messages: WidgetMessage[];
  businessName: string;
};

export function MessageList({ messages, businessName }: Props) {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (ref.current) ref.current.scrollTop = ref.current.scrollHeight;
  }, [messages.length]);

  return (
    <div class="body" ref={ref}>
      {messages.length === 0 && (
        <div class="empty">
          Tell us what you need — service type, address, when works. Replies
          land here. If we have your number on file from a prior text, the
          conversation can pick up where you left it after a quick code check.
        </div>
      )}
      {messages.map((m) => (
        <div class={`msg ${m.role}`} key={m.id}>
          <div class="bubble">{m.text}</div>
        </div>
      ))}
      {messages.length > 0 && (
        <div class="msg system">
          <div class="bubble">
            You are texting {businessName}. Reply STOP at any time to opt out.
          </div>
        </div>
      )}
    </div>
  );
}
