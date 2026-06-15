# Foyer — web

Next.js 16 docs and marketing site for Foyer. Tailwind 4, React 19, output
standalone for the rsync + PM2 reload deploy. Hosts:

- `foyer.philiprehberger.com` — this site.

## Local dev

```bash
npm install
npm run dev
# http://localhost:3000
```

## Build

```bash
npm run build
npm run start
```

## Deploy

```bash
npm run deploy
```

The deploy script (`@philiprehberger/next-deploy`) reads
`deploy.config.js`, rsyncs the standalone output to
`/var/www/foyer-web/` on the EC2 host, and reloads the `foyer-web` PM2
process bound to port 3006.

## Layout

```
web/
  app/
    page.tsx              Landing.
    demo/page.tsx         Embedded widget + read-only owner inbox mockup.
    api/page.tsx          Scalar-rendered OpenAPI reference.
    docs/                 Quickstart, Twilio, Calendar, scope, internal API,
                          operations, data retention, versioning.
  components/             Hero, HowItWorks, WedgeBanner, Pricing, Faq, etc.
  public/openapi/         Copy of /openapi/spec.yaml served at runtime.
  infra/apache/           Reverse-proxy vhost. (At repo root, not here.)
```
