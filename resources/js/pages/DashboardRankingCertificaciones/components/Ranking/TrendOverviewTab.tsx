import { useEffect, useState } from "react";
import axios from "axios";

type TrendSource = {
  title?: string;
  url: string;
};

type TrendDetail = {
  title: string;
  score: number;
  regions: string[];
  summary?: string;
  key_drivers?: string[];
  sources?: TrendSource[];
};

type Props = {
  trendId: number;
};

/* =========================
   Utils
========================= */
const getDomainLabel = (url: string) => {
  try {
    return new URL(url).hostname.replace("www.", "");
  } catch {
    return "Fuente externa";
  }
};

export default function TrendOverviewTab({ trendId }: Props) {
  const [trend, setTrend] = useState<TrendDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!trendId) return;

    setLoading(true);
    setError(null);

    axios
      .get(`/trends/${trendId}/detail`)
      .then((res) => {
        setTrend(res.data?.trend ?? null);
      })
      .catch(() => {
        setError("No se pudo cargar la información de la tendencia.");
      })
      .finally(() => setLoading(false));
  }, [trendId]);

  /* =========================
     ESTADOS
  ========================= */
  if (loading) {
    return <p className="text-sm text-slate-500">Cargando tendencia…</p>;
  }

  if (error) {
    return <p className="text-sm text-red-500">{error}</p>;
  }

  if (!trend) {
    return (
      <p className="text-sm text-slate-500">
        No hay información disponible para esta tendencia.
      </p>
    );
  }

  /* =========================
     RENDER
  ========================= */
  return (
    <div className="space-y-6">
      {/* ================= HEADER ================= */}
      <div>
        <h3 className="text-xl font-semibold text-slate-900 dark:text-slate-100">
          {trend.title}
        </h3>

        <div className="mt-2 flex flex-wrap gap-2 text-xs uppercase tracking-widest">
          <span className="px-2 py-1 rounded-full bg-sky-100 text-sky-700 dark:bg-[#14384F] dark:text-sky-300">
            🌍 {trend.regions.join(" · ")}
          </span>

          <span className="px-2 py-1 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
            📈 Score {trend.score}
          </span>
        </div>
      </div>

      {/* ================= SUMMARY ================= */}
      {trend.summary && (
        <div className="rounded-xl border p-4 bg-slate-50 dark:bg-[#14384F] dark:border-[#1E3A4A]">
          <p className="text-sm text-slate-700 dark:text-slate-300 leading-relaxed">
            {trend.summary}
          </p>
        </div>
      )}

      {/* ================= DRIVERS ================= */}
      {trend.key_drivers && trend.key_drivers.length > 0 && (
        <div>
          <h4 className="text-xs font-semibold uppercase tracking-widest text-slate-400 mb-3">
            Factores clave
          </h4>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {trend.key_drivers.map((driver) => (
              <div
                key={driver}
                className="rounded-lg border px-4 py-3 text-sm bg-white dark:bg-[#0F2A3A] dark:border-[#1E3A4A]"
              >
                {driver}
              </div>
            ))}
          </div>
        </div>
      )}

      {/* ================= SOURCES ================= */}
      {trend.sources && trend.sources.length > 0 && (
        <div>
          <h4 className="text-xs font-semibold uppercase tracking-widest text-slate-400 mb-3">
            Fuentes
          </h4>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {trend.sources.map((s) => (
              <a
                key={s.url}
                href={s.url}
                target="_blank"
                rel="noreferrer"
                className="
                  group rounded-xl border p-4
                  bg-white dark:bg-[#0F2A3A]
                  dark:border-[#1E3A4A]
                  hover:shadow-md transition
                "
              >
                <div className="text-sm font-semibold text-slate-900 dark:text-slate-100">
                  🌐 {getDomainLabel(s.url)}
                </div>

                {s.title && (
                  <div className="mt-1 text-xs text-slate-500">
                    {s.title}
                  </div>
                )}
              </a>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
