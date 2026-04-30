import { useEffect, useState } from "react";
import axios from "axios";
import { Dialog, DialogContent } from "@/components/ui/dialog";
import { Trophy, Calendar } from "lucide-react";

export function WeeklyEvolutionModal({ open, onClose }) {
  const [data, setData] = useState([]);
  const [page, setPage] = useState(1);
  const [pagination, setPagination] = useState(null);

  useEffect(() => {
    if (!open) return;

    axios
      .get("/dashboard/ranking/technologies/weekly", {
        params: { page, per_page: 6 },
      })
      .then((res) => {
        setData(res.data.data);
        setPagination(res.data.pagination);
      });
  }, [open, page]);

  const formatDate = (date) =>
    new Date(date).toLocaleDateString("es-PE", {
      day: "2-digit",
      month: "short",
    });

  return (
    <Dialog open={open} onOpenChange={onClose}>
      {/* 🔥 SIN ALTURAS RARAS */}
      <DialogContent className="!max-w-[1000px] w-full p-0">

        <div className="flex flex-col max-h-[75vh]">

          {/* HEADER */}
          <div className="p-5 border-b">
            <h2 className="text-xl font-bold">Evolución semanal</h2>
            <p className="text-sm text-slate-500">
              Top tecnologías por demanda laboral
            </p>
          </div>

          {/* 🔥 SCROLL REAL */}
          <div className="overflow-y-auto px-5 py-4 space-y-5">

            {data.map((week) => (
              <div
                key={week.week}
                className="border rounded-xl p-4 bg-white"
              >

                {/* HEADER SEMANA */}
                <div className="flex justify-between items-center mb-3">

                  <div className="flex items-center gap-2">
                    <Calendar className="w-4 h-4 text-teal-500" />

                    <div>
                      <p className="font-semibold">
                        Semana {week.week}
                      </p>
                      <p className="text-xs text-slate-500">
                        {formatDate(week.start_date)} → {formatDate(week.end_date)}
                      </p>
                    </div>
                  </div>

                  <div className="text-right">
                    <p className="text-xs text-slate-400">
                      Total
                    </p>
                    <p className="text-lg font-bold text-teal-600">
                      {week.total_week}
                    </p>
                  </div>
                </div>

                {/* 🔥 GRID REAL */}
                <div className="grid grid-cols-2 md:grid-cols-5 gap-3">

                  {week.top.map((tech, i) => (
                    <div
                      key={i}
                      className={`p-3 rounded-lg ${
                        i === 0
                          ? "bg-teal-500 text-white"
                          : "bg-slate-100"
                      }`}
                    >
                      <div className="flex items-center gap-1 mb-1">
                        {i === 0 && <Trophy className="w-3 h-3" />}
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

          {/* 🔥 PAGINACIÓN GRANDE */}
          {pagination && (
            <div className="p-4 border-t flex justify-between items-center">

              <button
                disabled={page <= 1}
                onClick={() => setPage((p) => p - 1)}
                className="px-5 py-2 bg-slate-200 rounded-lg text-sm font-semibold disabled:opacity-40"
              >
                ← Anterior
              </button>

              <span className="text-sm text-slate-600 font-medium">
                Página {pagination.current_page} de {pagination.last_page}
              </span>

              <button
                disabled={page >= pagination.last_page}
                onClick={() => setPage((p) => p + 1)}
                className="px-5 py-2 bg-teal-500 text-white rounded-lg text-sm font-semibold disabled:opacity-40"
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