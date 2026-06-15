import Link from "next/link";

export type DocLink = { href: string; label: string; group?: string };

export const DOC_LINKS: DocLink[] = [
  { group: "Start here", href: "/docs", label: "Overview" },
  { group: "Start here", href: "/docs/quickstart", label: "Quickstart" },
  { group: "Channels", href: "/docs/twilio-setup", label: "Twilio setup" },
  { group: "Channels", href: "/docs/twilio-10dlc", label: "Twilio 10DLC" },
  { group: "Channels", href: "/docs/widget", label: "Embed the widget" },
  { group: "Integrations", href: "/docs/google-calendar", label: "Google Calendar" },
  { group: "Safety", href: "/docs/scope-guardrails", label: "Scope guardrails" },
  { group: "Safety", href: "/docs/data-retention", label: "Data retention" },
  { group: "Architecture", href: "/docs/internal-api", label: "Internal API" },
  { group: "Operations", href: "/docs/operations", label: "Operations runbook" },
  { group: "Operations", href: "/docs/versioning", label: "Versioning policy" },
];

export function DocsLayout({
  current,
  title,
  description,
  children,
}: {
  current: string;
  title: string;
  description?: string;
  children: React.ReactNode;
}) {
  const groups = Array.from(new Set(DOC_LINKS.map((l) => l.group ?? "")));
  return (
    <div className="mx-auto max-w-6xl px-6 py-12 grid md:grid-cols-[220px_1fr] gap-10">
      <aside className="text-sm md:sticky md:top-6 md:self-start">
        {groups.map((group) => (
          <div key={group} className="mb-6">
            <p className="text-xs uppercase tracking-widest text-(--color-ink-faint) mb-2">
              {group}
            </p>
            <ul className="space-y-1">
              {DOC_LINKS.filter((l) => (l.group ?? "") === group).map((l) => (
                <li key={l.href}>
                  <Link
                    href={l.href}
                    className={
                      "no-underline block px-2 py-1 rounded " +
                      (current === l.href
                        ? "bg-(--color-paper-dim) text-(--color-ink) font-medium"
                        : "text-(--color-ink-dim) hover:bg-(--color-paper-dim)/60 hover:text-(--color-ink)")
                    }
                  >
                    {l.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </aside>
      <article className="min-w-0">
        <header className="mb-6">
          <h1 className="text-3xl font-bold text-(--color-ink) mb-2 tracking-tight">
            {title}
          </h1>
          {description && (
            <p className="text-(--color-ink-dim) text-base leading-relaxed">
              {description}
            </p>
          )}
        </header>
        <div className="prose">{children}</div>
      </article>
    </div>
  );
}
