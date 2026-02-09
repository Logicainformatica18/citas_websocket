export type TrendReport = {
  id: number;
  trend_score: number | string;
  source_title?: string;
  source_url?: string;
  source_type?: string;
  created_at?: string;
};

type Pagination = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  prev_page_url?: string | null;
  next_page_url?: string | null;
};

type Props = {
  open: boolean;
  technologyId: number;
  technologyName: string | null;
  reports: TrendReport[];
  pagination?: Pagination;
  onPageChange?: (page: number) => void;
  onClose: () => void;
};
export default function TrendDetailModal({
  open,
  technologyId,
  technologyName,
  reports,
  pagination,
  onPageChange,
  onClose,
}: Props) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[9999] flex items-center justify-center">
      {/* BACKDROP */}
      <div
        className="absolute inset-0 bg-black/50"
        onClick={onClose}
      />

      {/* MODAL */}
      <div className="relative w-full max-w-3xl mx-4 rounded-2xl bg-white dark:bg-[#0F2A3A] shadow-xl overflow-hidden">
        {/* ================= HEADER ================= */}
        <div className="px-6 py-4 border-b dark:border-[#1E3A4A] flex justify-between items-start">
          <div>
            <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
              Evidencia de tendencias tecnológicas
            </h3>
            <p className="text-xs text-slate-500 dark:text-slate-400">
              Reportes externos utilizados en el ranking
            </p>

            {technologyName && (
              <p className="mt-1 text-sm font-medium text-[#1CBCE8]">
                {technologyName}
              </p>
            )}
          </div>

          <button
            onClick={onClose}
            className="text-slate-400 hover:text-slate-600"
          >
            ✕
          </button>
        </div>

        {/* ================= CONTENT ================= */}
        <div className="px-6 py-5 space-y-4 max-h-[65vh] overflow-y-auto">
          {reports.length === 0 && (
            <p className="text-sm text-slate-500">
              No se encontraron reportes de tendencia para esta tecnología.
            </p>
          )}

          {reports.map((report) => (
            <div
              key={report.id}
              className="
                rounded-xl border p-4
                dark:border-[#1E3A4A]
                bg-slate-50 dark:bg-[#102C3C]
              "
            >
              {/* SCORE */}
              <div className="flex justify-between items-center">
                <p className="text-xs uppercase tracking-wider text-slate-500">
                  Score de tendencia
                </p>
                <span className="text-xl font-semibold text-purple-600">
                  {report.trend_score}
                </span>
              </div>

              {/* META */}
              <div className="mt-2 flex flex-wrap gap-3 text-sm text-slate-600 dark:text-slate-400">
                {report.source_type && (
                  <span className="inline-block px-2 py-0.5 rounded-full text-xs bg-slate-200 dark:bg-[#1E3A4A]">
                    {report.source_type}
                  </span>
                )}

                {report.created_at && (
                  <span>
                    📅 {new Date(report.created_at).toLocaleDateString()}
                  </span>
                )}
              </div>

              {/* FUENTE */}
              {(report.source_title || report.source_url) && (
                <div className="mt-4 pt-3 border-t dark:border-[#1E3A4A]">
                  {report.source_title && (
                    <p className="text-sm font-medium text-slate-900 dark:text-slate-100">
                      {report.source_title}
                    </p>
                  )}

                  {report.source_url && (
                    <div className="mt-1">
                      <a
                        href={report.source_url}
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
          ))}
        </div>

        {/* ================= PAGINATION ================= */}
        {pagination && onPageChange && (
          <div className="px-6 py-3 border-t dark:border-[#1E3A4A] flex items-center justify-between">
            <button
              disabled={!pagination.prev_page_url}
              onClick={() =>
                onPageChange(pagination.current_page - 1)
              }
              className="px-3 py-1 rounded text-sm bg-slate-100 dark:bg-[#1E3A4A] disabled:opacity-40"
            >
              ← Anterior
            </button>

            <span className="text-xs text-slate-500">
              Página {pagination.current_page} de {pagination.last_page} ·{" "}
              {pagination.total} reportes
            </span>

            <button
              disabled={!pagination.next_page_url}
              onClick={() =>
                onPageChange(pagination.current_page + 1)
              }
              className="px-3 py-1 rounded text-sm bg-slate-100 dark:bg-[#1E3A4A] disabled:opacity-40"
            >
              Siguiente →
            </button>
          </div>
        )}

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
