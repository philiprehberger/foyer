type Props = {
  language?: string;
  children: string;
};

export function CodeBlock({ language, children }: Props) {
  return (
    <pre className="not-prose rounded-lg bg-(--color-ink) text-[#f3ece0] p-4 overflow-x-auto text-[13px] leading-[1.55] font-mono my-4">
      <code data-lang={language}>{children}</code>
    </pre>
  );
}
