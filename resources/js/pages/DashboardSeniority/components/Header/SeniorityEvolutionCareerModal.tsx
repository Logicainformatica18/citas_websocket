import { useEffect, useState } from "react";
import axios from "axios";
import { usePage } from "@inertiajs/react";

import {
  Dialog,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from "@/components/ui/dialog";

export function SeniorityEvolutionCareerModal({ open, onClose }) {
  const { meta } = usePage().props as any;

  const [data, setData] = useState<any[]>([]);
  const [filter, setFilter] = useState("weekly");
  const [loading, setLoading] = useState(false);

  /* =========================
     FETCH
  ========================= */
  useEffect(() => {
    if (!open) return;

    setLoading(true);

    axios
      .get("/dashboard/indicators/seniority/evolution-careers", {
        params: {
          filter,
          year: meta.year,
          period: meta.period,
        },
      })
      .then((res) => {
        setData(res.data.data || []);
      })
      .catch(() => setData([]))
      .finally(() => setLoading(false));
  }, [open, filter, meta.year, meta.period]);

  /* =========================
     EXPORT
  ========================= */
  const downloadExcel = () => {
    const params = new URLSearchParams({
      year: meta.year.toString(),
      period: meta.period,
      filter,
    });

    window.open(
      `/dashboard/indicators/seniority/evolution-careers/export?${params}`,
      "_blank"
    );
  };

  /* =========================
     HELPER TOTAL (fallback)
  ========================= */
  const getTotal = (period: any) => {
    if (period.total_jobs !== undefined) return period.total_jobs;

    // fallback si backend no lo manda
    return period.careers?.reduce((sum: number, c: any) => sum + c.jobs, 0);
  };

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-6xl p-0">

        <div className="flex flex-col max-h-[80vh]">

          {/* ================= HEADER ================= */}
          <div className="p-5 border-b flex flex-col gap-4">

            <div>
              <DialogTitle>Evolución por Carrera</DialogTitle>
              <DialogDescription>
                Evolución de vacantes tecnológicas agrupadas por carrera
              </DialogDescription>
            </div>

            <div className="flex justify-between items-center gap-3">

              {/* FILTRO */}
              <select
                value={filter}
                onChange={(e) => setFilter(e.target.value)}
                className="border px-3 py-2 rounded-md text-sm"
              >
                <option value="weekly">Semanal</option>
                <option value="biweekly">Quincenal</option>
                <option value="monthly">Mensual</option>
              </select>

              {/* EXPORT */}
              <button
                onClick={downloadExcel}
                className="px-4 py-2 border rounded-lg text-sm font-semibold hover:bg-slate-50"
              >
                📥 Exportar Excel
              </button>

            </div>

          </div>

          {/* ================= CONTENT ================= */}
          <div className="flex-1 overflow-y-auto px-5 py-4 space-y-6">

            {loading && (
              <p className="text-sm text-slate-500">Cargando...</p>
            )}

            {!loading && data.length === 0 && (
              <p className="text-sm text-slate-400">
                No hay datos disponibles
              </p>
            )}

            {!loading && data.map((period, i) => (
              <div
                key={i}
                className="border rounded-xl p-4 bg-white shadow-sm"
              >

                {/* HEADER PERIODO */}
                <div className="flex justify-between items-center mb-4">

                  <div>
                    <p className="font-semibold text-[#0A2540]">
                      {period.label}
                    </p>

                    {period.start_date && (
                      <p className="text-xs text-slate-400">
                        {period.start_date} → {period.end_date}
                      </p>
                    )}
                  </div>

                  <div className="text-right">
                    <p className="text-xs text-slate-400">Total</p>
                    <p className="text-lg font-bold text-teal-600">
                      {getTotal(period)}
                    </p>
                  </div>

                </div>

                {/* LISTA DE CARRERAS */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">

                  {period.careers?.map((c: any, idx: number) => (
                    <div
                      key={idx}
                      className="bg-slate-100 p-4 rounded-lg"
                    >
                      <p className="text-sm font-semibold text-teal-600">
                        {c.career_name}
                      </p>

                      <p className="text-lg font-bold text-[#0A2540]">
                        {c.jobs} vacantes
                      </p>

                      <p className="text-xs text-slate-500">
                        {c.percentage}%
                      </p>
                    </div>
                  ))}

                </div>

              </div>
            ))}

          </div>

        </div>

      </DialogContent>
    </Dialog>
  );
}