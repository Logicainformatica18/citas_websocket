import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import {
  Globe2,
  Sparkles,
  Settings2,
  TrendingUp,
} from "lucide-react";
import { router, usePage } from "@inertiajs/react";
import { WeightConfig } from "./WeightConfigModal";

interface HeaderProps {
  meta: {
    year: number;
    period: "s1" | "s2";
    periodo_label: string;
    vacantes_analizadas: number;
    reportes_analizados: number;
  };
  weights: WeightConfig;
  onEditWeights: () => void;
}

export function MacroTrendsHeader({
  meta,
  weights,
  onEditWeights,
}: HeaderProps) {
  const { filters } = usePage().props as any;

  const onChange = (params: { year?: number; period?: "s1" | "s2" }) => {
    router.get(
      "/dashboard/macro-trends",
      {
        ...filters,
        year: params.year ?? meta.year,
        period: params.period ?? meta.period,
        page: 1,
      },
      {
        preserveState: true,
        replace: true,
      }
    );
  };

  return (
    <header className="relative overflow-hidden border-b bg-[#E6F7FD] dark:bg-[#0A2540] px-6">
      {/* Background decor */}
      <div className="absolute inset-0 pointer-events-none">
        <div className="absolute -left-20 -top-20 h-96 w-96 rounded-full bg-[#00B6E8]/30 blur-3xl" />
        <div className="absolute right-0 bottom-0 h-80 w-80 rounded-full bg-[#1CBCE8]/20 blur-3xl" />
      </div>

      <div className="relative z-10 mx-auto max-w-7xl py-12">
        <div className="flex flex-col gap-8 md:flex-row md:justify-between">

          {/* ================= LEFT ================= */}
          <div className="space-y-6 max-w-3xl">

            {/* Title */}
            <div className="flex items-center gap-4">
              <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#00B6E8] shadow-lg">
                <Globe2 className="h-6 w-6 text-white" />
              </div>

              <div>
                <p className="text-sm font-semibold text-[#005F7A] dark:text-[#7DD3FC]">
                  Observatorio Tecnológico ISIL
                </p>
                <h1 className="text-3xl font-extrabold tracking-tight text-[#0A2540] dark:text-slate-100">
                  Ranking de Macro Tendencias
                </h1>
              </div>
            </div>

            {/* Description */}
            <p className="text-[15px] text-slate-700 dark:text-slate-300">
              Análisis estratégico de tendencias globales que impactan el mercado
              laboral tecnológico, integrando reportes internacionales y demanda real.
            </p>

            {/* ===== CONTROLES ===== */}
            <div className="flex flex-wrap items-end gap-8">

              {/* Año */}
              <div className="flex flex-col gap-2">
                <span className="text-xs font-semibold text-[#005F7A] dark:text-slate-300">
                  Año de análisis
                </span>

                <select
                  value={meta.year}
                  onChange={(e) =>
                    onChange({ year: Number(e.target.value) })
                  }
                  className="rounded-xl border bg-white px-4 py-2 text-sm font-semibold shadow-sm"
                >
                  {[2025, 2026].map((y) => (
                    <option key={y} value={y}>
                      {y}
                    </option>
                  ))}
                </select>
              </div>

              {/* Semestre */}
              <div className="flex flex-col gap-2">
                <span className="text-xs font-semibold text-[#005F7A] dark:text-slate-300">
                  Semestre
                </span>

                <div className="flex rounded-xl border bg-white overflow-hidden">
                  {[
                    { value: "s1", label: "Ene – Jun" },
                    { value: "s2", label: "Jul – Dic" },
                  ].map((s) => {
                    const active = meta.period === s.value;
                    return (
                      <button
                        key={s.value}
                        onClick={() =>
                          onChange({ period: s.value as "s1" | "s2" })
                        }
                        className={`px-6 py-2 text-sm font-semibold transition-all ${
                          active
                            ? "bg-[#00B6E8] text-white"
                            : "text-[#005F7A] hover:bg-[#E6F7FD]"
                        }`}
                      >
                        {s.label}
                      </button>
                    );
                  })}
                </div>
              </div>

              {/* Badges */}
              <div className="flex items-center gap-3">
                <Badge className="gap-1.5 bg-white text-[#0A2540] shadow">
                  <TrendingUp className="h-3 w-3 text-[#00B6E8]" />
                  {meta.vacantes_analizadas.toLocaleString()} vacantes
                </Badge>

                <Badge className="gap-1.5 bg-white text-[#0A2540] shadow">
                  <Sparkles className="h-3 w-3 text-[#00B6E8]" />
                  {meta.reportes_analizados.toLocaleString()} reportes
                </Badge>
              </div>
            </div>

            <p className="pt-4 text-sm text-[#0A2540]/70">
              <b>Periodo activo:</b> {meta.periodo_label}
            </p>
          </div>

          {/* ================= RIGHT ================= */}
          <button
            onClick={onEditWeights}
            className="group w-full max-w-sm rounded-2xl border border-[#00B6E8]/40 bg-white p-6 text-left shadow-xl transition-all hover:shadow-2xl dark:bg-[#102C3C]"
          >
            <div className="mb-3 flex items-center justify-between">
              <p className="text-xs font-bold uppercase tracking-wider text-[#00B6E8]">
                Metodología de cálculo
              </p>
              <Settings2 className="h-4 w-4 text-[#00B6E8]" />
            </div>

           <div className="space-y-2 text-sm text-[#0A2540] dark:text-gray-300">
  <p>
    <span className="font-bold text-[#00B6E8] text-lg">
      {weights.laborWeight}%
    </span>{" "}
    Impacto laboral
  </p>

  <p>
    <span className="font-bold text-emerald-500 text-lg">
      {weights.trendsWeight}%
    </span>{" "}
    Relevancia estratégica
  </p>
</div>


           <p className="mt-3 text-xs text-[#0A2540]/70">
  Score = (
  {(weights?.laborWeight ?? 0) / 100} × Laboral
  ) + (
  {(weights?.trendsWeight ?? 0) / 100} × Tendencias
  )
</p>

          </button>
        </div>
      </div>
    </header>
  );
}
