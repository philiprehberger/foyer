import { DocsLayout } from "../../../components/DocsLayout";

export const metadata = { title: "Data retention" };

export default function DataRetention() {
  return (
    <DocsLayout
      current="/docs/data-retention"
      title="Data retention"
      description="What Foyer stores, how long, and what happens on a STOP or a deletion request. The numbers are deliberately conservative for a portfolio demo; a real engagement would size them against the buyer's actual regulatory posture."
    >
      <h2>What is hot and what is cold</h2>
      <p>
        Hot data is in the live Postgres database, indexed for lookup. Cold
        data is in S3 as JSON exports under business-keyed prefixes — readable
        with a restore script, not a query.
      </p>
      <table>
        <thead>
          <tr>
            <th>Table</th>
            <th>Hot retention</th>
            <th>After</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><code>messages</code> (text + attachments)</td>
            <td>18 months</td>
            <td>Anonymized in place — phone hashed, text scrubbed. Photos deleted from S3.</td>
          </tr>
          <tr>
            <td><code>conversations</code></td>
            <td>18 months</td>
            <td>Archived to S3, kept 3 years.</td>
          </tr>
          <tr>
            <td><code>bookings</code></td>
            <td>3 years</td>
            <td>Archived to S3, kept 7 years.</td>
          </tr>
          <tr>
            <td><code>audit_log</code></td>
            <td>1 year</td>
            <td>Archived to S3, kept 7 years.</td>
          </tr>
          <tr>
            <td><code>consent_state</code> + <code>consent_changes</code></td>
            <td>Indefinite</td>
            <td>Required for TCPA dispute defense.</td>
          </tr>
        </tbody>
      </table>

      <h2>STOP-driven suppression</h2>
      <p>
        When a customer texts STOP, the consent state for{" "}
        <code>(customer_phone, twilio_number)</code> flips to{" "}
        <code>stopped</code> and outbound on that pair is permanently
        suppressed. No marketing follow-up, no &ldquo;just one more&rdquo;
        message, no retry on START except a customer-initiated{" "}
        START reply (which is the only thing that flips the state back).
      </p>
      <p>
        Suppression applies across channels: a STOP&rsquo;d phone cannot
        resume an SMS conversation via the web widget. The widget receives a{" "}
        <code>consent_blocked</code> response and renders a static notice.
      </p>

      <h2>Anonymization on deletion request</h2>
      <p>
        A documented deletion request triggers the anonymization path:
      </p>
      <ul>
        <li>
          Customer phone in <code>messages</code> and <code>conversations</code> is replaced with a per-business salted SHA-256.
        </li>
        <li>
          Message text in <code>messages</code> is replaced with a phase
          marker. Intents are kept (the structured fields), free text is
          gone.
        </li>
        <li>
          Photo S3 objects are deleted; the S3 keys in <code>messages.attachments</code> are nulled out.
        </li>
        <li>
          Address, lat/lng on <code>bookings</code> are truncated to ZIP-3 +
          city.
        </li>
      </ul>
      <p>
        <strong>Consent records are preserved</strong>. TCPA dispute defense
        requires Foyer to be able to demonstrate when consent was given and
        when it was withdrawn; anonymizing them would defeat the only
        protection the business has against a complaint.
      </p>

      <h2>Photo storage specifics</h2>
      <p>
        Customer-supplied photos (MMS or web upload) are re-encoded to JPEG
        through Intervention\Image, stripped of EXIF, and uploaded under a
        UUIDv7 key with a private ACL: <code>s3://foyer-photos/&lt;business_id&gt;/&lt;uuid&gt;.jpg</code>.
        The owner dashboard displays via pre-signed URLs with a 5-minute TTL.
        Photos count under the <code>messages.attachments</code> retention
        clock and are deleted from S3 at anonymization time.
      </p>

      <h2>Backups</h2>
      <p>
        Nightly Postgres dumps land in the S3 backup bucket, encrypted
        server-side with AWS-managed KMS. Backup retention is 30 days. A
        backup is, by definition, a copy of the hot database — anonymization
        applied to the live row does not retroactively scrub historical
        backups. The deletion-request runbook says so explicitly so the buyer
        understands the trade-off: backups can be re-cut after anonymization,
        but pre-anonymization snapshots are kept until their natural 30-day
        expiry.
      </p>

      <h2>Owner data</h2>
      <p>
        Owner accounts, businesses, and scope configuration are kept for the
        life of the engagement plus 90 days for dispute defense. Owner OAuth
        refresh tokens are revoked when the engagement ends; the Calendar API
        side reflects that within hours.
      </p>

      <h2>What this page is and is not</h2>
      <p>
        This is the floor for a portfolio demo. A real production deployment
        would size retention against the buyer&rsquo;s state-level privacy
        regime (CCPA, CPRA, others), their insurance posture, and any
        carrier-specific requirements. The numbers above are conservative
        defaults — not legal advice.
      </p>
    </DocsLayout>
  );
}
