import { X } from "lucide-react";
import TrendOverviewTab from "./TrendOverviewTab";
import TrendJobsTab from "./TrendJobsTab";
import { useEffect, useState } from "react";
import axios from "axios";

type Availability = {
  trend: boolean;
  jobs: boolean;
};

type Props = {
  open: boolean;
  trendId: number;
  activeTab: "trend" | "jobs";
  onClose: () => void;
  onTabChange: (tab: "trend" | "jobs") => void;
};

export default function TrendDetailModal({
  open,
  trendId,
  activeTab,
  onClose,
  onTabChange,
}: Props) {
  const [availability, setAvailability] = useState<Availability | null>(null);
  const [loading, setLoading] = useState(true);

  /* =========================
     LOAD AVAILABILITY
  ========================= */
  useEffect(() => {
    if (!open || !trendId) return;

    setLoading(true);

    axios
      .get(`/trends/${trendId}/detail`)
      .then((res) => {
        setAvailability(res.data?.availability ?? null);
      })
      .finally(() => setLoading(false));
  }, [open, trendId]);

  if (!open) return null;

  const hasTrend = availability?.trend === true;
  const hasJobs = availability?.jobs === true;

  /* =========================
     BLOQUEO REAL DE TABS
  ========================= */
  const safeTabChange = (tab: "trend" | "jobs") => {
    if (tab === "trend" && !hasTrend) return;
    if (tab === "jobs" && !hasJobs) return;
    onTabChange(tab);
  };

  /* =========================
     AJUSTE AUTOMÁTICO
  ========================= */
  useEffect(() => {
    if (!availability) return;

    if (activeTab === "trend" && !hasTrend && hasJobs) {
      onTabChange("jobs");
    }

    if (activeTab === "jobs" && !hasJobs && hasTrend) {
      onTabChange("trend");
    }
  }, [availability]);

  return (
    <div className="fixed inset-0 z-[9999] flex items-center justify-center">
      {/* BACKDROP */}
      <div
        className="absolute inset-0 bg-black/50"
        onClick={onClose}
      />

      {/* MODAL */}
      <div className="relative w-full max-w-5xl mx-4 rounded-2xl bg-white dark:bg-[#0F2A3A] shadow-xl">
        {/* HEADER */}
        <div className="flex items-center justify-between px-6 py-4 border-b dark:border-[#1E3A4A]">
          <h3 className="text-lg font-semibold">
            Detalle de tendencia
          </h3>

          <button
            onClick={onClose}
            className="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-[#1E3A4A]"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* TABS */}
        <div className="flex gap-2 px-6 pt-4">
          {hasTrend && (
            <button
              onClick={() => safeTabChange("trend")}
              className={`px-4 py-2 rounded-lg text-sm font-semibold
                ${
                  activeTab === "trend"
                    ? "bg-[#1CBCE8] text-white"
                    : "bg-slate-100 dark:bg-[#1E3A4A]"
                }`}
            >
              Tendencias
            </button>
          )}

          {hasJobs && (
            <button
              onClick={() => safeTabChange("jobs")}
              className={`px-4 py-2 rounded-lg text-sm font-semibold
                ${
                  activeTab === "jobs"
                    ? "bg-[#1CBCE8] text-white"
                    : "bg-slate-100 dark:bg-[#1E3A4A]"
                }`}
            >
              Ofertas laborales
            </button>
          )}
        </div>

        {/* CONTENT */}
        <div className="px-6 py-6 max-h-[70vh] overflow-y-auto">
          {loading && (
            <p className="text-sm text-slate-500">
              Cargando información…
            </p>
          )}

          {!loading && activeTab === "trend" && hasTrend && (
            <TrendOverviewTab trendId={trendId} />
          )}

          {!loading && activeTab === "jobs" && hasJobs && (
            <TrendJobsTab trendId={trendId} />
          )}
        </div>
      </div>
    </div>
  );
}
