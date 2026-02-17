import { useEffect, useState } from "react";

export default function CourseGapsTab({ course }: any) {
  const [data, setData] = useState<any>({
    total: 0,
    conectadas: 0,
    brechas: [],
  });

  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!course?.id) return;

    setLoading(true);

    fetch(`/indicators/course/${course.id}/gaps`)
      .then(res => res.json())
      .then(setData)
      .finally(() => setLoading(false));

  }, [course.id]);

  return (
    <div className="space-y-6">
      <h3 className="font-semibold">
        Análisis de brechas
      </h3>

      {loading && (
        <p className="text-sm text-muted-foreground">
          Analizando brechas...
        </p>
      )}

      {!loading && (
        <>
          <div className="grid grid-cols-3 gap-4 text-center">
            <StatCard
              label="Total entidades"
              value={data.total}
            />
            <StatCard
              label="Con señal de mercado"
              value={data.conectadas}
            />
            <StatCard
              label="Brechas detectadas"
              value={data.brechas.length}
              danger
            />
          </div>

          {data.brechas.length > 0 && (
            <div>
              <h4 className="font-medium mb-2">
                Entidades sin señal de mercado
              </h4>
              <div className="flex flex-wrap gap-2">
                {data.brechas.map((b: any) => (
                  <span
                    key={b.market_entity_id}
                    className="inline-flex items-center rounded-full bg-red-50 text-red-600 px-3 py-1 text-[11px]"
                  >
                    {b.name}
                  </span>
                ))}
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}

function StatCard({
  label,
  value,
  danger = false,
}: any) {
  return (
    <div className={`rounded-xl p-4 border ${
      danger ? "border-red-200 bg-red-50" : "bg-muted/40"
    }`}>
      <div className="text-lg font-semibold">
        {value}
      </div>
      <div className="text-xs text-muted-foreground">
        {label}
      </div>
    </div>
  );
}
