import { h } from "preact";
import { useRef, useState } from "preact/hooks";

type Props = {
  onSend: (text: string, files: File[]) => void;
  disabled?: boolean;
};

export function Composer({ onSend, disabled }: Props) {
  const [text, setText] = useState("");
  const [files, setFiles] = useState<File[]>([]);
  const fileRef = useRef<HTMLInputElement>(null);

  function send() {
    const trimmed = text.trim();
    if (!trimmed && files.length === 0) return;
    onSend(trimmed, files);
    setText("");
    setFiles([]);
    if (fileRef.current) fileRef.current.value = "";
  }

  function onKeyDown(e: KeyboardEvent) {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      send();
    }
  }

  return (
    <div class="composer">
      <button
        type="button"
        class="file-btn"
        title="Attach photo"
        onClick={() => fileRef.current?.click()}
        disabled={disabled}
        aria-label="Attach photo"
      >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48" />
        </svg>
      </button>
      <input
        ref={fileRef}
        type="file"
        accept="image/jpeg,image/png,image/heic,image/webp"
        style="display:none"
        onChange={(e) => {
          const list = (e.target as HTMLInputElement).files;
          if (list) setFiles(Array.from(list));
        }}
      />
      <textarea
        placeholder="Type a message…"
        value={text}
        onInput={(e) => setText((e.target as HTMLTextAreaElement).value)}
        onKeyDown={onKeyDown}
        disabled={disabled}
        rows={1}
      />
      <button
        type="button"
        class="send-btn"
        onClick={send}
        disabled={disabled || (!text.trim() && files.length === 0)}
        aria-label="Send message"
      >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="22" y1="2" x2="11" y2="13" />
          <polygon points="22 2 15 22 11 13 2 9 22 2" />
        </svg>
      </button>
    </div>
  );
}
