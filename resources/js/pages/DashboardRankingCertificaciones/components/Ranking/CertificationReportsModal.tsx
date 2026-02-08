type Trend = {
  id: number;
  trend_score: number;
  source_title?: string;
  source_url?: string;
  source_type?: string;
  created_at: string;
};

type Props = {
  open: boolean;
  certification: any;
  trends: Trend[];
  onClose: () => void;
};

export default function CertificationTrendModal({
  open,
  certification,
  trends,
  onClose,
}: Props) {
  if (!open) return null;

  const hasTrends = Array.isArray(trends) && trends.length > 0;

  return (
    <div className="fixed inset-0 z-[9999] flex items-center justify-center">
      {/* BACKDROP */}
      <div
        className="absolute inset-0 bg-black/50"
        onClick={onClose}
      />

      {/* MODAL */}
      <div className="relative w-full max-w-2xl mx-4 rounded-2xl bg-white dark:bg-[#0F2A3A] shadow-xl overflow-hidden">
        {/* ================= HEADER ================= */}
        <div className="flex items-center justify-between px-6 py-4 border-b dark:border-[#1E3A4A]">
          <div>
            <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
              📈 Tendencias consideradas
            </h3>
            <p className="text-xs text-slate-500 dark:text-slate-400">
              Evidencia usada en el ranking ·{" "}
              <span className="font-medium">{certification?.name}</span>
            </p>
          </div>

          <button
            onClick={onClose}
            className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
          >
            ✕
          </button>
        </div>

        {/* ================= CONTENT ================= */}
        <div className="px-6 py-6 space-y-4 max-h-[60vh] overflow-y-auto">
          {/* EMPTY */}
          {!hasTrends && (
            <p className="text-sm text-slate-500 dark:text-slate-400">
              No se identificaron tendencias relevantes para esta certificación
              en el periodo seleccionado.
            </p>
          )}

          {/* LIST */}
          {hasTrends &&
            trends.map((trend) => (
              <div
                key={trend.id}
                className="
                  rounded-xl border p-4
                  bg-white dark:bg-[#102C3C]
                  dark:border-[#1E3A4A]
                "
              >
                {/* TITULO */}
                <h4 className="font-semibold text-slate-900 dark:text-slate-100">
                  {trend.source_title ?? "Fuente de tendencia"}
                </h4>

                {/* META */}
                <div className="mt-1 flex flex-wrap gap-3 text-xs text-slate-500 dark:text-slate-400">
                  {trend.source_type && (
                    <span className="flex items-center gap-1">
                      🧾 {trend.source_type}
                    </span>
                  )}
                  <span className="flex items-center gap-1">
                    📅 {new Date(trend.created_at).toLocaleDateString()}
                  </span>
                </div>

                {/* SCORE */}
                <div className="mt-3">
                  <div className="flex items-center justify-between">
                    <span className="text-xs uppercase tracking-wider text-slate-500">
                      Impacto de la tendencia
                    </span>
                    <span className="text-sm font-semibold text-purple-600 dark:text-purple-400">
                      {Number(trend.trend_score).toFixed(1)}
                    </span>
                  </div>

                  <div className="mt-1 h-1.5 w-full rounded-full bg-slate-200 dark:bg-[#1E3A4A] overflow-hidden">
                    <div
                      className="h-full bg-purple-500"
                      style={{
                        width: `${Math.min(Number(trend.trend_score), 100)}%`,
                      }}
                    />
                  </div>
                </div>

                {/* LINK */}
                {trend.source_url && (
                  <div className="mt-3">
                    <a
                      href={trend.source_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="
                        inline-flex items-center gap-1
                        text-sm font-medium text-[#1CBCE8]
                        hover:underline
                      "
                    >
                      🔗 Ver fuente original
                    </a>
                  </div>
                )}
              </div>
            ))}
        </div>

        {/* ================= FOOTER ================= */}
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
