import { useEffect, useState } from "react";
import axios from "axios";
import { usePage } from "@inertiajs/react";

import {
  Dialog,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from "@/components/ui/dialog";

export function SeniorityEvolutionModal({ open, onClose }) {
  const { meta, filters } = usePage().props as any;

  const [data, setData] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [page, setPage] = useState(1);
  const [filter, setFilter] = useState("weekly");
  const [loading, setLoading] = useState(false);

  // 🎯 CAPTURAMOS EL SLUG DE LA CARRERA DESDE INERTIA (Por defecto 'global')
  const careerSlug = filters?.career_slug || "global";

  /* =========================
      FETCH (CON FILTRO DE CARRERA Y AÑO)
  ========================= */
  useEffect(() => {
    if (!open) return;

    setLoading(true);

    // 🔥 LOG 1: Ver qué tiene el objeto meta de Inertia al abrir el modal o cambiar filtros
    console.log("👀 [FRONTEND] Meta actual en usePage():", meta);

    axios
      .get("/dashboard/indicators/seniority/evolution", {
        params: {
          filter,
          page,
          per_page: 6,
          year: meta?.year, 
          period: meta?.period,
          career_slug: careerSlug,
        },
      })
      .then((res) => {
        setData(res.data.data);
        setPagination(res.data.pagination);
      })
      .catch((err) => console.error("Error cargando evolución:", err))
      .finally(() => setLoading(false));
  }, [open, filter, page, careerSlug, meta?.year, meta?.period]);

  /* =========================
      EXPORTACIÓN DIRECTA A EXCEL
  ========================= */
  const downloadExcel = () => {
    // Generamos las variables resolviendo los fallbacks por si meta viene vacío
    const dynamicYear = meta?.year ? meta.year.toString() : new Date().getFullYear().toString();
    const dynamicPeriod = meta?.period || "s1";

    const params = new URLSearchParams({
      year: dynamicYear,
      period: dynamicPeriod,
      filter: filter,
      career_slug: careerSlug,
    });

   

    window.location.href = `/dashboard/indicators/seniority/evolution/export?${params}`;
  };

  const downloadByCareer = () => {
    const dynamicYear = meta?.year ? meta.year.toString() : new Date().getFullYear().toString();
    const dynamicPeriod = meta?.period || "s1";

    const params = new URLSearchParams({
      year: dynamicYear,
      period: dynamicPeriod,
      filter: filter,
      career_slug: careerSlug,
    });

   
    window.location.href = `/dashboard/indicators/seniority/evolution-careers/export?${params}`;
  };

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-6xl p-0">

        <div className="flex flex-col max-h-[80vh]">

          {/* ================= HEADER ================= */}
          <div className="p-5 border-b flex flex-col gap-4">

            <div>
              <DialogTitle className="flex items-center gap-2 text-xl font-bold text-slate-950">
                Evolución Nivel Profesional
                {careerSlug !== "global" && (
                  <span className="text-xs px-2 py-0.5 bg-teal-50 border border-teal-200 text-teal-700 rounded-full font-normal capitalize">
                    {careerSlug.replace(/-/g, " ")}
                  </span>
                )}
              </DialogTitle>
              <DialogDescription className="text-slate-500">
                Distribución de niveles profesionales en base a muestras estrictas por periodo
              </DialogDescription>
            </div>

            {/* CONTROLES INTERNOS */}
            <div className="flex flex-wrap items-center justify-between gap-3">

              {/* FILTRO DE GRANULARIDAD */}
              <select
                value={filter}
                onChange={(e) => {
                  setFilter(e.target.value);
                  setPage(1);
                }}
                className="border px-3 py-2 rounded-md text-sm bg-white font-medium text-slate-700 outline-none focus:ring-2 focus:ring-teal-500/20"
              >
                <option value="weekly">Semanal</option>
                <option value="biweekly">Quincenal</option>
                <option value="monthly">Mensual</option>
              </select>

              {/* ACCIONES DE DESCARGA */}
              <div className="flex gap-2">

                <button
                  onClick={downloadExcel}
                  className="px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition-colors shadow-sm"
                >
                  📥 Exportar Vista Actual
                </button>

                {careerSlug !== "global" && (
                  <button
                    onClick={downloadByCareer}
                    className="px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition-colors shadow-sm"
                  >
                    📊 Reporte Consolidado
                  </button>
                )}

              </div>

            </div>
          </div>

          {/* ================= CONTENT (CARDS) ================= */}
          <div className="flex-1 overflow-y-auto px-5 py-4 space-y-5 bg-slate-50/50">

            {loading && (
              <div className="flex items-center justify-center py-10">
                <p className="text-sm text-slate-500 animate-pulse">Cargando métricas de evolución...</p>
              </div>
            )}

            {!loading && data.length === 0 && (
              <div className="text-center py-10 border border-dashed rounded-xl bg-white">
                <p className="text-sm text-slate-400">
                  No se registran vacantes ni variaciones en este tramo para la carrera seleccionada
                </p>
              </div>
            )}

            {!loading &&
              data.map((p, i) => (
                <div
                  key={i}
                  className="border border-slate-150 rounded-xl p-4 bg-white shadow-sm hover:shadow-md transition-shadow"
                >

                  {/* HEADER CARD */}
                  <div className="flex justify-between items-start mb-4">
                    <div>
                      <p className="font-bold text-slate-800 text-base">{p.label}</p>
                      <p className="text-xs text-slate-400 font-medium mt-0.5">
                        Rango de corte: {p.start_date} al {p.end_date}
                      </p>
                    </div>

                    <div className="text-right">
                      <p className="text-[10px] text-slate-400 uppercase tracking-wider font-bold">
                        Muestra Absoluta
                      </p>
                      <p className="text-xl font-black text-slate-800">
                        {p.total_jobs ? p.total_jobs.toLocaleString() : 0}
                      </p>
                    </div>
                  </div>

                  {/* DISTRIBUCIÓN DE SENIORITIES */}
                  <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                    {p.distribution?.map((d, idx) => (
                      <div
                        key={idx}
                        className="bg-slate-50 border border-slate-100 p-3 rounded-lg text-center"
                      >
                        <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                          {d.level}
                        </p>

                        <p className="font-extrabold text-teal-600 text-2xl">
                          {d.percentage}%
                        </p>

                        <p className="text-xs text-slate-500 font-medium mt-1">
                          {d.jobs ? d.jobs.toLocaleString() : 0} vacantes
                        </p>
                      </div>
                    ))}
                  </div>

                </div>
              ))}

          </div>

          {/* ================= PAGINACIÓN ================= */}
          {pagination && !loading && data.length > 0 && (
            <div className="p-4 border-t flex justify-between items-center bg-white shadow-[0_-2px_10px_rgba(0,0,0,0.02)]">

              <button
                disabled={page <= 1}
                onClick={() => setPage((p) => p - 1)}
                className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-sm rounded-lg disabled:opacity-40 disabled:hover:bg-slate-100 transition-colors"
              >
                ← Anterior
              </button>

              <span className="text-sm font-medium text-slate-600">
                Página {pagination.current_page} de {pagination.last_page}
              </span>

              <button
                disabled={page >= pagination.last_page}
                onClick={() => setPage((p) => p + 1)}
                className="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-semibold text-sm rounded-lg disabled:opacity-40 disabled:hover:bg-slate-900 transition-colors"
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