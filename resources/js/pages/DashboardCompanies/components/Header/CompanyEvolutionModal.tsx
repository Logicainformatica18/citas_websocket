import { useEffect, useState } from "react";
import axios from "axios";
import {
  Dialog,
  DialogContent,
  DialogTitle,
  DialogDescription,
  DialogHeader,
} from "@/components/ui/dialog";

export function CompanyEvolutionModal({ open, onClose }) {
  const [data, setData] = useState({
    national: { data: [], pagination: {} },
    international: { data: [], pagination: {} },
  });

  const [activeTab, setActiveTab] = useState("national");
  const [filter, setFilter] = useState("weekly");
  const [page, setPage] = useState(1);

  const [loading, setLoading] = useState(false);

  /* =========================
     FETCH
  ========================= */
  const fetchData = (pageToLoad = 1) => {
    setLoading(true);

    axios
      .get("/dashboard/indicators/companies/evolution", {
        params: {
          filter,
          page: pageToLoad,
        },
      })
      .then((res) => {
        setData(res.data);
        setPage(pageToLoad);
      })
      .catch((err) => {
        console.error("❌ Error evolución empresas:", err);
      })
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    if (!open) return;
    fetchData(1);
  }, [open, filter]);

  const dataset = data?.[activeTab]?.data ?? [];
  const pagination = data?.[activeTab]?.pagination ?? {};

  return (
    <Dialog open={open} onOpenChange={onClose}>
<DialogContent className="!w-[80vw] !max-w-[65vw]">

        {/* HEADER */}
        <DialogHeader>
          <DialogTitle>Evolución de Empresas</DialogTitle>
          <DialogDescription>
            Distribución de empresas en el tiempo
          </DialogDescription>
        </DialogHeader>

        {/* CONTROLES */}
        <div className="flex flex-wrap items-center justify-between gap-4 mt-3">

          {/* LEFT */}
          <div className="flex items-center gap-3">

            {/* Tabs */}
            <div className="flex gap-2">
              {["national", "international"].map((tab) => (
                <button
                  key={tab}
                  onClick={() => setActiveTab(tab)}
                  className={`px-4 py-2 rounded-lg text-sm font-semibold ${
                    activeTab === tab
                      ? "bg-teal-400 text-white"
                      : "bg-slate-100 text-slate-600"
                  }`}
                >
                  {tab === "national" ? "Nacional" : "Internacional"}
                </button>
              ))}
            </div>

            {/* Filtro */}
            <select
              value={filter}
              onChange={(e) => setFilter(e.target.value)}
              className="border px-3 py-2 rounded-md text-sm"
            >
              <option value="weekly">Semanal</option>
              <option value="biweekly">Quincenal</option>
              <option value="monthly">Mensual</option>
            </select>
          </div>

          {/* RIGHT */}
          <div className="text-sm text-slate-500">
            {dataset.length > 0 && `${dataset.length} periodos`}
          </div>

        </div>

        {/* CONTENT */}
        <div className="space-y-4 mt-4 max-h-[65vh] overflow-y-auto">

          {loading && (
            <div className="text-sm text-slate-500">
              Cargando evolución...
            </div>
          )}

          {!loading && dataset.length === 0 && (
            <div className="text-sm text-slate-400">
              No hay datos disponibles
            </div>
          )}

          {!loading &&
            dataset.map((period, i) => (
              <div
                key={i}
                className="border rounded-xl p-4 bg-white shadow-sm"
              >
                {/* HEADER PERIODO */}
                <div className="flex justify-between items-center mb-3">
                  <p className="font-semibold">
                    {period.label}
                  </p>

                  <span className="text-sm text-slate-500">
                    {period.total_jobs} vacantes
                  </span>
                </div>

                {/* EMPRESAS */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">

                  {period.companies.map((c, idx) => (
                    <div
                      key={idx}
                      className="bg-slate-100 rounded-lg p-3"
                    >
                      <p className="text-sm font-semibold text-[#0A2540]">
                        {c.company}
                      </p>

                      <div className="flex justify-between text-xs mt-1 text-slate-500">
                        <span>{c.jobs} vacantes</span>
                        <span>{c.percentage}%</span>
                      </div>
                    </div>
                  ))}

                </div>
              </div>
            ))}

        </div>

        {/* PAGINACIÓN */}
        {!loading && pagination?.last_page > 1 && (
          <div className="flex justify-center gap-2 mt-4">

            <button
              disabled={pagination.current_page === 1}
              onClick={() => fetchData(page - 1)}
              className="px-3 py-1 border rounded disabled:opacity-40"
            >
              ←
            </button>

            <span className="text-sm px-2">
              Página {pagination.current_page} de {pagination.last_page}
            </span>

            <button
              disabled={pagination.current_page === pagination.last_page}
              onClick={() => fetchData(page + 1)}
              className="px-3 py-1 border rounded disabled:opacity-40"
            >
              →
            </button>

          </div>
        )}

      </DialogContent>
    </Dialog>
  );
}