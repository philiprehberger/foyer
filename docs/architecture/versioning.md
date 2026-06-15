# Versioning policy

What changes inside v1, what causes a v2, and what the overlap window
looks like. The policy is conservative on purpose — once an SDK pins a
major version, breaking it is a non-zero cost for someone.

## Major versions in the URL path

Every public endpoint lives under a `/v<N>/` prefix. The current major
is `v1`. A major bump moves *every* endpoint to `/v2/`; we do not
partially version. Callers always know which contract they are talking
to from the URL alone.

## What ships inside v1

Additive changes are not breaking and ship within `v1`:

- New endpoints.
- New optional request fields.
- New response fields (clients ignore unknown keys).
- New enum values, when accompanied by a documented default-handling
  rule.
- New error `type` URIs inside the existing problem-document shape.
- Performance, observability, and behavior changes that do not change
  the wire shape.

## What forces v2

A breaking change is anything that would cause an existing client
running against `v1` to behave incorrectly:

- Removing or renaming a field.
- Tightening the type or pattern of an existing field.
- Removing or renaming an endpoint.
- Changing the semantics of an existing field or status code.
- Changing the shape of error responses.
- Changing the auth scheme or the required headers.

Anything in this list ships as `/v2/` with parallel endpoints live, not
as a mutation of `/v1/`.

## Overlap window

When `v2` ships, `v1` stays live for at least six months. During the
overlap:

- Both versions are served from the same hosts. SDKs pinned to `v1`
  continue to work without changes.
- Every `v1` response carries a `Sunset` header with the RFC 8594 date
  at which `v1` will be removed.
- Every `v1` response carries a `Deprecation` header set to the date
  `v2` shipped.
- The changelog spells out the field-by-field migration from `v1` to
  `v2`.

## SDK versioning

The SDKs follow semver and pin the major they target. A new major of
the API is a new major of the SDK; the prior SDK major continues to be
published and patched for the overlap window.

## Internal-API versioning

The internal API (the loopback-bound HMAC seam) versions independently
from the public surface. The `AgentTurn` job and the agent response
both carry a `schema_version` integer. Producer and consumer reject
anything they do not understand. CI gates schema parity between the
PHP and Python codegen, so a drift surfaces in review, not in
production.

When the internal-API schema bumps, both sides ship together in the
same release window. There is no overlap window for the internal API
because it has exactly one producer and one consumer, both deployed
together.

## Webhook / status-callback versioning

Twilio and Google webhook handlers do not have a Foyer-controlled
contract — they follow whatever those vendors publish. Foyer keeps the
translation layer in one place (a thin adapter under
`connectors/twilio/` and `connectors/google-calendar/`) so a vendor-
side change is a one-file edit. Public Foyer endpoints that consume
vendor webhooks are versioned only in the sense that they map vendor
fields to a documented internal shape; the vendor side moves at its own
cadence.

## What changes do not force a major

Adding rate-limit headers, tightening server-side validation that was
already documented as required, fixing a bug where the response did
not match the OpenAPI spec — these are not breaking changes even when
a misbehaving client would notice. The OpenAPI spec is the source of
truth; aligning behavior with the spec is not a contract break.

## Pre-1.0 caveat

While Foyer is pre-1.0 (the current state at the time of this writing),
the policy above is the intent. Pre-1.0 specifically:

- Breaking changes inside `v1` may happen on a documented two-week
  window with email to all known SDK consumers.
- The changelog records each pre-1.0 break.
- Tagging `v1.0.0` formally activates the policy above, and from that
  point breaking changes only ship as `v2`.

The point of the pre-1.0 carve-out is to allow the API shape to settle
before locking it. The point of the post-1.0 lock is to make the SDK
contract worth pinning to.
