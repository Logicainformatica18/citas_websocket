import {
  Briefcase,
  Globe,
} from "lucide-react";

/* ======================================================
   TYPES
====================================================== */
interface Summary {
  total_competencies: number;
  market_rate: number;   // Intensidad promedio mercado
  trend_rate: number;    // Intensidad promedio tendencia
  final_index: number;   // Intensidad ponderada total
}

interface Props {
  summary?: Summary | null;
}

/* ======================================================
   COMPONENT
====================================================== */
export default function PeAlignmentKpis({ summary }: Props) {
  if (!summary) return null;

  const {
    total_competencies,
    market_rate,
    trend_rate,
    final_index,
  } = summary;

  /* =========================
     Clasificación estratégica
  ========================== */
  const level =
    final_index >= 80
      ? { label: "Alta intensidad de alineación", color: "text-emerald-600" }
      : final_index >= 60
      ? { label: "Intensidad sólida", color: "text-[#00B6E8]" }
      : final_index >= 40
      ? { label: "Intensidad moderada", color: "text-amber-600" }
      : { label: "Baja intensidad de alineación", color: "text-red-600" };

  /* =========================
     Gauge (SVG semicircular)
  ========================== */
  const radius = 90;
  const stroke = 14;
  const normalized = Math.min(Math.max(final_index, 0), 100);
  const circumference = Math.PI * radius;
  const dash = (normalized / 100) * circumference;

  return (
    <div className="grid md:grid-cols-3 gap-6">

      {/* =========================
          GAUGE PRINCIPAL
      ========================== */}
      <div className="bg-white dark:bg-slate-900 rounded-2xl shadow border p-6 flex flex-col items-center justify-center relative">
        <svg width="220" height="140">
          <path
            d={`M 10 120 A ${radius} ${radius} 0 0 1 210 120`}
            fill="transparent"
            stroke="#E5E7EB"
            strokeWidth={stroke}
            strokeLinecap="round"
          />
          <path
            d={`M 10 120 A ${radius} ${radius} 0 0 1 210 120`}
            fill="transparent"
            stroke="#00B6E8"
            strokeWidth={stroke}
            strokeDasharray={`${dash} ${circumference}`}
            strokeLinecap="round"
          />
        </svg>

        <div className="absolute text-center mt-6">
          <div className="text-4xl font-bold text-slate-800 dark:text-white">
            {final_index}%
          </div>
          <div className="text-sm text-slate-500">
            Intensidad de alineación
          </div>
          <div className={`mt-2 font-semibold ${level.color}`}>
            {level.label}
          </div>
        </div>
      </div>

      {/* =========================
          KPIS LATERALES
      ========================== */}
      <div className="col-span-2 space-y-4">

        {/* DEMANDA LABORAL */}
        <div className="bg-white dark:bg-slate-900 rounded-2xl shadow border p-5 flex items-center gap-4 relative">
          <div className="absolute left-0 top-0 bottom-0 w-1 bg-green-500 rounded-l-2xl" />

          <div className="bg-slate-100 dark:bg-slate-800 p-3 rounded-xl">
            <Briefcase className="text-slate-600 dark:text-slate-300" />
          </div>

          <div>
            <div className="text-sm text-slate-500">
              Intensidad promedio — Demanda Laboral
            </div>
            <div className="text-2xl font-bold">
              {market_rate}%
            </div>
            <div className="text-xs text-slate-500">
              Promedio sobre {total_competencies} competencias
            </div>
          </div>
        </div>

        {/* TENDENCIAS */}
        <div className="bg-white dark:bg-slate-900 rounded-2xl shadow border p-5 flex items-center gap-4 relative">
          <div className="absolute left-0 top-0 bottom-0 w-1 bg-[#00B6E8] rounded-l-2xl" />

          <div className="bg-slate-100 dark:bg-slate-800 p-3 rounded-xl">
            <Globe className="text-slate-600 dark:text-slate-300" />
          </div>

          <div>
            <div className="text-sm text-slate-500">
              Intensidad promedio — Tendencias
            </div>
            <div className="text-2xl font-bold">
              {trend_rate}%
            </div>
            <div className="text-xs text-slate-500">
              Promedio sobre {total_competencies} competencias
            </div>
          </div>
        </div>

      </div>
    </div>
  );
}
