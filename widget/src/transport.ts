import type { Config, SessionMint, VerifyResponse, WidgetMessage } from "./types";

/**
 * UUIDv7 — timestamp-ordered, monotonic-leaning. Used as the client-side
 * idempotency key on every outbound message so server-side dedupe is
 * straightforward.
 */
export function uuidv7(): string {
  const t = BigInt(Date.now());
  const bytes = new Uint8Array(16);
  bytes[0] = Number((t >> 40n) & 0xffn);
  bytes[1] = Number((t >> 32n) & 0xffn);
  bytes[2] = Number((t >> 24n) & 0xffn);
  bytes[3] = Number((t >> 16n) & 0xffn);
  bytes[4] = Number((t >> 8n) & 0xffn);
  bytes[5] = Number(t & 0xffn);
  const rand = new Uint8Array(10);
  crypto.getRandomValues(rand);
  bytes[6] = (0x70 | (rand[0] & 0x0f)) & 0xff;
  bytes[7] = rand[1] ?? 0;
  bytes[8] = (0x80 | ((rand[2] ?? 0) & 0x3f)) & 0xff;
  for (let i = 9; i < 16; i++) {
    bytes[i] = rand[i - 6] ?? 0;
  }
  const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, "0")).join("");
  return (
    hex.slice(0, 8) +
    "-" +
    hex.slice(8, 12) +
    "-" +
    hex.slice(12, 16) +
    "-" +
    hex.slice(16, 20) +
    "-" +
    hex.slice(20)
  );
}

export class Transport {
  private session: SessionMint | null = null;

  constructor(private config: Config) {}

  async mintSession(): Promise<SessionMint> {
    if (this.session && new Date(this.session.expires_at).getTime() > Date.now() + 30_000) {
      return this.session;
    }
    const res = await fetch(`${this.config.apiBase}/v1/web/sessions`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ business_id: this.config.businessId }),
    });
    if (!res.ok) throw new Error(`session mint failed: ${res.status}`);
    const data = (await res.json()) as SessionMint;
    this.session = data;
    return data;
  }

  async sendInbound(text: string, attachments: File[]): Promise<WidgetMessage> {
    const session = await this.mintSession();
    const widget_message_id = uuidv7();
    const uploads = attachments.length
      ? await Promise.all(attachments.map((f) => this.uploadAttachment(session.token, f)))
      : [];
    const res = await fetch(`${this.config.apiBase}/v1/web/inbound`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${session.token}`,
      },
      body: JSON.stringify({ widget_message_id, text, attachments: uploads }),
    });
    if (!res.ok) throw new Error(`inbound send failed: ${res.status}`);
    return (await res.json()) as WidgetMessage;
  }

  async uploadAttachment(
    token: string,
    file: File,
  ): Promise<{ content_type: string; url: string; name: string }> {
    const fd = new FormData();
    fd.append("file", file);
    const res = await fetch(`${this.config.apiBase}/v1/web/attachments`, {
      method: "POST",
      headers: { Authorization: `Bearer ${token}` },
      body: fd,
    });
    if (!res.ok) throw new Error(`attachment upload failed: ${res.status}`);
    return (await res.json()) as { content_type: string; url: string; name: string };
  }

  async issueOtp(phoneE164: string): Promise<VerifyResponse> {
    const session = await this.mintSession();
    const res = await fetch(
      `${this.config.apiBase}/v1/web/sessions/${session.session_id}/verify-phone`,
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${session.token}`,
        },
        body: JSON.stringify({ phone_e164: phoneE164 }),
      },
    );
    return (await res.json()) as VerifyResponse;
  }

  async submitOtp(phoneE164: string, code: string): Promise<VerifyResponse> {
    const session = await this.mintSession();
    const res = await fetch(
      `${this.config.apiBase}/v1/web/sessions/${session.session_id}/verify-phone`,
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${session.token}`,
        },
        body: JSON.stringify({ phone_e164: phoneE164, code }),
      },
    );
    return (await res.json()) as VerifyResponse;
  }

  /**
   * Subscribe to the session's SSE stream for agent replies. Returns a
   * cleanup function. Falls back to a no-op if EventSource is missing.
   */
  subscribe(onMessage: (m: WidgetMessage) => void): () => void {
    if (!this.session) return () => undefined;
    if (typeof EventSource === "undefined") return () => undefined;
    const url = `${this.config.apiBase}/v1/web/sessions/${this.session.session_id}/stream?token=${encodeURIComponent(this.session.token)}`;
    const es = new EventSource(url);
    es.addEventListener("message", (e: MessageEvent) => {
      try {
        const data = JSON.parse(e.data) as WidgetMessage;
        onMessage(data);
      } catch {
        /* malformed — drop */
      }
    });
    return () => es.close();
  }
}
