import { DocsLayout } from "../../../components/DocsLayout";

export const metadata = { title: "Versioning policy" };

export default function Versioning() {
  return (
    <DocsLayout
      current="/docs/versioning"
      title="Versioning policy"
      description="What changes inside v1, what causes a v2, and what the overlap window looks like. The policy is conservative on purpose — once an SDK pins a major version, breaking it is a non-zero cost for someone."
    >
      <h2>Major versions in the URL path</h2>
      <p>
        Every public endpoint lives under a <code>/v&lt;N&gt;/</code> prefix.
        The current major is <code>v1</code>. A major bump moves <em>every</em>{" "}
        endpoint to <code>/v2/</code>; we do not partially version. Callers
        always know which contract they are talking to from the URL alone.
      </p>

      <h2>What ships inside v1</h2>
      <p>
        Additive changes are not breaking and ship within <code>v1</code>:
      </p>
      <ul>
        <li>New endpoints.</li>
        <li>New optional request fields.</li>
        <li>New response fields (clients ignore unknown keys).</li>
        <li>New enum values, when accompanied by a documented default-handling rule.</li>
        <li>New error <code>type</code> URIs inside the existing problem-document shape.</li>
        <li>Performance, observability, and behavior changes that do not change the wire shape.</li>
      </ul>

      <h2>What forces v2</h2>
      <p>
        A breaking change is anything that would cause an existing client
        running against <code>v1</code> to behave incorrectly:
      </p>
      <ul>
        <li>Removing or renaming a field.</li>
        <li>Tightening the type or pattern of an existing field.</li>
        <li>Removing or renaming an endpoint.</li>
        <li>Changing the semantics of an existing field or status code.</li>
        <li>Changing the shape of error responses.</li>
        <li>Changing the auth scheme or the required headers.</li>
      </ul>
      <p>
        Anything in this list ships as <code>/v2/</code> with the parallel
        endpoints live, not as a mutation of <code>/v1/</code>.
      </p>

      <h2>Overlap window</h2>
      <p>
        When <code>v2</code> ships, <code>v1</code> stays live for at least six
        months. During the overlap:
      </p>
      <ul>
        <li>
          Both versions are served from the same hosts. SDKs pinned to{" "}
          <code>v1</code> continue to work without changes.
        </li>
        <li>
          Every <code>v1</code> response carries a <code>Sunset</code> header
          with the RFC 8594 date at which <code>v1</code> will be removed.
        </li>
        <li>
          Every <code>v1</code> response carries a <code>Deprecation</code>{" "}
          header set to the date <code>v2</code> shipped.
        </li>
        <li>
          The changelog spells out the field-by-field migration from{" "}
          <code>v1</code> to <code>v2</code>.
        </li>
      </ul>

      <h2>SDK versioning</h2>
      <p>
        The SDKs follow semver and pin the major they target. A new major of
        the API is a new major of the SDK; the prior SDK major continues to be
        published and patched for the overlap window.
      </p>

      <h2>Internal-API versioning</h2>
      <p>
        The internal API (the loopback-bound HMAC seam) versions independently
        from the public surface. The <code>AgentTurn</code> job and the agent
        response both carry a <code>schema_version</code> integer. Producer
        and consumer reject anything they do not understand. CI gates schema
        parity between the PHP and Python codegen, so a drift surfaces in
        review, not in production.
      </p>

      <h2>Webhook / status-callback versioning</h2>
      <p>
        Twilio and Google webhook handlers do not have a Foyer-controlled
        contract — they follow whatever those vendors publish. Foyer keeps the
        translation layer in one place (a thin adapter) so a vendor-side
        change is a one-file edit.
      </p>

      <h2>What changes do not force a major</h2>
      <p>
        Adding rate-limit headers, tightening server-side validation that was
        already documented as required, fixing a bug where the response did
        not match the OpenAPI spec — these are not breaking changes even when
        a misbehaving client would notice. The OpenAPI spec is the source of
        truth; aligning behavior with the spec is not a contract break.
      </p>
    </DocsLayout>
  );
}
