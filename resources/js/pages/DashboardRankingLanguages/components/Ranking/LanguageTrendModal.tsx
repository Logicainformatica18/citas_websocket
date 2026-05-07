import { useMemo } from "react";

export default function TrendDetailModal({
  open,
  language,
  trends,
  stats,
  pagination,
  onPageChange,
  onClose,
}: Props) {
  if (!open) return null;

  const tavilyCount =
    stats?.tavily_total ?? 0;

  const gptCount =
    stats?.gpt_total ?? 0;

  /* =========================================================
     FIX PAGINATION
  ========================================================= */

  const currentPage =
    pagination?.current_page ?? 1;

  const lastPage =
    pagination?.last_page ?? 1;

  const visiblePages = useMemo(() => {
    if (lastPage <= 5) {
      return Array.from(
        { length: lastPage },
        (_, i) => i + 1
      );
    }

    if (currentPage <= 3) {
      return [1, 2, 3, 4, 5];
    }

    if (currentPage >= lastPage - 2) {
      return [
        lastPage - 4,
        lastPage - 3,
        lastPage - 2,
        lastPage - 1,
        lastPage,
      ];
    }

    return [
      currentPage - 2,
      currentPage - 1,
      currentPage,
      currentPage + 1,
      currentPage + 2,
    ];
  }, [currentPage, lastPage]);

  return (
    <div className="fixed inset-0 z-[9999] flex items-center justify-center">
      {/* BACKDROP */}
      <div
        className="absolute inset-0 bg-black/60 backdrop-blur-sm"
        onClick={onClose}
      />

      {/* MODAL */}
      <div
        className="
          relative
          w-full
          max-w-5xl
          mx-4
          rounded-3xl
          overflow-hidden
          bg-[#F5F7FB]
          dark:bg-[#0F172A]
          shadow-2xl
        "
      >
        {/* HEADER */}
        <div
          className="
            relative
            overflow-hidden
            px-6
            py-5
            bg-gradient-to-r
            from-[#7C3AED]
            via-[#5B5FF6]
            to-[#00AEEF]
          "
        >
          <div className="flex items-start justify-between">
            <div className="flex gap-4">
              <div
                className="
                  h-12
                  w-12
                  rounded-2xl
                  bg-white/10
                  backdrop-blur-md
                  flex
                  items-center
                  justify-center
                  border
                  border-white/20
                "
              >
                <span className="text-lg text-white">
                  ✦
                </span>
              </div>

              <div>
                <p className="text-[10px] tracking-[0.22em] uppercase text-white/70 font-semibold">
                  Insights · Ranking
                </p>

                <h2 className="mt-1 text-2xl font-bold text-white leading-tight">
                  Evidencia de tendencias
                </h2>

                <p className="mt-2 text-xs text-white/80 max-w-2xl leading-relaxed">
                  Reportes externos y descubrimientos
                  generados por IA utilizados para
                  construir el ranking.
                </p>

                {language?.name && (
                  <div className="mt-4">
                    <span
                      className="
                        inline-flex
                        items-center
                        rounded-full
                        bg-white/15
                        backdrop-blur-md
                        border
                        border-white/20
                        px-3
                        py-1.5
                        text-xs
                        font-semibold
                        text-white
                      "
                    >
                      {language.name}
                    </span>
                  </div>
                )}
              </div>
            </div>

            <button
              onClick={onClose}
              className="
                text-white/70
                hover:text-white
                text-lg
                transition-colors
              "
            >
              ×
            </button>
          </div>
        </div>

        {/* LEGEND */}
        <div className="px-6 py-4 bg-white dark:bg-[#111827] border-b dark:border-slate-800">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            {/* TAVILY */}
            <div
              className="
                rounded-2xl
                border
                border-emerald-200
                bg-emerald-50
                dark:bg-emerald-900/10
                dark:border-emerald-900/30
                p-4
              "
            >
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div
                    className="
                      h-8
                      w-8
                      rounded-full
                      bg-emerald-500
                      text-white
                      flex
                      items-center
                      justify-center
                      text-xs
                    "
                  >
                    🔍
                  </div>

                  <h4 className="font-bold text-sm text-slate-900 dark:text-white">
                    Tavily Search
                  </h4>
                </div>

                <div
                  className="
                    h-7
                    min-w-[28px]
                    px-2
                    rounded-full
                    bg-white
                    dark:bg-[#111827]
                    border
                    border-emerald-200
                    flex
                    items-center
                    justify-center
                    text-xs
                    font-bold
                    text-slate-700
                  "
                >
                  {tavilyCount}
                </div>
              </div>

              <p className="mt-3 text-xs leading-relaxed text-slate-600 dark:text-slate-300">
                Tendencias identificadas mediante
                fuentes externas verificadas.
              </p>
            </div>

            {/* GPT */}
            <div
              className="
                rounded-2xl
                border
                border-violet-200
                bg-violet-50
                dark:bg-violet-900/10
                dark:border-violet-900/30
                p-4
              "
            >
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div
                    className="
                      h-8
                      w-8
                      rounded-full
                      bg-violet-500
                      text-white
                      flex
                      items-center
                      justify-center
                      text-xs
                    "
                  >
                    🤖
                  </div>

                  <h4 className="font-bold text-sm text-slate-900 dark:text-white">
                    GPT Discovery
                  </h4>
                </div>

                <div
                  className="
                    h-7
                    min-w-[28px]
                    px-2
                    rounded-full
                    bg-white
                    dark:bg-[#111827]
                    border
                    border-violet-200
                    flex
                    items-center
                    justify-center
                    text-xs
                    font-bold
                    text-slate-700
                  "
                >
                  {gptCount}
                </div>
              </div>

              <p className="mt-3 text-xs leading-relaxed text-slate-600 dark:text-slate-300">
                Tendencias propuestas automáticamente
                por IA.
              </p>
            </div>
          </div>
        </div>

        {/* CONTENT */}
        <div className="px-6 py-5 max-h-[55vh] overflow-y-auto space-y-3">
          {(trends ?? []).length === 0 && (
            <div
              className="
                rounded-2xl
                border
                border-dashed
                border-slate-300
                dark:border-slate-700
                bg-white
                dark:bg-[#111827]
                p-10
                text-center
              "
            >
              <p className="text-sm text-slate-500 dark:text-slate-400">
                No se encontraron tendencias.
              </p>
            </div>
          )}

          {(trends ?? []).map((report, index) => {
            const source = report.discovered_by
              ?.trim()
              ?.toLowerCase();

            const isTavily =
              source?.includes("tavily");

            const isGPT =
              source?.includes("gpt");

            return (
              <div
                key={report.id}
                className="
                  rounded-2xl
                  border
                  border-slate-200
                  dark:border-slate-800
                  bg-white
                  dark:bg-[#111827]
                  p-4
                  shadow-sm
                "
              >
                <div className="flex gap-4">
                  <div className="flex-1">
                    {/* NUMBER */}
                    <div className="text-[11px] font-bold text-slate-400">
                      #
                      {(currentPage - 1) *
                        (pagination?.per_page ??
                          trends.length) +
                        index +
                        1}
                    </div>

                    {/* TITLE */}
                    {report.source_url ? (
                      <a
                        href={report.source_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="
                          mt-1
                          block
                          text-base
                          font-bold
                          text-slate-900
                          dark:text-white
                          hover:text-[#5B5FF6]
                        "
                      >
                        {report.source_title}
                      </a>
                    ) : (
                      <h3 className="mt-1 text-base font-bold text-slate-900 dark:text-white">
                        {report.source_title}
                      </h3>
                    )}

                    {/* DESCRIPTION */}
                    <p className="mt-3 text-sm text-slate-600 dark:text-slate-300">
                      {isTavily
                        ? "Fuente externa verificada."
                        : isGPT
                          ? "Tendencia propuesta por IA."
                          : "Origen no identificado."}
                    </p>

                    {/* META */}
                    <div className="mt-4 flex flex-wrap gap-2">
                      {/* TYPE */}
                      {report.source_type && (
                        <span
                          className="
                            inline-flex
                            items-center
                            rounded-full
                            border
                            border-slate-200
                            bg-slate-100
                            px-2.5
                            py-1
                            text-[11px]
                          "
                        >
                          📄 {report.source_type}
                        </span>
                      )}

                      {/* DATE */}
                      {report.created_at && (
                        <span
                          className="
                            inline-flex
                            items-center
                            rounded-full
                            border
                            border-slate-200
                            bg-slate-100
                            px-2.5
                            py-1
                            text-[11px]
                          "
                        >
                          📅{" "}
                          {new Date(
                            report.created_at
                          ).toLocaleDateString()}
                        </span>
                      )}

                      {/* SOURCE */}
                      {report.discovered_by && (
                        <span
                          className="
                            inline-flex
                            items-center
                            rounded-full
                            border
                            border-slate-200
                            bg-slate-100
                            px-2.5
                            py-1
                            text-[11px]
                          "
                        >
                          🧠 Fuente:{" "}
                          {report.discovered_by}
                        </span>
                      )}

                      {/* ORIGIN */}
                      <span
                        className={`
                          inline-flex
                          items-center
                          rounded-full
                          px-2.5
                          py-1
                          text-[11px]
                          font-semibold
                          border
                          ${
                            isTavily
                              ? "border-emerald-200 bg-emerald-50 text-emerald-700"
                              : isGPT
                                ? "border-violet-200 bg-violet-50 text-violet-700"
                                : "border-slate-200 bg-slate-50 text-slate-700"
                          }
                        `}
                      >
                        {isTavily
                          ? "🔎 Tavily"
                          : isGPT
                            ? "🤖 GPT"
                            : "⚪ Desconocido"}
                      </span>
                    </div>
                  </div>

                  {/* SCORE */}
                  <div
                    className="
                      w-[90px]
                      min-w-[90px]
                      rounded-2xl
                      bg-gradient-to-br
                      from-fuchsia-500
                      to-violet-600
                      flex
                      flex-col
                      items-center
                      justify-center
                      text-white
                      shadow-lg
                      px-3
                      py-4
                    "
                  >
                    <div className="text-sm opacity-90">
                      ↗
                    </div>

                    <p className="mt-1 text-[10px] uppercase tracking-[0.15em] font-semibold opacity-80">
                      Score
                    </p>

                    <div className="mt-1 text-3xl font-black leading-none">
                      {report.trend_score}
                    </div>
                  </div>
                </div>
              </div>
            );
          })}
        </div>

        {/* FOOTER */}
        <div
          className="
            px-6
            py-4
            border-t
            border-slate-200
            dark:border-slate-800
            bg-white
            dark:bg-[#111827]
          "
        >
          <div className="flex flex-col md:flex-row gap-4 md:items-center md:justify-between">

            {/* INFO */}
            <div>
              <p className="text-xs text-slate-500 dark:text-slate-400">
                {pagination?.total ??
                  trends?.length ??
                  0}{" "}
                fuentes
              </p>

              {pagination && (
                <p className="mt-1 text-[11px] text-slate-400">
                  Página {currentPage} de{" "}
                  {lastPage}
                </p>
              )}
            </div>

            {/* PAGINATION */}
            {pagination &&
              lastPage > 1 && (
                <div className="flex items-center gap-2">
                  {/* PREV */}
                  <button
                    disabled={currentPage === 1}
                    onClick={() =>
                      onPageChange?.(
                        currentPage - 1
                      )
                    }
                    className="
                      px-3
                      py-2
                      rounded-xl
                      border
                      border-slate-200
                      dark:border-slate-700
                      bg-white
                      dark:bg-slate-900
                      text-xs
                      font-medium
                      text-slate-700
                      dark:text-slate-300
                      hover:bg-slate-50
                      dark:hover:bg-slate-800
                      disabled:opacity-40
                      disabled:cursor-not-allowed
                      transition-all
                    "
                  >
                    ← Anterior
                  </button>

                  {/* PAGES */}
                  <div className="flex items-center gap-1">
                    {visiblePages.map((page) => {
                      const active =
                        page === currentPage;

                      return (
                        <button
                          key={page}
                          onClick={() =>
                            onPageChange?.(
                              page
                            )
                          }
                          className={`
                            h-9
                            w-9
                            rounded-xl
                            text-xs
                            font-semibold
                            transition-all
                            ${
                              active
                                ? "bg-violet-600 text-white shadow-md"
                                : "border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
                            }
                          `}
                        >
                          {page}
                        </button>
                      );
                    })}
                  </div>

                  {/* NEXT */}
                  <button
                    disabled={
                      currentPage ===
                      lastPage
                    }
                    onClick={() =>
                      onPageChange?.(
                        currentPage + 1
                      )
                    }
                    className="
                      px-3
                      py-2
                      rounded-xl
                      border
                      border-slate-200
                      dark:border-slate-700
                      bg-white
                      dark:bg-slate-900
                      text-xs
                      font-medium
                      text-slate-700
                      dark:text-slate-300
                      hover:bg-slate-50
                      dark:hover:bg-slate-800
                      disabled:opacity-40
                      disabled:cursor-not-allowed
                      transition-all
                    "
                  >
                    Siguiente →
                  </button>
                </div>
              )}

            {/* CLOSE */}
            <div className="flex justify-end">
              <button
                onClick={onClose}
                className="
                  px-4
                  py-2.5
                  rounded-xl
                  bg-[#0F172A]
                  hover:bg-[#111827]
                  text-white
                  text-sm
                  font-semibold
                "
              >
                Cerrar
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}