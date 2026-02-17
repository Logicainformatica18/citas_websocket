import { useEffect, useState } from "react";
import { Sparkles, Calendar, Link2, Globe } from "lucide-react";

export default function CourseTrendsTab({ course }: any) {
  const [trends, setTrends] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!course?.id) return;

    setLoading(true);

    fetch(`/indicators/course/${course.id}/trends`)
      .then((res) => res.json())
      .then((data) => setTrends(data))
      .finally(() => setLoading(false));
  }, [course?.id]);

  return (
    <div>
      <h3 className="font-semibold mb-4 flex items-center gap-2">
        <Sparkles size={16} className="text-[#1CBCE8]" />
        Tendencias detectadas
      </h3>

      {loading && (
        <p className="text-sm text-muted-foreground">
          Cargando tendencias...
        </p>
      )}

      {!loading && trends.length === 0 && (
        <p className="text-sm text-muted-foreground">
          No se detectaron tendencias recientes.
        </p>
      )}

      {!loading &&
        trends.map((trend) => (
          <div
            key={trend.id}
            className="border rounded-xl p-4 mb-3 hover:bg-muted/20 transition"
          >
            {/* TITULO */}
            <div className="font-medium text-sm mb-2">
              {trend.trend_name}
            </div>

            {/* META INFO */}
            <div className="flex flex-wrap gap-3 text-xs text-muted-foreground mb-2">
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

            {/* SOURCE TYPE BADGE */}
            {trend.source_type && (
              <div className="mt-1">
                <span className="inline-flex items-center rounded-full bg-[#E6F7FD] text-[#1CBCE8] px-3 py-1 text-[11px] font-medium">
                  {trend.source_type}
                </span>
              </div>
            )}
          </div>
        ))}
    </div>
  );
}
