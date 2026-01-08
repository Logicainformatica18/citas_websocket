interface Props {
  label: string;
}

const badgeStyles: Record<string, string> = {
  "Alta demanda": `
    bg-[#E6F7FB]
    text-[#0A4E61]
    border border-[#1CBCE8]/40

    dark:bg-[#0E3A4A]
    dark:text-slate-200
    dark:border-[#1CBCE8]/30
  `,
  "Alta proyección": `
    bg-[#EEF9F1]
    text-green-700
    border border-green-400/40

    dark:bg-[#123B2A]
    dark:text-green-300
    dark:border-green-500/30
  `,
};

export default function CertificationBadge({ label }: Props) {
  return (
    <span
      className={`
        text-xs font-medium
        px-2 py-1
        rounded-full
        inline-flex items-center

        ${badgeStyles[label] ?? `
          bg-gray-100
          text-gray-700
          border border-gray-300

          dark:bg-[#1B2933]
          dark:text-slate-300
          dark:border-[#334155]
        `}
      `}
    >
      {label}
    </span>
  );
}
