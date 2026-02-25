import { useEffect, useState } from "react";
import {
  Sparkles,
  Calendar,
  Link2,
  Globe,
  ExternalLink,
} from "lucide-react";

export default function CourseTrendsTab({ course }: any) {
  const [trends, setTrends] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!course?.id) return;

    setLoading(true);

    fetch(`/indicators/course/${course.id}/trends`)
      .then((res) => res.json())
      .then((data) => setTrends(data ?? []))
      .finally(() => setLoading(false));
  }, [course?.id]);

  return (
    <div className="space-y-6">

      {/* ================= HEADER ================= */}
      <div className="flex items-center gap-2">
        <Sparkles
          size={18}
          className="text-yellow-500 dark:text-yellow-400"
        />
        <h3 className="font-semibold text-sm text-gray-800 dark:text-gray-100">
          Tendencias detectadas
        </h3>
      </div>

      {/* ================= LOADING ================= */}
      {loading && (
        <p className="text-sm text-gray-500 dark:text-gray-400">
          Cargando tendencias...
        </p>
      )}

      {/* ================= EMPTY ================= */}
      {!loading && trends.length === 0 && (
        <p className="text-sm text-gray-500 dark:text-gray-400">
          No se detectaron tendencias recientes.
        </p>
      )}

      {/* ================= LIST ================= */}
      {!loading &&
        trends.map((trend) => (
          <TrendCard key={trend.id} trend={trend} />
        ))}
    </div>
  );
}

/* ======================================================
   TREND CARD
====================================================== */

function TrendCard({ trend }: any) {
  const url = trend.source_url || null;

  return (
    <a
      href={url || "#"}
      target="_blank"
      rel="noopener noreferrer"
      className="
        block rounded-2xl p-5
        border border-gray-200 dark:border-gray-800
        bg-gray-50 dark:bg-slate-800/60
        hover:bg-gray-100 dark:hover:bg-slate-800
        hover:shadow-md
        transition-all
      "
    >
      {/* ================= TITLE ================= */}
      <div className="flex justify-between items-start mb-2">
        <div className="font-medium text-sm text-gray-800 dark:text-gray-100">
          {trend.trend_name}
        </div>

        {url && (
          <ExternalLink size={14} className="text-gray-400" />
        )}
      </div>

      {/* ================= META INFO ================= */}
      <div className="flex flex-wrap gap-4 text-xs text-gray-500 dark:text-gray-400 mb-3">

        <span className="flex items-center gap-1">
          <Globe size={12} />
          {trend.entity_name}
        </span>

        <span className="flex items-center gap-1">
          <Calendar size={12} />
          {trend.year} Q{trend.quarter}
        </span>

        {trend.source_title && (
          <span className="flex items-center gap-1">
            <Link2 size={12} />
            {trend.source_title}
          </span>
        )}
      </div>

      {/* ================= BADGE ================= */}
      {trend.source_type && (
        <SourceBadge type={trend.source_type} />
      )}
    </a>
  );
}

/* ======================================================
   SOURCE BADGE DINÁMICO
====================================================== */

function SourceBadge({ type }: any) {
  const map: any = {
    news: "bg-blue-100 text-blue-700 border-blue-300 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700",
    report: "bg-emerald-100 text-emerald-700 border-emerald-300 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-700",
    blog: "bg-pink-100 text-pink-700 border-pink-300 dark:bg-pink-900/30 dark:text-pink-300 dark:border-pink-700",
    academic: "bg-violet-100 text-violet-700 border-violet-300 dark:bg-violet-900/30 dark:text-violet-300 dark:border-violet-700",
  };

  const style =
    map[type?.toLowerCase()] ||
    "bg-sky-100 text-sky-700 border-sky-300 dark:bg-sky-900/30 dark:text-sky-300 dark:border-sky-700";

  return (
    <span
      className={`inline-flex items-center rounded-full px-3 py-1 text-[11px] font-medium border ${style}`}
    >
      {type}
    </span>
  );
}