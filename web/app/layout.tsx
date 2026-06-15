import type { Metadata } from "next";
import { SiteFooter } from "../components/SiteFooter";
import { SiteHeader } from "../components/SiteHeader";
import "./globals.css";

export const metadata: Metadata = {
  title: {
    default: "Foyer — Conversational booking agent for local-service businesses",
    template: "%s — Foyer",
  },
  description:
    "SMS plus embedded web chat. Fifteen-minute slot hold against a Postgres exclusion constraint. Human-confirm gate before any calendar lock. A portfolio demo by Philip Rehberger.",
  robots: { index: false, follow: false },
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" className="h-full antialiased">
      <body className="min-h-full flex flex-col">
        <SiteHeader />
        <main className="flex-1">{children}</main>
        <SiteFooter />
      </body>
    </html>
  );
}
