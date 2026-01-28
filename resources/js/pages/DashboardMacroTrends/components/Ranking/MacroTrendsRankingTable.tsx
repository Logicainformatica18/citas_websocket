import ScoreBar from "./ScoreBar";
import { Trophy } from "lucide-react";

interface Item {
  term: string;
  labor_score: number | string;
  trend_score: number | string;
  final_score: number | string;
}

export default function MacroTrendsRankingTable({
  data,
}: {
  data: Item[];
}) {
  if (!data || data.length === 0) {
    return (
      <div className="bg-white dark:bg-[#0F2A3A] border rounded-xl p-6 text-center">
        <p className="font-semibold text-slate-700 dark:text-slate-200">
          No se detectaron macro-tendencias en este período
        </p>
      </div>
    );
  }

  const rankIcon = (i: number) => {
    if (i === 0) return <Trophy size={14} className="text-yellow-500" />;
    if (i === 1) return <Trophy size={14} className="text-slate-500" />;
    if (i === 2) return <Trophy size={14} className="text-amber-700" />;
    return <span className="text-xs font-semibold">{i + 1}</span>;
  };

  return (
    <div className="bg-white dark:bg-[#0F2A3A] border rounded-xl p-6">
      <h3 className="font-semibold mb-6 text-slate-900 dark:text-slate-100">
        Top Macro-Tendencias
      </h3>

      <table className="w-full text-sm table-fixed">
        <thead>
          <tr className="text-left text-xs uppercase tracking-wide text-slate-500">
            <th className="w-14 text-center">#</th>
            <th className="w-[260px]">Tendencia</th>
            <th className="w-[320px]">Score final</th>
            <th className="w-[240px]">Demanda laboral</th>
            <th className="w-[220px]">Reportes</th>
          </tr>
        </thead>

        <tbody>
          {data.map((row, i) => {
            const labor = Number(row.labor_score);
            const trend = Number(row.trend_score);
            const final = Number(row.final_score);

            return (
              <tr
                key={row.term}
                className="border-t border-slate-200 dark:border-slate-700"
              >
                {/* RANK */}
                <td className="px-3 py-4 text-center">
                  <span className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 dark:bg-[#123A52]">
                    {rankIcon(i)}
                  </span>
                </td>

                {/* TENDENCIA */}
                <td className="px-3 py-4 font-semibold text-slate-900 dark:text-white">
                  {row.term}
                </td>

                {/* SCORE FINAL */}
                <td className="px-3 py-4">
                  <div className="flex items-center gap-4">
                    <div className="flex-1">
                      <ScoreBar value={final} />
                    </div>
                    <span className="text-sm font-bold text-[#00B6E8]">
                      {final.toFixed(1)}%
                    </span>
                  </div>
                </td>

                {/* DEMANDA */}
                <td className="px-3 py-4">
                  <div className="flex items-center gap-4">
                    <div className="w-full bg-slate-200 dark:bg-slate-700 rounded h-2 overflow-hidden">
                      <div
                        className="h-2 bg-[#00B6E8]"
                        style={{ width: `${labor}%` }}
                      />
                    </div>
                    <span className="text-xs font-medium">
                      {labor.toFixed(1)}%
                    </span>
                  </div>
                </td>

                {/* REPORTES */}
                <td className="px-3 py-4">
                  <div className="flex items-center gap-4">
                    <div className="w-full bg-slate-200 dark:bg-slate-700 rounded h-2 overflow-hidden">
                      <div
                        className="h-2 bg-indigo-500"
                        style={{ width: `${trend}%` }}
                      />
                    </div>
                    <span className="text-xs font-medium">
                      {trend.toFixed(1)}%
                    </span>
                  </div>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
