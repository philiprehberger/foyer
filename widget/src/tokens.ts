/**
 * All visible styling for the widget lives here. Inlined into the Shadow
 * DOM as a single <style> tag so host-page CSS cannot leak in or out.
 *
 * Theme is resolved at mount time: 'auto' reads prefers-color-scheme;
 * 'light' / 'dark' are forced.
 */

export type Theme = "light" | "dark";

export function resolveTheme(pref: "auto" | "light" | "dark"): Theme {
  if (pref === "light" || pref === "dark") return pref;
  if (
    typeof window !== "undefined" &&
    window.matchMedia &&
    window.matchMedia("(prefers-color-scheme: dark)").matches
  ) {
    return "dark";
  }
  return "light";
}

export function shadowStyles(theme: Theme): string {
  const isDark = theme === "dark";
  const ink = isDark ? "#f7f3ed" : "#1b1612";
  const inkDim = isDark ? "#bcb5a8" : "#57514a";
  const paper = isDark ? "#1b1612" : "#fbf7f1";
  const paperDim = isDark ? "#2a231d" : "#efe7d8";
  const paperDeep = isDark ? "#3a3027" : "#e3d9c5";
  const warm = "#b35a1f";
  const warmDeep = "#7a3a10";
  const bad = isDark ? "#ff8a7a" : "#b03020";

  return `
    :host { all: initial; }
    *, *::before, *::after { box-sizing: border-box; }

    .launcher {
      position: fixed;
      bottom: 20px;
      z-index: 2147483000;
      width: 56px; height: 56px; border-radius: 28px;
      background: ${warm};
      color: #fff;
      border: 0; cursor: pointer;
      box-shadow: 0 8px 24px rgba(0,0,0,0.18);
      display: flex; align-items: center; justify-content: center;
      font: 600 14px / 1.2 -apple-system, BlinkMacSystemFont, "Segoe UI", Inter, sans-serif;
    }
    .launcher.right { right: 20px; }
    .launcher.left  { left: 20px; }
    .launcher svg { width: 24px; height: 24px; }

    .panel {
      position: fixed;
      bottom: 90px;
      z-index: 2147483000;
      width: 360px; max-width: calc(100vw - 40px);
      height: 540px; max-height: calc(100vh - 120px);
      display: flex; flex-direction: column;
      background: ${paper};
      color: ${ink};
      border-radius: 14px;
      box-shadow: 0 18px 40px rgba(0,0,0,0.22);
      overflow: hidden;
      font: 14px / 1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Inter, sans-serif;
    }
    .panel.right { right: 20px; }
    .panel.left  { left: 20px; }

    .header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 14px 16px;
      background: ${warm};
      color: #fff;
    }
    .title { font-weight: 600; font-size: 14px; }
    .subtitle { font-size: 11px; opacity: 0.8; margin-top: 2px; }
    .close {
      background: none; border: 0; color: #fff; cursor: pointer;
      width: 28px; height: 28px; border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
    }
    .close:hover { background: rgba(255,255,255,0.15); }

    .body {
      flex: 1; min-height: 0; overflow-y: auto;
      padding: 16px;
      background: ${paper};
    }
    .empty {
      color: ${inkDim};
      font-size: 13px;
      line-height: 1.5;
      padding: 12px;
      border: 1px dashed ${paperDeep};
      border-radius: 8px;
    }

    .msg { display: flex; margin-bottom: 8px; }
    .msg.agent { justify-content: flex-start; }
    .msg.customer { justify-content: flex-end; }
    .bubble {
      max-width: 78%;
      padding: 8px 12px;
      border-radius: 14px;
      font-size: 14px;
      line-height: 1.4;
      white-space: pre-wrap;
      word-break: break-word;
    }
    .msg.agent .bubble {
      background: ${paperDim};
      color: ${ink};
      border-bottom-left-radius: 4px;
    }
    .msg.customer .bubble {
      background: ${warm};
      color: #fff;
      border-bottom-right-radius: 4px;
    }
    .msg.system .bubble {
      background: transparent;
      color: ${inkDim};
      font-size: 12px;
      font-style: italic;
      max-width: 100%;
      text-align: center;
    }

    .composer {
      border-top: 1px solid ${paperDeep};
      padding: 10px 12px;
      display: flex;
      align-items: flex-end;
      gap: 8px;
      background: ${paper};
    }
    .composer textarea {
      flex: 1;
      resize: none;
      min-height: 36px; max-height: 120px;
      padding: 8px 10px;
      border: 1px solid ${paperDeep};
      border-radius: 8px;
      font: inherit;
      color: ${ink};
      background: ${paper};
    }
    .composer textarea:focus {
      outline: none;
      border-color: ${warm};
    }
    .composer .file-btn,
    .composer .send-btn {
      border: 0; cursor: pointer;
      width: 36px; height: 36px;
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
    }
    .composer .file-btn {
      background: ${paperDim}; color: ${ink};
    }
    .composer .send-btn {
      background: ${warm}; color: #fff;
    }
    .composer .send-btn:disabled {
      background: ${paperDeep}; cursor: not-allowed;
    }

    .otp {
      padding: 16px;
      display: flex; flex-direction: column; gap: 10px;
      background: ${paperDim};
    }
    .otp h3 { margin: 0; font-size: 14px; color: ${ink}; font-weight: 600; }
    .otp p { margin: 0; font-size: 12px; color: ${inkDim}; line-height: 1.4; }
    .otp input {
      padding: 8px 10px;
      border: 1px solid ${paperDeep};
      border-radius: 6px;
      font: inherit;
      color: ${ink};
      background: ${paper};
    }
    .otp .row { display: flex; gap: 8px; }
    .otp .row input { flex: 1; }
    .otp button {
      background: ${warm}; color: #fff; border: 0; cursor: pointer;
      padding: 8px 12px; border-radius: 6px; font: inherit;
    }
    .otp button.secondary {
      background: transparent; color: ${warmDeep};
      text-decoration: underline;
      padding: 4px 0;
      text-align: left;
    }
    .otp .err { color: ${bad}; font-size: 12px; }
  `;
}
