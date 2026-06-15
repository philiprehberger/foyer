import Link from "next/link";
import { DOC_LINKS, DocsLayout } from "../../components/DocsLayout";

export const metadata = {
  title: "Docs",
};

export default function DocsIndex() {
  const groups = Array.from(new Set(DOC_LINKS.map((l) => l.group ?? "")));
  return (
    <DocsLayout
      current="/docs"
      title="Docs"
      description="What Foyer is, how to wire it to a Twilio number and a Google Calendar, how the scope guard works, and what to do when something drifts. Skim the overview first, then jump in wherever the work is."
    >
      <p>
        Foyer is a conversational booking agent for one-shop service businesses
        — plumber, cleaner, dog walker. SMS plus an embedded web widget. The
        agent collects what it needs, proposes a slot inside business hours,
        holds it against a Postgres exclusion constraint for fifteen minutes,
        and waits for the owner to confirm before locking it in Google Calendar.
        Out-of-scope and out-of-hours requests hit a documented fallback.
      </p>
      <p>
        The docs assume you have a Laravel-shaped backend, a Twilio number you
        can register on a 10DLC campaign, and a Google account whose calendar
        the agent should book against. The Quickstart walks the full path; the
        rest of the pages drill in where you need them.
      </p>

      <h2>What to read first</h2>
      <ul>
        <li>
          <Link href="/docs/quickstart">Quickstart</Link> — the half-hour path
          from cloned repo to a working SMS booking flow.
        </li>
        <li>
          <Link href="/docs/twilio-setup">Twilio setup</Link> and{" "}
          <Link href="/docs/twilio-10dlc">10DLC walkthrough</Link> — what
          carriers need before they will deliver your messages.
        </li>
        <li>
          <Link href="/docs/google-calendar">Google Calendar</Link> — OAuth
          scoped to <code>calendar.events</code> only.
        </li>
        <li>
          <Link href="/docs/scope-guardrails">Scope guardrails</Link> — how the
          agent refuses things outside what you have configured.
        </li>
      </ul>

      <h2>All sections</h2>
      {groups.map((g) => (
        <div key={g}>
          <h3>{g}</h3>
          <ul>
            {DOC_LINKS.filter(
              (l) => (l.group ?? "") === g && l.href !== "/docs",
            ).map((l) => (
              <li key={l.href}>
                <Link href={l.href}>{l.label}</Link>
              </li>
            ))}
          </ul>
        </div>
      ))}
    </DocsLayout>
  );
}
