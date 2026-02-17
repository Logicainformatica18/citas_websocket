import { BarChart3, Briefcase, Sparkles } from "lucide-react";

/* ======================================================
   TYPES
====================================================== */
interface MarketData {
  percentage: number;
  matched: number;
}

interface ResultItem {
  career_id: number;
  career_name: string;
  market: MarketData;
  prospective: MarketData;
  final_index: number;
}

interface Props {
  /** Modo carrera única (opcional) */
  market?: MarketData;
  prospective?: MarketData;
  finalIndex?: number;

  /** Modo global */
  results?: ResultItem[];
}

/* ======================================================
   COMPONENT
====================================================== */
export default function PeAlignmentKpis({
  market,
  prospective,
  finalIndex,
  results = [],
}: Props) {

  /* ==================================================
     1️⃣ Normalizar datos (GLOBAL vs DETALLE)
  ================================================== */
  let marketPct = 0;
  let marketMatched = 0;
  let prospectivePct = 0;
  let prospectiveMatched = 0;
  let finalScore = 0;

  if (results.length > 0) {
    // 🔹 MODO GLOBAL → promedio
    const total = results.length;

    marketPct = Math.round(
      results.reduce((a, r) => a + r.market.percentage, 0) / total
    );

    marketMatched = Math.round(
      results.reduce((a, r) => a + r.market.matched, 0) / total
    );

    prospectivePct = Math.round(
      results.reduce((a, r) => a + r.prospective.percentage, 0) / total
    );

    prospectiveMatched = Math.round(
      results.reduce((a, r) => a + r.prospective.matched, 0) / total
    );

    finalScore = Math.round(
      results.reduce((a, r) => a + r.final_index, 0) / total
    );

  } else if (market && prospective) {
    // 🔹 MODO CARRERA ÚNICA
    marketPct = market.percentage ?? 0;
    marketMatched = market.matched ?? 0;
    prospectivePct = prospective.percentage ?? 0;
    prospectiveMatched = prospective.matched ?? 0;
    finalScore = finalIndex ?? 0;
  }

  /* ==================================================
     2️⃣ KPIs
  ================================================== */
  const items = [
    {
      label: "Coincidencia en portales de empleo",
      value: `${marketPct}%`,
      subtitle: `${marketMatched} competencias`,
      icon: Briefcase,
      color: "text-[#00B6E8]",
      bg: "bg-[#E6F7FD] dark:bg-[#0F2A3A]",
    },
    {
      label: "Coincidencia en reportes",
      value: `${prospectivePct}%`,
      subtitle: `${prospectiveMatched} competencias`,
      icon: Sparkles,
      color: "text-purple-600",
      bg: "bg-purple-50 dark:bg-[#1A1F2C]",
    },
    {
      label: "Porcentaje de Alineación",
      value: `${finalScore}%`,
      subtitle: results.length > 1
        ? "Promedio entre carreras"
        : "Score ponderado",
      icon: BarChart3,
      color: "text-emerald-600",
      bg: "bg-emerald-50 dark:bg-[#0F2F28]",
    },
  ];

  /* ==================================================
     3️⃣ RENDER
  ================================================== */
  return (
    <div className="grid gap-4 md:grid-cols-3">
      {items.map((kpi) => (
        <div
          key={kpi.label}
          className={`rounded-xl border p-4 ${kpi.bg}`}
        >
          <div className="flex items-center gap-3">
            <kpi.icon className={`h-6 w-6 ${kpi.color}`} />
            <div>
              <p className="text-sm font-semibold">{kpi.label}</p>
              <p className="text-2xl font-bold">{kpi.value}</p>
              <p className="text-xs text-slate-500">{kpi.subtitle}</p>
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}
