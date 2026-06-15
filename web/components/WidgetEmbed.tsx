"use client";

import { useEffect, useRef } from "react";

const WIDGET_SRC = "/widget.js";
const BUSINESS_ID = "01J4YQX9MA0RBPV6N7K8WJ6ANCH";

export function WidgetEmbed() {
  const mounted = useRef(false);

  useEffect(() => {
    if (mounted.current) return;
    mounted.current = true;
    const existing = document.querySelector(
      `script[data-foyer-business-id="${BUSINESS_ID}"]`,
    );
    if (existing) return;
    const s = document.createElement("script");
    s.src = WIDGET_SRC;
    s.async = true;
    s.dataset.foyerBusinessId = BUSINESS_ID;
    s.dataset.foyerPosition = "bottom-right";
    s.dataset.foyerTheme = "auto";
    document.body.appendChild(s);
  }, []);

  return (
    <div className="rounded-md border border-dashed border-(--color-paper-deep) p-4 text-xs text-(--color-ink-dim) bg-(--color-paper)">
      The widget mounts itself on the page — look for the launcher in the
      lower-right corner. If you do not see it, the widget bundle has not been
      built yet; run <code className="bg-(--color-paper-dim) px-1 py-0.5 rounded">node widget/build.mjs</code> from the repo root.
    </div>
  );
}
