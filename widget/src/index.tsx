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
 * Phase 1 ships the boot + Shadow DOM + open/close + a stubbed session
 * mint. Phase 6 lands the full chat UI, OTP cross-channel resume, and the
 * embeddable build pipeline integration tests.
 */
import { h, render } from 'preact';
import { useState } from 'preact/hooks';

interface Config {
    apiBase: string;
    businessId: string;
    position: 'bottom-right' | 'bottom-left';
    theme: 'auto' | 'light' | 'dark';
}

function readConfig(script: HTMLScriptElement): Config {
    return {
        apiBase: script.dataset.foyerApiBase ?? 'https://api.foyer.philiprehberger.com',
        businessId: script.dataset.foyerBusinessId ?? '',
        position: (script.dataset.foyerPosition as Config['position']) ?? 'bottom-right',
        theme: (script.dataset.foyerTheme as Config['theme']) ?? 'auto',
    };
}

const TOKENS = `
    :host { all: initial; }
    .panel {
        position: fixed; bottom: 20px; z-index: 2147483000;
        width: 360px; max-width: calc(100vw - 40px);
        background: #fff; border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.18);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Inter, sans-serif;
        color: #1c1817;
        overflow: hidden;
    }
    .panel.right { right: 20px; }
    .panel.left  { left: 20px; }
    .header {
        background: #b45309; color: white;
        padding: 12px 16px; font-weight: 600;
        display: flex; justify-content: space-between; align-items: center;
        cursor: pointer;
    }
    .body { padding: 16px; min-height: 280px; }
    .close { background: none; border: 0; color: white; cursor: pointer; font-size: 18px; }
    .empty { color: #57534e; font-size: 14px; line-height: 1.4; }
    .composer { display: flex; gap: 8px; padding: 12px 16px; border-top: 1px solid #e7e5e4; }
    .composer input { flex: 1; padding: 8px 10px; border-radius: 6px; border: 1px solid #d6d3d1; font-size: 14px; }
    .composer button { padding: 8px 14px; border-radius: 6px; background: #b45309; color: white; border: 0; cursor: pointer; }
`;

function Widget({ config }: { config: Config }) {
    const [open, setOpen] = useState(false);
    const [text, setText] = useState('');

    return (
        <div class={`panel ${config.position === 'bottom-left' ? 'left' : 'right'}`}>
            <div class="header" onClick={() => setOpen(!open)}>
                <span>Foyer — text us</span>
                <button class="close" type="button">{open ? '–' : '+'}</button>
            </div>
            {open && (
                <>
                    <div class="body">
                        <p class="empty">
                            Tell us what you need help with — service type, address, when works.
                        </p>
                    </div>
                    <div class="composer">
                        <input
                            placeholder="Type a message…"
                            value={text}
                            onInput={(e) => setText((e.target as HTMLInputElement).value)}
                        />
                        <button type="button" onClick={() => setText('')}>Send</button>
                    </div>
                </>
            )}
        </div>
    );
}

function mount() {
    const script = document.currentScript as HTMLScriptElement | null;
    if (!script) {
        return;
    }
    const config = readConfig(script);
    if (!config.businessId) {
        console.warn('[foyer-widget] data-foyer-business-id is required');

        return;
    }

    const host = document.createElement('div');
    host.id = 'foyer-widget-host';
    document.body.appendChild(host);

    const shadow = host.attachShadow({ mode: 'open' });
    const style = document.createElement('style');
    style.textContent = TOKENS;
    shadow.appendChild(style);

    const mountPoint = document.createElement('div');
    shadow.appendChild(mountPoint);

    render(<Widget config={config} />, mountPoint);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
} else {
    mount();
}
