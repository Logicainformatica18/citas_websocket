type Props = {
  open: boolean;
  trend: {
    id: number;
    name: string;
    category?: string;
    trend_score: number | string;
    final_score?: number | string;
    trend_reports?: number;
    year?: number;
    quarter?: number;
    source_title?: string;
    source_url?: string;
    source_type?: string;
  };
  onClose: () => void;
};

export default function TrendDetailModal({
  open,
  trend,
  onClose,
}: Props) {
  if (!open || !trend) return null;

  return (
    <div className="fixed inset-0 z-[9999] flex items-center justify-center">
      {/* BACKDROP */}
      <div
        className="absolute inset-0 bg-black/50"
        onClick={onClose}
      />

      {/* MODAL */}
      <div className="relative w-full max-w-2xl mx-4 rounded-2xl bg-white dark:bg-[#0F2A3A] shadow-xl overflow-hidden">
        {/* HEADER */}
        <div className="px-6 py-4 border-b dark:border-[#1E3A4A] flex justify-between items-start">
          <div>
            <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
              Detalle de la tendencia
            </h3>
            <p className="text-xs text-slate-500 dark:text-slate-400">
              Evidencia externa utilizada en el ranking
            </p>
          </div>

          <button
            onClick={onClose}
            className="text-slate-400 hover:text-slate-600"
          >
            ✕
          </button>
        </div>

        {/* CONTENT */}
        <div className="px-6 py-5 space-y-5">
          {/* NOMBRE */}
          <div>
            <h4 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
              {trend.name}
            </h4>

            {trend.category && (
              <p className="text-sm text-slate-500 mt-1">
                Categoría: {trend.category}
              </p>
            )}
          </div>

          {/* SCORES */}
          <div className="grid grid-cols-2 gap-4">
            <div className="rounded-xl border p-4 dark:border-[#1E3A4A]">
              <p className="text-xs uppercase tracking-wider text-slate-500">
                Score de tendencia
              </p>
              <p className="mt-1 text-2xl font-semibold text-purple-600">
                {trend.trend_score}
              </p>
            </div>

            {trend.final_score && (
              <div className="rounded-xl border p-4 dark:border-[#1E3A4A]">
                <p className="text-xs uppercase tracking-wider text-slate-500">
                  Score final ponderado
                </p>
                <p className="mt-1 text-2xl font-semibold text-[#1CBCE8]">
                  {trend.final_score}
                </p>
              </div>
            )}
          </div>

          {/* CONTEXTO */}
          <div className="flex flex-wrap gap-3 text-sm text-slate-600 dark:text-slate-400">
            {trend.year && trend.quarter && (
              <span>
                📅 Periodo: {trend.year} · Q{trend.quarter}
              </span>
            )}

            {trend.trend_reports !== undefined && (
              <span>
                📊 Reportes analizados: {trend.trend_reports}
              </span>
            )}
          </div>

          {/* FUENTE */}
          {(trend.source_title || trend.source_url) && (
            <div className="rounded-xl border p-4 bg-slate-50 dark:bg-[#102C3C] dark:border-[#1E3A4A]">
              <p className="text-xs uppercase tracking-wider text-slate-500 mb-2">
                Fuente
              </p>

              {trend.source_title && (
                <p className="text-sm font-medium text-slate-900 dark:text-slate-100">
                  {trend.source_title}
                </p>
              )}

              {trend.source_type && (
                <span className="inline-block mt-1 px-2 py-0.5 rounded-full text-xs bg-slate-200 dark:bg-[#1E3A4A] text-slate-600 dark:text-slate-300">
                  {trend.source_type}
                </span>
              )}

              {trend.source_url && (
                <div className="mt-2">
                  <a
                    href={trend.source_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-sm font-medium text-[#1CBCE8] hover:underline"
                  >
                    Ver fuente original ↗
                  </a>
                </div>
              )}
            </div>
          )}
        </div>

        {/* FOOTER */}
        <div className="px-6 py-3 border-t dark:border-[#1E3A4A] flex justify-end">
          <button
            onClick={onClose}
            className="
              px-4 py-2 rounded-lg text-sm font-semibold
              bg-slate-100 text-slate-700
              hover:bg-slate-200
              dark:bg-[#1E3A4A] dark:text-slate-200
              dark:hover:bg-[#25485E]
            "
          >
            Cerrar
          </button>
        </div>
      </div>
    </div>
  );
}
