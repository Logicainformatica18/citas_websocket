import { useEffect, useState } from "react";
import axios from "axios";
import {
  Dialog,
  DialogContent,
  DialogTitle,
  DialogDescription,
  DialogHeader,
} from "@/components/ui/dialog";

export function SeniorityEvolutionModal({ open, onClose }) {
  const [data, setData] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [page, setPage] = useState(1);
  const [filter, setFilter] = useState("weekly");
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!open) return;

    setLoading(true);

    axios
      .get("/dashboard/indicators/seniority/evolution", {
        params: { filter, page, per_page: 6 },
      })
      .then((res) => {
        setData(res.data.data);
        setPagination(res.data.pagination);
      })
      .finally(() => setLoading(false));
  }, [open, filter, page]);

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-5xl p-0">

        <div className="flex flex-col max-h-[75vh]">

          {/* HEADER */}
          <div className="p-5 border-b">
            <DialogTitle>Evolución Nivel Profesional</DialogTitle>
            <DialogDescription>
              Distribución de niveles en el tiempo
            </DialogDescription>

            <select
              value={filter}
              onChange={(e) => {
                setFilter(e.target.value);
                setPage(1);
              }}
              className="mt-3 border px-3 py-2 rounded-md text-sm"
            >
              <option value="weekly">Semanal</option>
              <option value="biweekly">Quincenal</option>
              <option value="monthly">Mensual</option>
            </select>
          </div>

          {/* CONTENT */}
          <div className="overflow-y-auto px-5 py-4 space-y-5">

            {loading && <p className="text-sm text-slate-500">Cargando...</p>}

            {data.map((p, i) => (
              <div key={i} className="border rounded-xl p-4 bg-white">

                <div className="flex justify-between mb-3">
                  <div>
                    <p className="font-semibold">{p.label}</p>
                    <p className="text-xs text-slate-500">
                      {p.start_date} → {p.end_date}
                    </p>
                  </div>

                  <div className="text-right">
                    <p className="text-xs text-slate-400">Total</p>
                    <p className="text-lg font-bold text-teal-600">
                      {p.total_jobs}
                    </p>
                  </div>
                </div>

                <div className="grid grid-cols-3 gap-3">

                  {p.distribution.map((d, idx) => (
                    <div key={idx} className="bg-slate-100 p-3 rounded text-center">
                      <p className="text-xs">{d.level}</p>
                      <p className="font-bold text-teal-600">{d.percentage}%</p>
                      <p className="text-xs text-slate-500">{d.jobs}</p>
                    </div>
                  ))}

                </div>

              </div>
            ))}

          </div>

          {/* PAGINACIÓN */}
          {pagination && (
            <div className="p-4 border-t flex justify-between items-center">

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