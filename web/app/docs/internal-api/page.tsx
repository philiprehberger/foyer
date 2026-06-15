import { CodeBlock } from "../../../components/CodeBlock";
import { DocsLayout } from "../../../components/DocsLayout";

export const metadata = { title: "Internal API" };

export default function InternalApi() {
  return (
    <DocsLayout
      current="/docs/internal-api"
      title="Internal API"
      description="The loopback-bound HMAC seam between Laravel and the FastAPI agent worker. Three endpoints. Bound to 127.0.0.1 only. Every request signed. The full contract document lives at docs/architecture/internal-api.md in the repo."
    >
      <h2>Where the full contract lives</h2>
      <p>
        The authoritative document is at{" "}
        <code>docs/architecture/internal-api.md</code> in the source tree. The
        JSON Schemas for the <code>AgentTurn</code> job and the agent response
        live at <code>infra/contracts/agent-turn.schema.json</code> and{" "}
        <code>infra/contracts/agent-turn-response.schema.json</code>; both PHP
        and Python code-generate from the same schemas and CI gates parity.
      </p>

      <h2>The shape</h2>
      <p>
        The FastAPI agent worker calls back into Laravel for two things: load
        the turn context and post the result. A third endpoint exists for
        escalation when the agent gives up.
      </p>
      <table>
        <thead>
          <tr>
            <th>Method</th>
            <th>Path</th>
            <th>Used by</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>GET</td>
            <td><code>/_internal/conversations/:id/turn-context</code></td>
            <td>FastAPI loads recent messages, scope, persona, current phase.</td>
          </tr>
          <tr>
            <td>POST</td>
            <td><code>/_internal/conversations/:id/turn-result</code></td>
            <td>FastAPI posts the LLM output: phase, reply, intents, tokens, cost.</td>
          </tr>
          <tr>
            <td>POST</td>
            <td><code>/_internal/conversations/:id/escalate</code></td>
            <td>FastAPI gives up after structured-output retries; switch to human handoff.</td>
          </tr>
        </tbody>
      </table>

      <h2>HMAC signature</h2>
      <p>
        Every request carries an <code>X-Foyer-Internal-Sig</code> header that
        is the HMAC-SHA256 of the raw request body under a shared secret. The
        secret lives in environment as <code>FOYER_INTERNAL_SECRET</code> and
        is the same value on both sides. Constant-time comparison on receive.
      </p>
      <CodeBlock language="python">
{`# In the FastAPI worker
import hashlib, hmac, os

def sign(body: bytes) -> str:
    secret = os.environ["FOYER_INTERNAL_SECRET"].encode()
    return hmac.new(secret, body, hashlib.sha256).hexdigest()`}
      </CodeBlock>
      <CodeBlock language="php">
{`# In Laravel middleware
$expected = hash_hmac('sha256', $request->getContent(), config('foyer.internal_secret'));
if (! hash_equals($expected, $request->header('X-Foyer-Internal-Sig'))) {
    abort(403, 'invalid signature');
}`}
      </CodeBlock>

      <h2>Loopback binding</h2>
      <p>
        The internal route group is registered behind two middlewares —{" "}
        <code>internal.loopback</code> and <code>internal.hmac</code>. The
        first rejects with 403 if the request did not originate from{" "}
        <code>127.0.0.1</code> or <code>::1</code>; the second validates the
        signature. Both fail closed. The router does not expose these routes
        to the public webserver — the Apache vhost on the API host does not
        proxy <code>/_internal</code>, so even a misconfiguration is one extra
        check away from a leak.
      </p>

      <h2>Error responses</h2>
      <p>
        RFC 7807 problem documents, consistent with the public surface:
      </p>
      <CodeBlock language="json">
{`{
  "type": "https://foyer.philiprehberger.com/problems/invalid-signature",
  "title": "Invalid internal signature",
  "status": 403,
  "detail": "X-Foyer-Internal-Sig header did not match the computed signature."
}`}
      </CodeBlock>
      <p>
        The agent worker treats any 4xx as terminal — do not retry on
        signature mismatch, the secret is misconfigured. 5xx is retryable with
        exponential backoff.
      </p>

      <h2>Why this seam is the most failure-prone part</h2>
      <p>
        Two processes, two languages, one DB, one queue. The internal API is
        the single seam where a contract drift, a clock skew, or a misrouted
        request can fail silently. The defenses are layered — schema
        versioning on the job, schema codegen on both sides, contract tests in
        CI, HMAC on every request, loopback binding, RFC 7807 errors — because
        any one of them on its own is too thin a guard. The doc at{" "}
        <code>docs/architecture/internal-api.md</code> is the place to read
        before changing anything across the seam.
      </p>
    </DocsLayout>
  );
}
