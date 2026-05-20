import { useEffect, useState } from "react";
import axios from "axios";
import { usePage } from "@inertiajs/react";

import {
  Dialog,
  DialogContent,
  DialogTitle,
  DialogDescription,
  DialogHeader,
} from "@/components/ui/dialog";

import {
  Trophy,
  Calendar,
  Download,
} from "lucide-react";

export function WeeklyEvolutionModal({
  open,
  onClose,
}) {

  const { meta } = usePage().props as any;

  /*
  ==================================================
  FALLBACKS
  ==================================================
  */

  const currentDate = new Date();

  const year =
    meta?.year ||
    currentDate.getFullYear();

  const month =
    meta?.month ||
    currentDate.getMonth() + 1;

  /*
  ==================================================
  STATE
  ==================================================
  */

  const [data, setData] = useState([]);

  const [page, setPage] = useState(1);

  const [pagination, setPagination] =
    useState(null);

  const [filter, setFilter] =
    useState("weekly");

  const [loading, setLoading] =
    useState(false);

  /*
  ==================================================
  FETCH
  ==================================================
  */

  useEffect(() => {

    if (!open) return;

    setLoading(true);

    axios
      .get(
        "/dashboard/ranking/technologies/weekly",
        {
          params: {
            page,
            per_page: 5,
            filter,
            year,
            month,
          },
        }
      )

      .then((res) => {

        setData(res.data.data || []);

        setPagination(
          res.data.pagination || null
        );
      })

      .catch((err) => {

        console.error(
          "EVOLUTION ERROR",
          err
        );

        setData([]);
      })

      .finally(() => {
        setLoading(false);
      });

  }, [
    open,
    page,
    filter,
    year,
    month,
  ]);

  /*
  ==================================================
  RESET PAGE
  ==================================================
  */

  useEffect(() => {
    setPage(1);
  }, [filter]);

  /*
  ==================================================
  EXPORT
  ==================================================
  */

  const downloadExcel = () => {

    const params = new URLSearchParams({

      year: year.toString(),

      month: month.toString(),

      filter,
    });

    window.open(
      `/dashboard/ranking/technologies/evolution/export?${params}`,
      "_blank"
    );
  };

  /*
  ==================================================
  FORMATTERS
  ==================================================
  */

  const formatDate = (date) => {

    if (!date) return "-";

    return new Date(date)
      .toLocaleDateString("es-PE", {
        day: "2-digit",
        month: "short",
      });
  };

  /*
  ==================================================
  LABELS
  ==================================================
  */

  const getLabel = (item) => {

    return item.label || item.period;
  };

  /*
  ==================================================
  TITLES
  ==================================================
  */

  const getTitle = () => {

    if (filter === "monthly") {
      return "Evolución mensual";
    }

    if (filter === "biweekly") {
      return "Evolución quincenal";
    }

    return "Evolución semanal";
  };

  /*
  ==================================================
  RENDER
  ==================================================
  */

  return (

    <Dialog
      open={open}
      onOpenChange={onClose}
    >

      <DialogContent
        className="
          !max-w-[1150px]
          w-full
          p-0
          overflow-hidden

          border
          border-slate-200
          dark:border-white/10

          bg-white
          dark:bg-[#060816]

          text-slate-900
          dark:text-white
        "
      >

        <DialogHeader>

          <DialogTitle className="sr-only">
            {getTitle()}
          </DialogTitle>

          <DialogDescription className="sr-only">
            Evolución de tecnologías
          </DialogDescription>

        </DialogHeader>

        <div className="flex flex-col max-h-[85vh]">

          {/* =======================================
              HEADER
          ======================================= */}

          <div
            className="
              px-6 py-5
              border-b

              border-slate-200
              dark:border-white/10
            "
          >

            <div className="flex items-start justify-between gap-5">

              {/* LEFT */}

              <div>

                <h2
                  className="
                    text-2xl
                    font-black
                    tracking-tight
                    leading-none
                  "
                >
                  {getTitle()}
                </h2>

                <p
                  className="
                    mt-2
                    text-[13px]

                    text-slate-500
                    dark:text-white/55
                  "
                >
                  Top tecnologías por demanda laboral
                  en el sector IT
                </p>

              </div>

              {/* RIGHT */}

              <div className="flex items-center gap-3 pr-6">

                {/* FILTER */}

                <div
                  className="
                    flex items-center gap-2

                    px-3 py-2
                    rounded-xl

                    border
                    border-slate-200
                    dark:border-white/10

                    bg-slate-50
                    dark:bg-white/[0.03]
                  "
                >

                  <span
                    className="
                      text-[10px]
                      uppercase
                      tracking-[0.18em]

                      text-slate-400
                      dark:text-white/35
                    "
                  >
                    Vista
                  </span>

                  <select
                    value={filter}
                    onChange={(e) => {
                      setFilter(e.target.value);
                    }}
                    className="
                      min-w-[110px]

                      bg-transparent

                      text-[13px]
                      font-semibold

                      text-slate-700
                      dark:text-white

                      border-0
                      outline-none
                      ring-0

                      focus:outline-none
                      focus:ring-0

                      appearance-none
                      cursor-pointer
                    "
                  >

                    <option value="weekly">
                      Semanal
                    </option>

                    <option value="biweekly">
                      Quincenal
                    </option>

                    <option value="monthly">
                      Mensual
                    </option>

                  </select>

                </div>

                {/* EXPORT */}

                <button
                  onClick={downloadExcel}
                  className="
                    flex items-center gap-2

                    px-4 py-2
                    rounded-xl

                    text-[13px]
                    font-semibold

                    border
                    border-slate-200
                    dark:border-white/10

                    bg-white
                    dark:bg-white

                    text-slate-900

                    hover:scale-[1.02]
                    transition
                  "
                >

                  <Download className="w-4 h-4" />

                  Exportar Excel

                </button>

              </div>

            </div>

          </div>

          {/* =======================================
              CONTENT
          ======================================= */}

          <div className="overflow-y-auto px-6 py-5 space-y-5">

            {loading && (

              <div className="py-16 text-center">

                <p
                  className="
                    text-sm
                    text-slate-500
                    dark:text-white/50
                  "
                >
                  Cargando...
                </p>

              </div>
            )}

            {!loading && data.length === 0 && (

              <div className="text-center py-16">

                <p
                  className="
                    text-sm
                    text-slate-500
                    dark:text-white/50
                  "
                >
                  No hay datos para este período
                </p>

              </div>
            )}

            {!loading && data.map((item) => (

              <div
                key={
                  item.period +
                  item.start_date
                }
                className="
                  rounded-[26px]
                  border

                  border-slate-200
                  dark:border-white/10

                  bg-slate-50
                  dark:bg-white/[0.02]

                  p-5
                "
              >

                {/* ======================================
                    HEADER
                ====================================== */}

                <div className="flex justify-between items-start mb-6">

                  {/* LEFT */}

                  <div className="flex items-center gap-4">

                    <div
                      className="
                        w-12 h-12
                        rounded-2xl

                        bg-emerald-50
                        dark:bg-emerald-500/10

                        border
                        border-emerald-100
                        dark:border-emerald-500/20

                        flex items-center justify-center
                      "
                    >

                      <Calendar
                        className="
                          w-5 h-5

                          text-emerald-600
                          dark:text-emerald-400
                        "
                      />

                    </div>

                    <div>

                      <p
                        className="
                          text-[26px]
                          font-black
                          leading-none
                        "
                      >
                        {getLabel(item)}
                      </p>

                      <p
                        className="
                          mt-2
                          text-[13px]

                          text-slate-500
                          dark:text-white/50
                        "
                      >

                        {formatDate(
                          item.start_date
                        )}

                        {" → "}

                        {formatDate(
                          item.end_date
                        )}

                      </p>

                    </div>

                  </div>

                  {/* RIGHT */}

                  <div className="text-right">

                    <p
                      className="
                        text-[10px]
                        uppercase
                        tracking-[0.22em]

                        text-slate-400
                        dark:text-white/40
                      "
                    >
                      Score Promedio
                    </p>

                    <div className="mt-1 flex items-end gap-1 justify-end">

                      <span
                        className="
                          text-4xl
                          font-black
                          leading-none

                          text-emerald-600
                          dark:text-emerald-400
                        "
                      >
                        {item.period_score}
                      </span>

                      <span
                        className="
                          text-sm
                          mb-1

                          text-slate-400
                          dark:text-white/40
                        "
                      >
                        /100
                      </span>

                    </div>

                    <p
                      className="
                        mt-1
                        text-[12px]

                        text-slate-500
                        dark:text-white/50
                      "
                    >

                      Basado en{" "}

                      <span
                        className="
                          font-bold

                          text-slate-700
                          dark:text-white
                        "
                      >
                        {item.total_jobs}
                      </span>

                      {" vacantes activas"}

                    </p>

                  </div>

                </div>

                {/* ======================================
                    TOP
                ====================================== */}

                <div className="flex items-center justify-between mb-4">

                  <p
                    className="
                      text-[11px]
                      uppercase
                      tracking-[0.20em]

                      text-slate-400
                      dark:text-white/40
                    "
                  >
                    Ranking Top 5
                  </p>

                  <p
                    className="
                      text-[12px]

                      text-slate-500
                      dark:text-white/45
                    "
                  >
                    5 resultados
                  </p>

                </div>

                {/* ======================================
                    GRID
                ====================================== */}

                <div
                  className="
                    grid
                    grid-cols-1
                    md:grid-cols-5
                    gap-4
                  "
                >

                  {item.top?.map(
                    (tech, i) => (

                    <div
                      key={tech.id || i}
                      className={`

                        rounded-[22px]
                        p-4
                        border
                        transition-all

                        min-h-[240px]

                        ${
                          i === 0

                            ? `
                              bg-emerald-500
                              text-white

                              border-emerald-500
                              shadow-xl
                            `

                            : `
                              bg-white
                              dark:bg-white/[0.03]

                              border-slate-200
                              dark:border-white/10
                            `
                        }
                      `}
                    >

                      {/* TOP */}

                      <div className="
                        flex items-start
                        justify-between
                        mb-4
                      ">

                        <div
                          className={`
                            px-2.5 py-1.5
                            rounded-lg

                            text-[10px]
                            font-black
                            uppercase

                            ${
                              i === 0
                                ? `
                                  bg-black/10
                                `
                                : `
                                  bg-slate-100
                                  dark:bg-white/5

                                  text-slate-500
                                  dark:text-white/55
                                `
                            }
                          `}
                        >

                          {i === 0 && (

                            <Trophy className="
                              w-3 h-3 mb-1
                            " />
                          )}

                          Rank #{i + 1}

                        </div>

                        <span
                          className="
                            text-3xl
                            font-black
                            leading-none
                          "
                        >

                          {tech.final_score}

                        </span>

                      </div>

                      {/* NAME */}

                      <p
                        className="
                          text-lg
                          font-black
                          leading-tight
                          mb-6
                          break-words
                        "
                      >

                        {tech.name}

                      </p>

                      {/* STATS */}

                      <div
                        className={`
                          space-y-2
                          text-[13px]

                          ${
                            i === 0
                              ? `
                                text-white/90
                              `
                              : `
                                text-slate-500
                                dark:text-white/55
                              `
                          }
                        `}
                      >

                        <div className="flex justify-between">

                          <span>
                            Vacantes
                          </span>

                          <span
                            className={`
                              font-bold

                              ${
                                i === 0
                                  ? `text-white`
                                  : `
                                    text-slate-800
                                    dark:text-white
                                  `
                              }
                            `}
                          >
                            {tech.jobs}
                          </span>

                        </div>

                        <div className="flex justify-between">

                          <span>
                            Laboral
                          </span>

                          <span
                            className={`
                              font-bold

                              ${
                                i === 0
                                  ? `text-white`
                                  : `
                                    text-slate-800
                                    dark:text-white
                                  `
                              }
                            `}
                          >
                            {tech.labor_score}
                          </span>

                        </div>

                        <div className="flex justify-between">

                          <span>
                            Tendencia
                          </span>

                          <span
                            className={`
                              font-bold

                              ${
                                i === 0
                                  ? `text-white`
                                  : `
                                    text-slate-800
                                    dark:text-white
                                  `
                              }
                            `}
                          >
                            {tech.trend_score}
                          </span>

                        </div>

                        <div
                          className={`
                            pt-4 mt-4
                            border-t
                            flex justify-between

                            ${
                              i === 0
                                ? `
                                  border-white/20
                                `
                                : `
                                  border-slate-200
                                  dark:border-white/10
                                `
                            }
                          `}
                        >

                          <span className="font-semibold">
                            Final
                          </span>

                          <span className="font-black">
                            {tech.final_score}
                          </span>

                        </div>

                      </div>

                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>

          {/* =======================================
              PAGINATION
          ======================================= */}

          {pagination && (

            <div
              className="
                p-5
                border-t

                border-slate-200
                dark:border-white/10

                flex justify-between items-center
              "
            >

              <button
                disabled={
                  page <= 1
                }
                onClick={() => {

                  setPage((p) => p - 1);
                }}
                className="
                  px-5 py-2

                  rounded-xl

                  text-[13px]
                  font-semibold

                  border
                  border-slate-200
                  dark:border-white/10

                  bg-slate-100
                  dark:bg-white/[0.03]

                  text-slate-700
                  dark:text-white/70

                  disabled:opacity-40
                "
              >

                ← Anterior

              </button>

              <span
                className="
                  text-[13px]
                  font-medium

                  text-slate-500
                  dark:text-white/55
                "
              >

                Página {
                  pagination.current_page
                }

                {" de "}

                {
                  pagination.last_page
                }

              </span>

              <button
                disabled={
                  page >=
                  pagination.last_page
                }
                onClick={() => {

                  setPage((p) => p + 1);
                }}
                className="
                  px-5 py-2

                  rounded-xl

                  text-[13px]
                  font-semibold

                  bg-emerald-500
                  text-white

                  disabled:opacity-40
                "
              >

                Siguiente →

              </button>

            </div>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
