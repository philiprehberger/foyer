import { CodeBlock } from "../../../components/CodeBlock";
import { DocsLayout } from "../../../components/DocsLayout";

export const metadata = { title: "Google Calendar" };

export default function GoogleCalendar() {
  return (
    <DocsLayout
      current="/docs/google-calendar"
      title="Google Calendar"
      description="OAuth scoped to calendar.events only. Tentative hold first, confirmed lock on owner approval, push-channel reconciliation when the owner edits the event from their phone."
    >
      <h2>Why calendar.events and not the full scope</h2>
      <p>
        Foyer reads and writes calendar events on a single calendar — the one
        the business books against. It does not need to enumerate the
        owner&rsquo;s calendar list, change ACLs, or touch other calendars.
        Requesting <code>https://www.googleapis.com/auth/calendar.events</code>{" "}
        is the minimum scope that lets the agent create, update, and delete
        events on a specific calendar by ID. Anything broader is a consent
        screen the owner will refuse, justifiably.
      </p>

      <h2>1. Create the OAuth client</h2>
      <p>
        Google Cloud Console → APIs &amp; Services → Credentials → Create
        Credentials → OAuth 2.0 Client ID. Application type: Web application.
        Authorized redirect URIs:
      </p>
      <CodeBlock language="text">
{`https://api.foyer.example.com/v1/google/oauth/callback`}
      </CodeBlock>
      <p>
        Drop the client ID and secret into <code>.env</code>:
      </p>
      <CodeBlock language="bash">
{`GOOGLE_CLIENT_ID=...apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=https://api.foyer.example.com/v1/google/oauth/callback`}
      </CodeBlock>

      <h2>2. Enable the APIs</h2>
      <p>
        APIs &amp; Services → Library → enable both Google Calendar API and
        Google Geocoding API. Geocoding is what validates the customer&rsquo;s
        address against the service area before the agent proposes a slot. The
        Geocoding API key goes in <code>.env</code> separately as{" "}
        <code>GOOGLE_GEOCODING_KEY</code> — it is a server key, not an OAuth
        credential.
      </p>

      <h2>3. Owner consent flow</h2>
      <p>
        From the Filament admin, the owner clicks &ldquo;Connect Google
        Calendar.&rdquo; Foyer redirects to Google&rsquo;s consent screen with
        <code>scope=https://www.googleapis.com/auth/calendar.events</code> and
        <code>access_type=offline</code> so the refresh token is returned. The
        callback handler exchanges the code, stores the refresh token via a
        KMS-pointer reference rather than plaintext, and asks the owner to
        pick which calendar to use.
      </p>

      <h2>4. Slot search</h2>
      <p>
        The agent calls <code>events.list</code> with the business-hours +
        quiet-hours + lead-time window, then conflicts the result against
        Foyer&rsquo;s own <code>slot_holds</code> and <code>bookings</code>{" "}
        tables. The agent only proposes slots that are clear in both — never
        relies solely on Calendar, because a concurrent customer might have
        already taken the hold inside Foyer in the same millisecond.
      </p>

      <h2>5. The hold</h2>
      <p>
        When the agent proposes a slot and the customer accepts, Foyer
        inserts a row into <code>slot_holds</code> (the Postgres exclusion
        constraint enforces no overlap) and creates a tentative Calendar event
        in the same job. If the Calendar API call fails, the DB hold is
        released within the same job — no orphans. The event title is{" "}
        <code>Foyer hold — pending</code>, transparency is opaque so the slot
        looks busy in the owner&rsquo;s view.
      </p>

      <h2>6. The lock</h2>
      <p>
        When the owner confirms in the Filament inbox, Foyer updates the
        Calendar event title to the real one, attaches the customer details to
        the description, and the booking row transitions to confirmed. The
        confirm endpoint requires an <code>Idempotency-Key</code> header — a
        double-click sends one Calendar update and one SMS, not two.
      </p>

      <h2>7. Drift detection</h2>
      <p>
        If the owner moves or deletes a Foyer-managed event from their phone,
        Foyer needs to know. The setup includes a calendar push channel
        subscription via <code>events.watch</code>; pushes land at{" "}
        <code>POST /v1/google/calendar-push</code> and trigger a reconciliation
        job. If the watch channel expires (Google rotates them every 30 days
        max), a five-minute fallback poll catches the gap.
      </p>
      <p>
        The owner dashboard surfaces a sync-health indicator if drift exceeds
        threshold — the only honest answer to &ldquo;is the calendar
        accurate?&rdquo; is &ldquo;yes, and here is the last time we
        confirmed.&rdquo;
      </p>

      <h2>Token refresh</h2>
      <p>
        Access tokens last an hour. Foyer refreshes ten minutes before expiry
        using the stored refresh token. If the refresh fails — the owner
        revoked access, or Google invalidated the token — the business is
        flagged in the admin, agent dispatch stops on that business, and the
        owner is asked to reconnect.
      </p>
    </DocsLayout>
  );
}
