"use client";

import { useState } from "react";

export function DemoControls() {
  const [state, setState] = useState<"idle" | "loading" | "done" | "error">(
    "idle",
  );
  const [message, setMessage] = useState<string | null>(null);

  async function reset() {
    setState("loading");
    setMessage(null);
    try {
      // Stub — wires to a real endpoint when the demo backend lands.
      await new Promise((r) => setTimeout(r, 600));
      setState("done");
      setMessage("Demo state reset.");
      setTimeout(() => setState("idle"), 2500);
    } catch {
      setState("error");
      setMessage("Reset failed.");
    }
  }

  return (
    <div className="flex items-center gap-3">
      {message && (
        <span
          className={
            "text-xs " +
            (state === "error"
              ? "text-(--color-bad)"
              : "text-(--color-good)")
          }
        >
          {message}
        </span>
      )}
      <button
        type="button"
        onClick={reset}
        disabled={state === "loading"}
        className="rounded-md border border-(--color-paper-deep) hover:border-(--color-ink-faint) px-3 py-1.5 text-xs text-(--color-ink) disabled:opacity-60"
      >
        {state === "loading" ? "Resetting…" : "Reset demo"}
      </button>
    </div>
  );
}
