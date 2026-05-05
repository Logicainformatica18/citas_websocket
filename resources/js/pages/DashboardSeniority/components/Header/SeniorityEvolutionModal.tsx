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

  /* =========================
     FETCH
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
        },
      })
      .then((res) => {
        setData(res.data.data);
        setPagination(res.data.pagination);
      })
      .finally(() => setLoading(false));
  }, [open, filter, page]);

  /* =========================
     EXPORT
  ========================= */
  const downloadExcel = () => {
    const params = new URLSearchParams({
      year: meta.year.toString(),
      period: meta.period,
      filter,
    });

    window.location.href = `/dashboard/indicators/seniority/evolution/export?${params}`;
  };

  const downloadByCareer = () => {
    const params = new URLSearchParams({
      year: meta.year.toString(),
      period: meta.period,
      filter,
      career: filters?.career || [],
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
              <DialogTitle>Evolución Nivel Profesional</DialogTitle>
              <DialogDescription>
                Distribución de niveles en el tiempo
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
                className="border px-3 py-2 rounded-md text-sm"
              >
                <option value="weekly">Semanal</option>
                <option value="biweekly">Quincenal</option>
                <option value="monthly">Mensual</option>
              </select>

              {/* ACCIONES */}
              <div className="flex gap-2">

                <button
                  onClick={downloadExcel}
                  className="px-4 py-2 bg-white border rounded-lg text-sm font-semibold hover:bg-slate-50"
                >
                  📥 Excel
                </button>

                {filters?.career?.length > 0 && (
                  <button
                    onClick={downloadByCareer}
                    className="px-4 py-2 bg-white border rounded-lg text-sm font-semibold hover:bg-slate-50"
                  >
                    📊 Carrera
                  </button>
                )}

              </div>

            </div>
          </div>

          {/* ================= CONTENT ================= */}
          <div className="flex-1 overflow-y-auto px-5 py-4 space-y-5">

            {loading && (
              <p className="text-sm text-slate-500">Cargando...</p>
            )}

            {!loading && data.length === 0 && (
              <p className="text-sm text-slate-400">
                No hay datos disponibles
              </p>
            )}

            {!loading &&
              data.map((p, i) => (
                <div
                  key={i}
                  className="border rounded-xl p-4 bg-white shadow-sm"
                >

                  {/* HEADER CARD */}
                  <div className="flex justify-between mb-3">

                    <div>
                      <p className="font-semibold">{p.label}</p>
                      <p className="text-xs text-slate-500">
                        {p.start_date} → {p.end_date}
                      </p>
                    </div>

                    <div className="text-right">
                      <p className="text-xs text-slate-400">
                        Total vacantes
                      </p>
                      <p className="text-lg font-bold text-teal-600">
                        {p.total_jobs}
                      </p>
                    </div>

                  </div>

                  {/* DISTRIBUCIÓN */}
                  <div className="grid grid-cols-2 md:grid-cols-3 gap-3">

                    {p.distribution.map((d, idx) => (
                      <div
                        key={idx}
                        className="bg-slate-100 p-3 rounded text-center"
                      >
                        <p className="text-xs uppercase text-slate-500">
                          {d.level}
                        </p>

                        <p className="font-bold text-teal-600 text-lg">
                          {d.percentage}%
                        </p>

                        <p className="text-xs text-slate-500">
                          {d.jobs} vacantes
                        </p>
                      </div>
                    ))}

                  </div>

                </div>
              ))}

          </div>

          {/* ================= PAGINACIÓN (FIJA) ================= */}
          {pagination && (
            <div className="p-4 border-t flex justify-between items-center bg-white">

              <button
                disabled={page <= 1}
                onClick={() => setPage((p) => p - 1)}
                className="px-4 py-2 bg-slate-200 rounded disabled:opacity-40"
              >
                ← Anterior
              </button>

              <span className="text-sm">
                Página {pagination.current_page} de {pagination.last_page}
              </span>

              <button
                disabled={page >= pagination.last_page}
                onClick={() => setPage((p) => p + 1)}
                className="px-4 py-2 bg-teal-500 text-white rounded disabled:opacity-40"
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
