type Props = {
  data: any[];
};

const COLORS = {
  junior: "#005B96",
  mid: "#00B6E8",
  senior: "#F4D03F",
};

export function SeniorityBarChart({ data }: Props) {
  /* ============================
     Transformar data dinámica
  ============================ */
  const transformed = data.map((career) => {
    const row = {
      carrera: career.career_name,
      junior: 0,
      mid: 0,
      senior: 0,
    };

    career.distribution.forEach((d: any) => {
      row[d.seniority] = d.jobs ?? 0;
    });

    return row;
  });

  const sorted = [...transformed].sort(
    (a, b) => b.junior + b.mid + b.senior - (a.junior + a.mid + a.senior)
  );

  const maxTotal = Math.max(
    ...sorted.map((d) => d.junior + d.mid + d.senior),
    1
  );

  const totals = {
    junior: sorted.reduce((s, d) => s + d.junior, 0),
    mid: sorted.reduce((s, d) => s + d.mid, 0),
    senior: sorted.reduce((s, d) => s + d.senior, 0),
  };

  return (
    <div className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-[#0F2A3A] p-6 shadow-sm">

      {/* HEADER */}
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-xl font-semibold text-slate-900 dark:text-white">
            Seniority por Carrera
          </h2>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Distribución de niveles de experiencia por área profesional
          </p>
        </div>

        {/* Leyenda */}
        <div className="flex items-center gap-6 text-sm font-medium">
          <LegendItem color={COLORS.junior} label="Junior" />
          <LegendItem color={COLORS.mid} label="Mid" />
          <LegendItem color={COLORS.senior} label="Senior" />
        </div>
      </div>

      {/* CHART */}
      <div className="space-y-4">
        {sorted.map((row) => {
          const total = row.junior + row.mid + row.senior;
          const pct = (v: number) => (v / maxTotal) * 100;

          return (
            <div key={row.carrera} className="flex items-center gap-4">

              {/* Nombre */}
              <span className="w-56 shrink-0 text-right text-sm font-medium text-slate-800 dark:text-slate-200 leading-tight">
                {row.carrera}
              </span>

              {/* Barra */}
              <div className="relative flex h-9 flex-1 overflow-hidden rounded-lg bg-slate-100 dark:bg-slate-800">

                {row.junior > 0 && (
                  <div
                    className="flex items-center justify-center text-xs font-semibold text-white transition-all duration-500"
                    style={{
                      width: `${pct(row.junior)}%`,
                      backgroundColor: COLORS.junior,
                    }}
                  >
                    {row.junior}
                  </div>
                )}

                {row.mid > 0 && (
                  <div
                    className="flex items-center justify-center text-xs font-semibold text-white transition-all duration-500"
                    style={{
                      width: `${pct(row.mid)}%`,
                      backgroundColor: COLORS.mid,
                    }}
                  >
                    {row.mid}
                  </div>
                )}

                {row.senior > 0 && (
                  <div
                    className="flex items-center justify-center text-xs font-semibold text-slate-900 transition-all duration-500"
                    style={{
                      width: `${pct(row.senior)}%`,
                      backgroundColor: COLORS.senior,
                    }}
                  >
                    {row.senior}
                  </div>
                )}
              </div>

              {/* Total */}
              <span className="w-10 text-right text-sm font-bold text-slate-600 dark:text-slate-300">
                {total}
              </span>
            </div>
          );
        })}
      </div>

      {/* SUMMARY */}
      <div className="mt-8 grid grid-cols-3 gap-4">
        <SummaryCard label="Total Junior" value={totals.junior} color={COLORS.junior} />
        <SummaryCard label="Total Mid" value={totals.mid} color={COLORS.mid} />
        <SummaryCard label="Total Senior" value={totals.senior} color={COLORS.senior} />
      </div>
    </div>
  );
}

/* ============================= */

function LegendItem({ color, label }: { color: string; label: string }) {
  return (
    <div className="flex items-center gap-2">
      <span
        className="h-3 w-3 rounded-md"
        style={{ backgroundColor: color }}
      />
      <span className="text-slate-600 dark:text-slate-300">
        {label}
      </span>
    </div>
  );
}

function SummaryCard({
  label,
  value,
  color,
}: {
  label: string;
  value: number;
  color: string;
}) {
  return (
    <div className="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-4 text-center">
      <p className="text-3xl font-bold text-slate-900 dark:text-white">
        {value}
      </p>
      <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
        {label}
      </p>
      <div
        className="mx-auto mt-2 h-1 w-12 rounded-full"
        style={{ backgroundColor: color }}
      />
    </div>
  );
}