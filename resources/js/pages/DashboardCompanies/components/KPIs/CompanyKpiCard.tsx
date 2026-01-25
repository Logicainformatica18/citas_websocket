import { ReactNode } from "react";

interface Props {
  title: string;
  value: string | number | null | undefined;
  subtitle?: string;
  icon?: ReactNode;
  highlight?: boolean;
}

export default function CompanyKpiCard({
  title,
  value,
  subtitle,
  icon,
  highlight = false,
}: Props) {
  const displayValue =
    value === null || value === undefined
      ? "—"
      : typeof value === "number"
      ? value.toLocaleString()
      : value;

  return (
    <div
      className={`
        rounded-xl
        border
        p-4
        transition
        bg-white
        border-gray-200
        dark:bg-[#0F2A3A]
        dark:border-[#1E3A4A]
        ${highlight ? "ring-1 ring-[#1CBCE8]/40" : ""}
      `}
    >
      {/* Header */}
      <div className="flex items-center gap-2">
        {icon && (
          <div className="text-[#1CBCE8]">
            {icon}
          </div>
        )}

        <p className="text-sm text-gray-500 dark:text-slate-400">
          {title}
        </p>
      </div>

      {/* Valor */}
      <p className="mt-2 text-2xl font-extrabold text-slate-900 dark:text-slate-100">
        {displayValue}
      </p>

      {/* Sub */}
      {subtitle && (
        <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
          {subtitle}
        </p>
      )}
    </div>
  );
}
