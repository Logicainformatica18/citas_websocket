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
      FETCH (CON FILTRO DE CARRERA)
  ========================= */
  useEffect(() => {
    if (!open) return;

    setLoading(true);

    axios
      .get("/dashboard/indicators/seniority/evolution", {
        params: {
          filter,
          page,
          per_page: 6,
          year: meta.year,
          period: meta.period,
          career_slug: careerSlug, // 👈 Enviado al método evolution adaptado
        },
      })
      .then((res) => {
        setData(res.data.data);
        setPagination(res.data.pagination);
      })
      .catch((err) => console.error("Error cargando evolución:", err))
      .finally(() => setLoading(false));
  }, [open, filter, page, careerSlug]); // Re-ejecuta si cambian de carrera en el fondo

  /* =========================
      EXPORT
  ========================= */
  const downloadExcel = () => {
    const params = new URLSearchParams({
      year: meta.year.toString(),
      period: meta.period,
      filter,
      career_slug: careerSlug, // Descarga el segmento que se visualiza actualmente
    });

    window.location.href = `/dashboard/indicators/seniority/evolution/export?${params}`;
  };

  const downloadByCareer = () => {
    const params = new URLSearchParams({
      year: meta.year.toString(),
      period: meta.period,
      filter,
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
              <DialogTitle className="flex items-center gap-2">
                Evolución Nivel Profesional
                {careerSlug !== "global" && (
                  <span className="text-xs px-2 py-0.5 bg-teal-50 border border-teal-200 text-teal-700 rounded-full font-normal capitalize">
                    {careerSlug.replace(/-/g, " ")}
                  </span>
                )}
              </DialogTitle>
              <DialogDescription>
                Distribución de niveles profesionales en base a muestras estrictas por periodo
              </DialogDescription>
            </div>

            {/* CONTROLES */}
            <div className="flex flex-wrap items-center justify-between gap-3">

              {/* FILTRO */}
              <select
                value={filter}
                onChange={(e) => {
                  setFilter(e.target.value);
                  setPage(1);
                }}
                className="border px-3 py-2 rounded-md text-sm bg-white"
              >
                <option value="weekly">Semanal</option>
                <option value="biweekly">Quincenal</option>
                <option value="monthly">Mensual</option>
              </select>

              {/* ACCIONES */}
              <div className="flex gap-2">

                <button
                  onClick={downloadExcel}
                  className="px-4 py-2 bg-white border rounded-lg text-sm font-semibold hover:bg-slate-50 transition-colors"
                >
                  📥 Exportar Vista Actual
                </button>

                {careerSlug !== "global" && (
                  <button
                    onClick={downloadByCareer}
                    className="px-4 py-2 bg-white border rounded-lg text-sm font-semibold hover:bg-slate-50 transition-colors"
                  >
                    📊 Reporte Consolidado
                  </button>
                )}

              </div>

            </div>
          </div>

          {/* ================= CONTENT ================= */}
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
                  className="border rounded-xl p-4 bg-white shadow-sm hover:shadow-md transition-shadow"
                >

                  {/* HEADER CARD */}
                  <div className="flex justify-between items-start mb-4">

                    <div>
                      <p className="font-semibold text-slate-800 text-base">{p.label}</p>
                      <p className="text-xs text-slate-400 font-medium mt-0.5">
                        Rango de corte: {p.start_date} al {p.end_date}
                      </p>
                    </div>

                    <div className="text-right">
                      <p className="text-xs text-slate-400 uppercase tracking-wider font-semibold">
                        Muestra Absoluta
                      </p>
                      <p className="text-xl font-black text-slate-800">
                        {p.total_jobs.toLocaleString()}
                      </p>
                    </div>

                  </div>

                  {/* DISTRIBUCIÓN (Muestra Dinámica con Totales y Porcentajes Reales) */}
                  <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">

                    {p.distribution.map((d, idx) => (
                      <div
                        key={idx}
                        className="bg-slate-50 border border-slate-100 p-3 rounded-lg text-center"
                      >
                        <p className="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">
                          {d.level}
                        </p>

                        <p className="font-extrabold text-teal-600 text-2xl">
                          {d.percentage}%
                        </p>

                        <p className="text-xs text-slate-500 font-medium mt-1">
                          {d.jobs.toLocaleString()} vacantes
                        </p>
                      </div>
                    ))}

                  </div>

                </div>
              ))}

          </div>

          {/* ================= PAGINACIÓN (FIJA) ================= */}
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
