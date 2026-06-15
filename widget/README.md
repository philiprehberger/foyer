# Foyer widget

Embedded chat widget — Preact + Shadow DOM, hard-capped at 30 KB gzipped
by the CI gate. One script tag, no host-page CSS dependency, no host-page
CSS leak in either direction.

## Embed

```html
<script
    src="https://foyer.philiprehberger.com/widget.js"
    data-foyer-business-id="01J4YQX9MA0RBPV6N7K8WJ6ANCH"
    data-foyer-position="bottom-right"
    data-foyer-theme="auto"
    async>
</script>
```

Required:

- `data-foyer-business-id` — the ULID of the business this widget belongs
  to.

Optional:

- `data-foyer-api-base` — override the API base URL. Default
  `https://api.foyer.philiprehberger.com`.
- `data-foyer-position` — `bottom-right` (default) or `bottom-left`.
- `data-foyer-theme` — `auto` (default — follows
  `prefers-color-scheme`), `light`, or `dark`.
- `data-foyer-business-name` — display label shown in the chat header.

## What it does

1. POSTs `/v1/web/sessions` with the business ID to mint a short-lived
   session token bound to the visitor's IP.
2. Renders a launcher button. On click, opens a chat panel.
3. First panel view is the OTP cross-channel-resume prompt — visitor can
   enter their phone, receive a 6-digit code, and resume an existing SMS
   conversation; or skip and start a fresh web session.
4. Each customer message POSTs `/v1/web/inbound` with a client-generated
   `widget_message_id` (UUIDv7) for idempotency.
5. Agent replies stream back via SSE on
   `/v1/web/sessions/:id/stream`.

## Build

```sh
npm install
npm run build         # writes dist/foyer-widget.js + .gz
npm run size-check    # fails if dist/foyer-widget.js.gz > 30 KB
npm run typecheck
```

CI runs `build` + `size-check` as the bundle-size gate.

## Local dev

```sh
npm run build
python3 -m http.server 5173
# open http://localhost:5173/index.html
```

The dev page mounts the widget pointed at `http://localhost:8000` (where
`php artisan serve` runs). Edit `index.html` to point elsewhere.

## Shadow DOM isolation

The widget renders into an open Shadow DOM root mounted on a single
`<div id="foyer-widget-host">` appended to `<body>`. Host-page CSS does
not leak in; widget CSS does not leak out. `:host { all: initial }`
resets any inherited typography.

Open mode (not closed) so a debugger can still inspect the tree — no
host-page `!important` selector will reach inside either way.

## File layout

```
widget/
  src/
    index.tsx         Boot — reads config, attaches Shadow DOM, mounts.
    ChatPanel.tsx     Top-level panel — header, OTP, messages, composer.
    MessageList.tsx   Scrolling transcript.
    Composer.tsx      Text input + file picker.
    OtpPrompt.tsx     Phone + code prompt for cross-channel resume.
    tokens.ts         Inline styles + theme resolution.
    transport.ts      Session mint, send, OTP, SSE subscribe, UUIDv7.
    types.ts          Shared type defs.
  index.html          Local dev mount page.
  build.mjs           esbuild -> dist/foyer-widget.js + .gz.
  size-check.mjs      Fails if dist/foyer-widget.js.gz > 30 KB.
  package.json        Preact runtime, esbuild + tsc dev.
  tsconfig.json       Strict, noUncheckedIndexedAccess.
  dist/               Build output. Gitignored.
```
