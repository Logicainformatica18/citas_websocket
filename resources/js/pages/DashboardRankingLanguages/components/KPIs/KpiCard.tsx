interface Props {
  title: string;
  value: string | number;
  score?: number; // Score laboral (0–100)
}

export default function KpiCard({ title, value, score }: Props) {
  return (
    <div
      className="
        rounded-xl
        border
        p-4
        transition

        bg-white
        border-gray-200

        dark:bg-[#0F2A3A]
        dark:border-[#1E3A4A]
      "
    >
      {/* Título */}
      <p className="text-sm text-gray-500 dark:text-slate-400">
        {title}
      </p>

      {/* Valor principal */}
      <p className="text-xl font-bold text-slate-900 dark:text-slate-100">
        {value}
      </p>

      {/* Score laboral */}
      {score !== undefined && (
        <p className="mt-1 text-sm font-semibold text-[#1CBCE8]">
          Score laboral: {score}
        </p>
      )}
    </div>
  );
}
