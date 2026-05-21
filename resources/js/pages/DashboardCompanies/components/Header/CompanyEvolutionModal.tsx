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
  Download,
  Calendar,
  Building2,
} from "lucide-react";

import dayjs from "dayjs";
import "dayjs/locale/es";

dayjs.locale("es");

export function CompanyEvolutionModal({
  open,
  onClose,
}) {

  const { meta } = usePage().props as any;

  const [data, setData] = useState({
    national: {
      data: [],
      pagination: {},
    },
    international: {
      data: [],
      pagination: {},
    },
  });

  const [activeTab, setActiveTab] =
    useState("national");

  const [filter, setFilter] =
    useState("weekly");

  const [page, setPage] =
    useState(1);

  const [loading, setLoading] =
    useState(false);

  /*
  ==================================================
  📦 FETCH
  ==================================================
  */

  const fetchData = (
    pageToLoad = 1
  ) => {

    if (!meta?.year) return;

    setLoading(true);

    axios
      .get(
        "/dashboard/indicators/companies/evolution",
        {
          params: {
            filter,
            page: pageToLoad,
            year: meta.year,
            period: meta.period,
          },
        }
      )
      .then((res) => {
        setData(res.data);
        setPage(pageToLoad);
      })
      .catch((err) => {
        console.error(
          "❌ Error evolución empresas:",
          err
        );
      })
      .finally(() => {
        setLoading(false);
      });
  };

  /*
  ==================================================
  🔄 EFFECT
  ==================================================
  */

  useEffect(() => {
    if (!open) return;
    fetchData(1);
  }, [open, filter]);

  /*
  ==================================================
  📦 ACTIVE DATA
  ==================================================
  */

  const dataset =
    data?.[activeTab]?.data ?? [];

  const pagination =
    data?.[activeTab]?.pagination ?? {};

  /*
  ==================================================
  📥 EXPORT
  ==================================================
  */

  const downloadExcel = () => {

    if (!meta?.year) return;

    const params =
      new URLSearchParams({
        year:
          meta.year.toString(),
        period:
          meta.period,
        filter,
        type:
          activeTab,
      });

    window.open(
      `/dashboard/companies/evolution/export?${params}`,
      "_blank"
    );
  };

  /*
  ==================================================
  📅 FORMAT DATE
  ==================================================
  */

  const formatDate = (
    date: string
  ) => {

    if (!date) return "-";

    /*
    🔥 FIX TIMEZONE BUG
    */
    return dayjs(date)
      .locale("es")
      .format("DD-MMM")
      .replace(".", "")
      .toLowerCase();
  };

  /*
  ==================================================
  🏷 LABELS
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

  const getRangeLabel = (
    period
  ) => {
    if (period?.label) {
      return period.label;
    }
    return `${formatDate(
      period.start_date
    )} → ${formatDate(
      period.end_date
    )}`;
  };

  /*
  ==================================================
  🎨 RENDER
  ==================================================
  */

  return (
    <Dialog
      open={open}
      onOpenChange={onClose}
    >
      <DialogContent
        className="
          !w-[80vw]
          !max-w-[65vw]
          p-0
        "
      >
        <div
          className="
            flex
            flex-col
            max-h-[80vh]
          "
        >
          {/* HEADER */}

          <DialogHeader
            className="
              px-6
              pt-6
              pb-4
              border-b
            "
          >
            <DialogTitle
              className="
                text-2xl
                font-bold
              "
            >
              {getTitle()}
            </DialogTitle>

            <DialogDescription>
              Distribución de empresas por demanda laboral
            </DialogDescription>
          </DialogHeader>

          {/* CONTROLS */}

          <div
            className="
              px-6
              py-4
              border-b
              flex
              flex-wrap
              justify-between
              items-center
              gap-4
            "
          >
            {/* LEFT */}

            <div
              className="
                flex
                items-center
                gap-3
                flex-wrap
              "
            >
              {/* TABS */}

              <div className="flex gap-2">
                {[
                  "national",
                  "international",
                ].map((tab) => (
                  <button
                    key={tab}
                    onClick={() => {
                      setActiveTab(tab);
                      setPage(1);
                    }}
                    className={`
                      px-4
                      py-2
                      rounded-lg
                      text-sm
                      font-semibold
                      transition
                      ${
                        activeTab === tab
                          ? `
                            bg-teal-500
                            text-white
                          `
                          : `
                            bg-slate-100
                            text-slate-600
                            hover:bg-slate-200
                          `
                      }
                    `}
                  >
                    {
                      tab === "national"
                        ? "Nacional"
                        : "Internacional"
                    }
                  </button>
                ))}
              </div>

              {/* FILTER */}

              <select
                value={filter}
                onChange={(e) => {
                  setFilter(
                    e.target.value
                  );
                }}
                className="
                  border
                  rounded-lg
                  px-3
                  py-2
                  text-sm
                  bg-white
                "
              >
                <option value="weekly">Semanal</option>
                <option value="biweekly">Quincenal</option>
                <option value="monthly">Mensual</option>
              </select>
            </div>

            {/* RIGHT */}

            <div
              className="
                flex
                items-center
                gap-3
              "
            >
              <div
                className="
                  text-sm
                  text-slate-500
                "
              >
                {
                  dataset.length > 0 &&
                  `${dataset.length} periodos`
                }
              </div>

              <button
                onClick={downloadExcel}
                className="
                  flex
                  items-center
                  gap-2
                  px-4
                  py-2
                  border
                  rounded-lg
                  text-sm
                  font-semibold
                  hover:bg-slate-50
                  transition
                "
              >
                <Download
                  className="
                    w-4
                    h-4
                  "
                />
                Exportar Excel
              </button>
            </div>
          </div>

          {/* CONTENT */}

          <div
            className="
              overflow-y-auto
              px-6
              py-5
              space-y-5
            "
          >
            {loading && (
              <p
                className="
                  text-sm
                  text-slate-500
                "
              >
                Cargando evolución...
              </p>
            )}

            {!loading &&
              dataset.length === 0 && (
              <p
                className="
                  text-sm
                  text-slate-400
                "
              >
                No hay datos disponibles
              </p>
            )}

            {!loading &&
              dataset.map((period, i) => (
                <div
                  key={i}
                  className="
                    border
                    rounded-xl
                    p-5
                    bg-white
                  "
                >
                  {/* PERIOD HEADER */}

                  <div
                    className="
                      flex
                      justify-between
                      items-center
                      mb-4
                    "
                  >
                    <div
                      className="
                        flex
                        items-center
                        gap-3
                      "
                    >
                      <Calendar
                        className="
                          w-5
                          h-5
                          text-teal-500
                        "
                      />
                      <div>
                        <p
                          className="
                            font-semibold
                          "
                        >
                          {getRangeLabel(period)}
                        </p>
                        <p
                          className="
                            text-xs
                            text-slate-500
                          "
                        >
                          {formatDate(period.start_date)}
                          {" → "}
                          {formatDate(period.end_date)}
                        </p>
                      </div>
                    </div>

                    {/* <div className="text-right">
                      <p
                        className="
                          text-xs
                          text-slate-400
                        "
                      >
                        Total
                      </p>
                      <p
                        className="
                          text-lg
                          font-bold
                          text-teal-600
                        "
                      >
                        {period.total_jobs}
                      </p>
                    </div> */}
                  </div>

                  {/* COMPANIES GRID */}

                  <div
                    className="
                      grid
                      grid-cols-1
                      md:grid-cols-2
                      lg:grid-cols-3
                      gap-3
                    "
                  >
                    {period.companies?.map((c, idx) => (
                      <div
                        key={idx}
                        className="
                          bg-slate-100
                          rounded-xl
                          p-4
                        "
                      >
                        <div
                          className="
                            flex
                            items-center
                            gap-2
                            mb-2
                          "
                        >
                          <Building2
                            className="
                              w-4
                              h-4
                              text-slate-500
                            "
                          />
                          <span
                            className="
                              text-xs
                              font-bold
                              text-slate-500
                            "
                          >
                            #{idx + 1}
                          </span>
                        </div>

                        <p
                          className="
                            text-sm
                            font-semibold
                            text-[#0A2540]
                            truncate
                          "
                          title={c.company}
                        >
                          {c.company}
                        </p>

                        <div
                          className="
                            flex
                            justify-between
                            mt-2
                            text-xs
                            text-slate-500
                          "
                        >
                          <span>
                            {c.jobs} vacantes
                          </span>
                          {/* <span>
                            {c.percentage}%
                          </span> */}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              ))}
          </div>

          {/* PAGINATION */}

          {!loading &&
            pagination?.last_page > 1 && (
            <div
              className="
                p-4
                border-t
                flex
                justify-between
                items-center
              "
            >
              <button
                disabled={pagination.current_page === 1}
                onClick={() => {
                  fetchData(page - 1);
                }}
                className="
                  px-5
                  py-2
                  bg-slate-200
                  rounded-lg
                  text-sm
                  font-semibold
                  disabled:opacity-40
                "
              >
                ← Anterior
              </button>

              <span
                className="
                  text-sm
                  text-slate-600
                  font-medium
                "
              >
                Página {pagination.current_page} de {pagination.last_page}
              </span>

              <button
                disabled={
                  pagination.current_page === pagination.last_page
                }
                onClick={() => {
                  fetchData(page + 1);
                }}
                className="
                  px-5
                  py-2
                  bg-teal-500
                  text-white
                  rounded-lg
                  text-sm
                  font-semibold
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
