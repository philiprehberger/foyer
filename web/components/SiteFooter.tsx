import Link from "next/link";

export function SiteFooter() {
  return (
    <footer className="mt-24 border-t border-(--color-paper-dim) py-12">
      <div className="mx-auto max-w-6xl px-6 grid grid-cols-2 md:grid-cols-4 gap-8 text-sm">
        <div className="col-span-2">
          <p className="text-(--color-ink) font-semibold mb-2">Foyer</p>
          <p className="text-(--color-ink-dim) max-w-md leading-relaxed">
            Conversational booking agent for local-service businesses — SMS plus
            embedded web chat, fifteen-minute slot hold, human-confirm gate
            before any calendar lock. A portfolio demo by{" "}
            <Link
              href="https://philiprehberger.com"
              className="text-(--color-warm-deep) hover:underline underline-offset-4"
            >
              Philip Rehberger
            </Link>
            . Production-shaped, not production-grade.
          </p>
        </div>
        <div>
          <p className="text-(--color-ink) font-medium mb-3">Product</p>
          <ul className="space-y-2 text-(--color-ink-dim)">
            <li><FooterLink href="/demo">Live demo</FooterLink></li>
            <li><FooterLink href="/docs">Docs</FooterLink></li>
            <li><FooterLink href="/docs/quickstart">Quickstart</FooterLink></li>
            <li><FooterLink href="/api">API reference</FooterLink></li>
          </ul>
        </div>
        <div>
          <p className="text-(--color-ink) font-medium mb-3">Project</p>
          <ul className="space-y-2 text-(--color-ink-dim)">
            <li>
              <FooterLink href="https://github.com/philiprehberger/foyer">
                GitHub
              </FooterLink>
            </li>
            <li><FooterLink href="/docs/versioning">Versioning</FooterLink></li>
            <li><FooterLink href="/docs/data-retention">Data retention</FooterLink></li>
            <li>
              <FooterLink href="https://scopeforged.com">ScopeForged</FooterLink>
            </li>
          </ul>
        </div>
      </div>
    </footer>
  );
}

function FooterLink({
  href,
  children,
}: {
  href: string;
  children: React.ReactNode;
}) {
  return (
    <Link
      href={href}
      className="hover:text-(--color-ink) transition-colors no-underline"
    >
      {children}
    </Link>
  );
}
