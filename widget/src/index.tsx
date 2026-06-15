/**
 * Foyer chat widget — embeddable Preact app rendered into a Shadow DOM.
 *
 * Embed:
 *   <script
 *     src="https://foyer.philiprehberger.com/widget.js"
 *     data-foyer-business-id="01HZ..."
 *     data-foyer-position="bottom-right"
 *     data-foyer-theme="auto">
 *   </script>
 *
 * Required: data-foyer-business-id (the business's ULID).
 * Optional: data-foyer-api-base, data-foyer-position, data-foyer-theme,
 *           data-foyer-business-name (display label).
 */
import { h, render } from "preact";
import { useState } from "preact/hooks";
import { ChatPanel } from "./ChatPanel";
import { resolveTheme, shadowStyles } from "./tokens";
import type { Config } from "./types";

function readConfig(script: HTMLScriptElement): Config {
  return {
    apiBase:
      script.dataset.foyerApiBase ?? "https://api.foyer.philiprehberger.com",
    businessId: script.dataset.foyerBusinessId ?? "",
    position:
      (script.dataset.foyerPosition as Config["position"]) ?? "bottom-right",
    theme: (script.dataset.foyerTheme as Config["theme"]) ?? "auto",
  };
}

function Widget({
  config,
  businessName,
}: {
  config: Config;
  businessName: string;
}) {
  const [open, setOpen] = useState(false);

  if (open) {
    return (
      <ChatPanel
        config={config}
        businessName={businessName}
        onClose={() => setOpen(false)}
      />
    );
  }

  return (
    <button
      type="button"
      class={`launcher ${config.position === "bottom-left" ? "left" : "right"}`}
      onClick={() => setOpen(true)}
      aria-label="Open booking chat"
    >
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z" />
      </svg>
    </button>
  );
}

function mount() {
  const script =
    (document.currentScript as HTMLScriptElement | null) ??
    (document.querySelector(
      "script[data-foyer-business-id]",
    ) as HTMLScriptElement | null);
  if (!script) return;

  const config = readConfig(script);
  if (!config.businessId) {
    console.warn("[foyer-widget] data-foyer-business-id is required");
    return;
  }

  const businessName =
    script.dataset.foyerBusinessName ?? "this business";

  if (document.getElementById("foyer-widget-host")) return;

  const host = document.createElement("div");
  host.id = "foyer-widget-host";
  document.body.appendChild(host);

  const shadow = host.attachShadow({ mode: "open" });
  const style = document.createElement("style");
  style.textContent = shadowStyles(resolveTheme(config.theme));
  shadow.appendChild(style);

  const mountPoint = document.createElement("div");
  shadow.appendChild(mountPoint);

  render(<Widget config={config} businessName={businessName} />, mountPoint);
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", mount);
} else {
  mount();
}
