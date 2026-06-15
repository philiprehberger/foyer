import { h } from "preact";
import { useState } from "preact/hooks";
import type { Transport } from "./transport";

type Props = {
  transport: Transport;
  onResumed: (conversationId: string | null) => void;
  onSkip: () => void;
};

type Step = "phone" | "code" | "blocked";

export function OtpPrompt({ transport, onResumed, onSkip }: Props) {
  const [step, setStep] = useState<Step>("phone");
  const [phone, setPhone] = useState("");
  const [code, setCode] = useState("");
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  async function requestCode() {
    setBusy(true);
    setErr(null);
    try {
      const res = await transport.issueOtp(phone);
      if (res.status === "consent_blocked") {
        setStep("blocked");
        return;
      }
      setStep("code");
    } catch {
      setErr("Could not send the code. Try again in a moment.");
    } finally {
      setBusy(false);
    }
  }

  async function submitCode() {
    setBusy(true);
    setErr(null);
    try {
      const res = await transport.submitOtp(phone, code);
      if (res.status === "verified") {
        onResumed(res.resumed_conversation_id);
      } else if (res.status === "consent_blocked") {
        setStep("blocked");
      } else {
        setErr("That code did not match. Try again.");
      }
    } catch {
      setErr("Could not verify the code. Try again in a moment.");
    } finally {
      setBusy(false);
    }
  }

  if (step === "blocked") {
    return (
      <div class="otp">
        <h3>This number has opted out</h3>
        <p>
          To resume booking texts from this business, contact them directly —
          we cannot un-do an opt-out from here.
        </p>
      </div>
    );
  }

  if (step === "code") {
    return (
      <div class="otp">
        <h3>Enter the 6-digit code</h3>
        <p>We sent it to {phone}. Codes expire in 10 minutes.</p>
        <div class="row">
          <input
            type="text"
            inputMode="numeric"
            maxLength={6}
            value={code}
            onInput={(e) => setCode((e.target as HTMLInputElement).value.replace(/\D/g, ""))}
            placeholder="123456"
          />
          <button type="button" onClick={submitCode} disabled={busy || code.length !== 6}>
            Verify
          </button>
        </div>
        {err && <div class="err">{err}</div>}
        <button type="button" class="secondary" onClick={onSkip} disabled={busy}>
          Skip — start fresh
        </button>
      </div>
    );
  }

  return (
    <div class="otp">
      <h3>Pick up where you left off?</h3>
      <p>
        If you have texted this business before, enter your phone number and
        we will send a 6-digit code to resume your conversation. Skip to start a
        new one.
      </p>
      <div class="row">
        <input
          type="tel"
          value={phone}
          onInput={(e) => setPhone((e.target as HTMLInputElement).value)}
          placeholder="+1 (303) 555-0142"
        />
        <button type="button" onClick={requestCode} disabled={busy || phone.length < 7}>
          Send code
        </button>
      </div>
      {err && <div class="err">{err}</div>}
      <button type="button" class="secondary" onClick={onSkip} disabled={busy}>
        Skip — start fresh
      </button>
    </div>
  );
}
