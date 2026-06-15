# Foyer widget

Embedded chat widget — Preact + Shadow DOM, ≤ 30 KB gzipped.

## Embed

```html
<script
    src="https://foyer.philiprehberger.com/widget.js"
    data-foyer-business-id="01HZ..."
    data-foyer-position="bottom-right"
    data-foyer-theme="auto">
</script>
```

Required attributes:

- `data-foyer-business-id` — the ULID of the business the widget belongs to.

Optional attributes:

- `data-foyer-api-base` — override the API base URL (default
  `https://api.foyer.philiprehberger.com`).
- `data-foyer-position` — `bottom-right` (default) or `bottom-left`.
- `data-foyer-theme` — `auto` (default), `light`, or `dark`.

## Build

```sh
npm install
npm run build           # writes dist/foyer-widget.js + .gz
npm run size-check      # fails if dist/foyer-widget.js.gz > 30 KB
```

CI runs both `build` + `size-check` as the bundle-size gate.

## Shadow DOM isolation

The widget renders into a Shadow DOM root mounted on a single `<div
id="foyer-widget-host">` appended to `<body>`. Host-page CSS does not leak
in; widget CSS does not leak out. `:host { all: initial }` resets any
inherited typography.

## What ships in Phase 1

- Boot, Shadow DOM mount, position + theme reading
- Open/close panel
- Stubbed message input (no transport yet)

## What lands in Phase 6

- Server-issued session-token mint (`POST /v1/web/sessions`)
- Idempotent `POST /v1/web/inbound` with UUIDv7 message IDs
- OTP cross-channel resume flow
- File upload (photos)
- Reconnect / retry on transient failure
- Vitest unit tests + Playwright cross-host (Webflow / Squarespace / Wix
  sandboxes) integration tests
