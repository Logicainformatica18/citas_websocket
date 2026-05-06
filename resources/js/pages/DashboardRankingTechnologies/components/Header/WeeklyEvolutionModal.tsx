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
  🔥 FALLBACKS
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
            per_page: 6,
            filter,
            year,
            month,
          },
        }
      )

      .then((res) => {

        console.log(
          "EVOLUTION RESPONSE",
          res.data
        );

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

      <DialogContent className="!max-w-[1000px] w-full p-0">

        <DialogHeader>

          <DialogTitle className="sr-only">
            {getTitle()}
          </DialogTitle>

          <DialogDescription className="sr-only">
            Evolución de tecnologías
          </DialogDescription>

        </DialogHeader>

        <div className="flex flex-col max-h-[75vh]">

          {/* =======================================
              HEADER
          ======================================= */}

          <div className="p-5 border-b flex items-start justify-between">

            <div>

              <h2 className="text-xl font-bold">
                {getTitle()}
              </h2>

              <p className="text-sm text-slate-500">
                Top tecnologías por demanda laboral
              </p>

            </div>

            <div className="flex items-center gap-3 pr-10">

              {/* FILTER */}

              <select
                value={filter}
                onChange={(e) => {

                  setFilter(
                    e.target.value
                  );
                }}
                className="
                  border rounded-lg
                  px-3 py-2
                  text-sm bg-white
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

              {/* EXPORT */}

              <button
                onClick={downloadExcel}
                className="
                  flex items-center gap-2
                  px-3 py-2
                  border rounded-lg
                  text-sm font-semibold
                  bg-white
                  hover:bg-slate-50
                  transition
                "
              >

                <Download className="w-4 h-4" />

                Excel

              </button>

            </div>
          </div>

          {/* =======================================
              CONTENT
          ======================================= */}

          <div className="overflow-y-auto px-5 py-4 space-y-5">

            {loading && (

              <p className="text-sm text-slate-500">
                Cargando...
              </p>
            )}

            {!loading && data.length === 0 && (

              <div className="text-center py-10">

                <p className="text-sm text-slate-500">

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
                  border rounded-xl
                  p-4 bg-white
                "
              >

                {/* HEADER */}

                <div className="flex justify-between items-center mb-3">

                  <div className="flex items-center gap-2">

                    <Calendar className="w-4 h-4 text-teal-500" />

                    <div>

                      <p className="font-semibold">
                        {getLabel(item)}
                      </p>

                      <p className="text-xs text-slate-500">

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

                  <div className="text-right">

                    <p className="text-xs text-slate-400">
                      Total
                    </p>

                    <p className="text-lg font-bold text-teal-600">

                      {item.total_period}

                    </p>

                  </div>
                </div>

                {/* GRID */}

                <div className="grid grid-cols-2 md:grid-cols-5 gap-3">

                  {item.top?.map(
                    (tech, i) => (

                    <div
                      key={tech.id || i}
                      className={`

                        p-3 rounded-lg

                        ${
                          i === 0
                            ? "bg-teal-500 text-white"
                            : "bg-slate-100"
                        }
                      `}
                    >

                      <div className="flex items-center gap-1 mb-1">

                        {i === 0 && (
                          <Trophy className="w-3 h-3" />
                        )}

                        <span className="text-xs font-bold">

                          #{i + 1}

                        </span>

                      </div>

                      <p className="text-sm font-semibold truncate">

                        {tech.name}

                      </p>

                      <p className="text-xs opacity-70">

                        {tech.total}

                      </p>

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

            <div className="p-4 border-t flex justify-between items-center">

              <button
                disabled={
                  page <= 1
                }
                onClick={() => {

                  setPage((p) => p - 1);
                }}
                className="
                  px-5 py-2
                  bg-slate-200
                  rounded-lg
                  text-sm font-semibold
                  disabled:opacity-40
                "
              >

                ← Anterior

              </button>

              <span className="text-sm text-slate-600 font-medium">

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
                  bg-teal-500
                  text-white
                  rounded-lg
                  text-sm font-semibold
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