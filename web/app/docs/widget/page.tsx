import Link from "next/link";
import { CodeBlock } from "../../../components/CodeBlock";
import { DocsLayout } from "../../../components/DocsLayout";

export const metadata = { title: "Embed the widget" };

export default function Widget() {
  return (
    <DocsLayout
      current="/docs/widget"
      title="Embed the widget"
      description="One script tag, Shadow-DOM isolated, under 30 KB gzipped. Position-configurable, theme-configurable, host-page-CSS-immune. Cross-channel resume requires phone OTP — number-match alone is a spoofing vector."
    >
      <h2>Drop it in</h2>
      <p>
        The widget mounts itself on <code>DOMContentLoaded</code>, reads its
        config from <code>data-*</code> attributes on its own script tag, and
        attaches a Shadow DOM root to <code>document.body</code>. Host-page
        CSS cannot leak into the widget and the widget&rsquo;s styles cannot
        leak out.
      </p>
      <CodeBlock language="html">
{`<script
  src="https://foyer.philiprehberger.com/widget.js"
  data-foyer-business-id="01J4YQX9MA0RBPV6N7K8WJ6XYZ"
  data-foyer-position="bottom-right"
  data-foyer-theme="auto"
  async
></script>`}
      </CodeBlock>

      <h2>Configuration</h2>
      <table>
        <thead>
          <tr>
            <th>Attribute</th>
            <th>Values</th>
            <th>Default</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><code>data-foyer-business-id</code></td>
            <td>ULID — your business&rsquo;s ID</td>
            <td>required</td>
          </tr>
          <tr>
            <td><code>data-foyer-position</code></td>
            <td><code>bottom-right</code>, <code>bottom-left</code></td>
            <td><code>bottom-right</code></td>
          </tr>
          <tr>
            <td><code>data-foyer-theme</code></td>
            <td><code>auto</code>, <code>light</code>, <code>dark</code></td>
            <td><code>auto</code></td>
          </tr>
        </tbody>
      </table>

      <h2>What the widget does</h2>
      <ol>
        <li>
          POSTs to <code>/v1/web/sessions</code> with the business ID and gets
          back a short-lived session token bound to the visitor&rsquo;s IP.
        </li>
        <li>
          Shows a launcher button. On click, opens a chat panel and POSTs each
          customer message to <code>/v1/web/inbound</code> with a
          client-generated <code>widget_message_id</code> (UUIDv7) for
          idempotency.
        </li>
        <li>
          Streams agent replies back. Server-sent events for the panel;
          fallback short-poll if SSE drops.
        </li>
      </ol>

      <h2>Cross-channel resume</h2>
      <p>
        If the visitor has texted the business before from this phone number,
        the web session can resume the SMS conversation — but only after OTP
        verification. The widget collects the phone, calls{" "}
        <code>/v1/web/sessions/:id/verify-phone</code> to issue a 6-digit code,
        the customer types it back, and only then does the web session bind to
        the SMS conversation. Phone-number match alone is a spoofing vector
        and is not accepted as resume auth.
      </p>

      <h2>Consent</h2>
      <p>
        If the visitor&rsquo;s phone is STOP&rsquo;d on this Twilio number, the
        widget receives a <code>consent_blocked</code> response on its first
        message and renders a static notice asking the visitor to contact the
        business directly. The widget will not resume a STOP&rsquo;d SMS
        conversation, and will not attempt cross-channel outbound.
      </p>

      <h2>CSS isolation guarantees</h2>
      <p>
        Foyer&rsquo;s widget uses an open Shadow DOM root so a debugger can
        still inspect the tree, but no host-page <code>!important</code>{" "}
        selector will reach inside. The widget ships its own minimal CSS reset
        and renders all interactive surfaces inside the shadow root. Tested
        against Webflow, Squarespace, and Wix sandbox themes — none of which
        could break it.
      </p>

      <h2>Bundle size</h2>
      <p>
        The widget is hard-capped at 30 KB gzipped. A CI gate runs the gzipped
        size of <code>dist/foyer-widget.js</code> against the cap on every PR;
        the build fails if the cap is exceeded. The current floor is well
        under that — Preact plus the chat panel, OTP prompt, and transport are
        the only payload.
      </p>

      <p>
        See the widget&rsquo;s own{" "}
        <Link href="https://github.com/philiprehberger/foyer/tree/main/widget">
          README in the repo
        </Link>{" "}
        for the local dev setup.
      </p>
    </DocsLayout>
  );
}
