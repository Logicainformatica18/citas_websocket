interface Props {
  label: string;
}

const badgeStyles: Record<string, string> = {
  "Alta demanda":
    "bg-[#E6F7FB] text-[#0A4E61] border border-[#1CBCE8]/40",
  "Alta proyección":
    "bg-[#EEF9F1] text-green-700 border border-green-400/40",
};

export default function CertificationBadge({ label }: Props) {
  return (
    <span
      className={`
        text-xs font-medium
        px-2 py-1 rounded-full
        ${badgeStyles[label] ?? "bg-gray-100 text-gray-700 border"}
      `}
    >
      {label}
    </span>
  );
}
