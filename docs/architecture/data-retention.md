# Data retention

What Foyer stores, how long, and what happens on a STOP or a deletion
request. The numbers are deliberately conservative for a portfolio
demo; a production deployment would size against the buyer's state-level
privacy regime, insurance posture, and carrier requirements.

## Hot vs cold

Hot data is in the live Postgres database, indexed for lookup. Cold
data is in S3 as JSON exports under business-keyed prefixes — readable
with a restore script, not a query.

## The table

| Table                                              | Hot retention | After hot                                                                |
| -------------------------------------------------- | ------------- | ------------------------------------------------------------------------ |
| `messages.text` + `messages.attachments`           | 18 months     | Anonymized in place — phone hashed, text scrubbed. Photos deleted from S3. |
| `conversations`                                    | 18 months     | Archived to S3 cold, kept 3 years.                                       |
| `bookings`                                         | 3 years       | Archived to S3 cold, kept 7 years.                                       |
| `audit_log`                                        | 1 year        | Archived to S3 cold, kept 7 years.                                       |
| `consent_state` + `consent_changes`                | Indefinite    | Required for TCPA dispute defense; never deleted.                        |
| `slot_holds`                                       | 90 days       | Hard-deleted (no archival).                                              |
| `out_of_scope_log`                                 | 12 months     | Hard-deleted.                                                            |
| `llm_cost_daily`                                   | 24 months     | Hard-deleted.                                                            |
| `idempotency_keys`                                 | 24 hours      | Hard-deleted by TTL job.                                                 |

## STOP-driven suppression

When a customer texts STOP, the consent state for
`(customer_phone, twilio_number)` flips to `stopped`. Effects:

- Outbound on that pair is permanently suppressed. No marketing
  follow-up, no "just one more" message, no automated re-engagement.
- The widget on the customer-facing site receives a `consent_blocked`
  response on its first message and renders a static notice asking the
  visitor to contact the business directly.
- Cross-channel resume from web to SMS is refused even with valid OTP
  — the STOP wins.
- The only thing that flips the state back is a customer-initiated
  START reply on the same number pair.

## Anonymization on deletion request

A documented deletion request triggers the anonymization path. The
operator runs:

```
php artisan foyer:anonymize-customer \
  --phone=+13035550142 \
  --business=01J4YQX9MA0RBPV6N7K8WJ6ANCH \
  --reason="customer request, ticket #..."
```

The job:

1. Replaces the phone in `messages.customer_phone_e164` and
   `conversations.customer_phone_e164` with a per-business salted
   SHA-256 hash.
2. Replaces `messages.text` with the phase marker. Intents (structured
   fields) are kept; free text is gone.
3. Deletes the photo S3 objects referenced from `messages.attachments`;
   nulls out the keys in the JSON.
4. Truncates `bookings.address`, `bookings.address_lat`,
   `bookings.address_lng` to ZIP-3 + city granularity.
5. Writes an `audit_log` entry with the operator, the reason, the
   number of affected rows. The audit entry is itself not anonymized.

**Consent records are preserved.** TCPA dispute defense requires Foyer
to be able to demonstrate when consent was given and when it was
withdrawn. Anonymizing them would defeat the only legal protection the
business has if a complaint arrives. The customer's anonymization
request explicitly does not extend to `consent_state` or
`consent_changes`; the runbook says so.

## Photo storage specifics

Customer-supplied photos (MMS or web upload) move through:

1. Content-Length cap check before reading body (MMS 5 MB, web 10 MB).
2. Content-Type allowlist: `image/jpeg`, `image/png`, `image/heic`,
   `image/webp`.
3. Stream to the `photo-sanitize` queue worker.
4. Re-encode through Intervention\Image to JPEG. EXIF stripped.
5. Random UUIDv7 S3 key: `s3://foyer-photos/{business_id}/{uuid}.jpg`,
   private ACL.

The owner dashboard displays via pre-signed URLs with a 5-minute TTL.
Photos count under the `messages.attachments` retention clock and are
deleted from S3 at anonymization time.

## Backups

Nightly Postgres dumps land in the S3 backup bucket, encrypted server-
side with AWS-managed KMS. Retention is 30 days.

A backup is, by definition, a copy of the hot database at the time of
the dump. Anonymization applied to the live row does not retroactively
scrub historical backups. The runbook spells out the trade-off
explicitly so the buyer understands:

- Pre-anonymization snapshots are kept until their natural 30-day
  expiry.
- Backups can be re-cut after anonymization if a stricter posture is
  required.
- The S3 object lock policy can be tightened from 30 days to a shorter
  window if the buyer's retention requirements demand it.

## Owner data

Owner accounts, businesses, and scope configuration are kept for the
life of the engagement plus 90 days for dispute defense. Owner OAuth
refresh tokens are revoked when the engagement ends; the Calendar API
side reflects that within hours.

## What this floor is

Conservative defaults for a portfolio demo. Not legal advice. A real
engagement would consult counsel and size against the buyer's actual
regulatory surface.
