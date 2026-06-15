import { ScalarReference } from "../../components/ScalarReference";

export const metadata = {
  title: "API reference",
};

export default function ApiPage() {
  return (
    <div className="mx-auto max-w-6xl px-6 py-12">
      <header className="mb-8">
        <p className="text-xs uppercase tracking-widest text-(--color-warm-deep) mb-2">
          OpenAPI 3.1
        </p>
        <h1 className="text-3xl md:text-4xl font-semibold text-(--color-ink) mb-3 tracking-tight">
          API reference
        </h1>
        <p className="text-(--color-ink-dim) max-w-2xl leading-relaxed">
          The public surface — webhook receivers, owner-authenticated
          endpoints, web-session token mint and OTP verification. The spec is
          the source of truth; the controllers are implemented against it,
          not the other way around.
        </p>
      </header>
      <div className="rounded-lg border border-(--color-paper-deep) overflow-hidden bg-white">
        <ScalarReference />
      </div>
    </div>
  );
}
