interface Props {
  title: string;
  value: string | number;
  score?: number;
  trend?: number;
}

export default function KpiCard({ title, value, score, trend }: Props) {
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

      {/* Score */}
      {score !== undefined && (
        <p className="text-[#1CBCE8] font-bold">
          Score {score}
        </p>
      )}

      {/* Tendencia */}
      {trend !== undefined && (
        <p className="text-sm font-medium text-green-600 dark:text-green-400">
          ↑ {trend}%
        </p>
      )}
    </div>
  );
}
