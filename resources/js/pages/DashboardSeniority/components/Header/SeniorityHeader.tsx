import { useState } from "react";
import { router, usePage } from "@inertiajs/react";
import axios from "axios";

import { Badge } from "@/components/ui/badge";
import {
  BarChart3,
  Database,
  Briefcase,
  Info,
  RefreshCcw,
} from "lucide-react";

interface HeaderProps {
  meta: {
    year: number;
    period: "s1" | "s2";
    periodo_label: string;
    vacantes_analizadas: number;
  };
}

export function SeniorityHeader({ meta }: HeaderProps) {
  const { filters } = usePage().props as any;
  const [updating, setUpdating] = useState(false);

  /* ===============================
     Cambio de filtros
  =============================== */
  const onChange = (params: { year?: number; period?: "s1" | "s2" }) => {
    router.get(
      "/dashboard/indicators/seniority",
      {
        ...filters,
        year: params.year ?? meta.year,
        period: params.period ?? meta.period,
      },
      {
        preserveState: true,
        replace: true,
      }
    );
  };

  /* ===============================
     Recalcular seniority
  =============================== */
  const updateSeniority = async () => {
    if (updating) return;

    try {
      setUpdating(true);

      await axios.post(
        "/dashboard/indicators/seniority/update-seniority",
        { only_unspecified: true }
      );

      router.reload({ preserveScroll: true });
    } catch (error) {
      console.error("❌ Error recalculando seniority", error);
    } finally {
      setUpdating(false);
    }
  };

  return (
    <header
      className="
        relative
        overflow-hidden
        border-b
        bg-[#E6F7FD]
        dark:bg-[#0A2540]
        px-4 sm:px-6 lg:px-8
      "
    >
      {/* ===== BACKGROUND ===== */}
      <div className="absolute inset-0 pointer-events-none">
        <div className="absolute -left-20 -top-20 h-96 w-96 rounded-full bg-[#00B6E8]/30 blur-3xl" />
        <div className="absolute right-0 bottom-0 h-80 w-80 rounded-full bg-[#1CBCE8]/20 blur-3xl" />
      </div>

      {/* ===== CONTENT ===== */}
      <div className="relative mx-auto max-w-7xl py-10 md:py-14">
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">

          {/* ================= LEFT ================= */}
          <div className="space-y-6 lg:col-span-2 max-w-3xl">
            {/* Title */}
            <div className="flex items-center gap-4">
              <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#00B6E8] shadow-lg">
                <BarChart3 className="h-6 w-6 text-white" />
              </div>

              <div>
                <p className="text-sm font-semibold text-[#005F7A] dark:text-[#7DD3FC]">
                  Observatorio Tecnológico ISIL
                </p>

                <h1 className="text-3xl font-extrabold tracking-tight text-[#0A2540] dark:text-slate-100">
                  Distribución de Seniority
                </h1>
              </div>
            </div>

            {/* Description */}
            <p className="text-[15px] leading-relaxed text-slate-700 dark:text-slate-300">
              Análisis del nivel de experiencia solicitado por el mercado laboral,
              segmentado por carrera y período.
            </p>

            {/* ===== CONTROLES ===== */}
            <div className="flex flex-wrap items-end gap-8">

              {/* Año */}
              <div className="flex flex-col gap-2">
                <span className="text-xs font-semibold text-[#005F7A] dark:text-slate-300">
                  Año de análisis
                </span>

                <div className="relative group rounded-xl border bg-white shadow-sm transition-all hover:border-[#00B6E8] hover:shadow-md">
                  <select
                    value={meta.year}
                    onChange={(e) => onChange({ year: Number(e.target.value) })}
                    className="w-[120px] appearance-none bg-transparent px-4 py-2 text-sm font-semibold text-[#0A2540] cursor-pointer focus:outline-none"
                  >
                    {[2024, 2025, 2026].map((y) => (
                      <option key={y} value={y}>
                        {y}
                      </option>
                    ))}
                  </select>
                  <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[#00B6E8] opacity-70 group-hover:opacity-100 transition">
                    ⌄
                  </span>
                </div>
              </div>

              {/* Semestre */}
              <div className="relative flex flex-col gap-2">
                <span className="text-xs font-semibold text-[#005F7A] dark:text-slate-300">
                  Semestre
                </span>

                <div className="flex rounded-xl border bg-white shadow-sm overflow-hidden dark:bg-[#0F2A3A] transition-all hover:border-[#00B6E8] hover:shadow-md">
                  {[
                    { value: "s1", label: "Ene – Jun" },
                    { value: "s2", label: "Jul – Dic" },
                  ].map((s) => {
                    const active = meta.period === s.value;

                    return (
                      <button
                        key={s.value}
                        onClick={() => onChange({ period: s.value as "s1" | "s2" })}
                        className={`px-6 py-2 text-sm font-semibold transition-all ${
                          active
                            ? "bg-[#00B6E8] text-white shadow-inner"
                            : "text-[#005F7A] hover:bg-[#E6F7FD] dark:text-slate-300 dark:hover:bg-[#123A52]"
                        }`}
                      >
                        {s.label}
                      </button>
                    );
                  })}
                </div>

                <span className="absolute -bottom-5 left-1/2 -translate-x-1/2 text-[11px] text-[#005F7A]/70 dark:text-slate-400 whitespace-nowrap">
                  Haz clic para cambiar el período
                </span>
              </div>

              {/* Badges */}
              <div className="flex flex-wrap items-center gap-3">
                <Badge className="gap-1.5 bg-white text-[#0A2540] shadow hover:shadow-md transition">
                  <Database className="h-3 w-3 text-[#00B6E8]" />
                  {meta.vacantes_analizadas.toLocaleString()} vacantes analizadas
                </Badge>

                <Badge className="gap-1.5 bg-white text-[#0A2540] shadow hover:shadow-md transition">
                  <Briefcase className="h-3 w-3 text-[#00B6E8]" />
                  Distribución por nivel
                </Badge>
              </div>
            </div>

            {/* Active period */}
            <p className="pt-4 text-sm text-[#0A2540]/70 dark:text-gray-400">
              <span className="font-semibold text-[#0A2540] dark:text-white">
                Periodo activo:
              </span>{" "}
              {meta.periodo_label}
            </p>
          </div>

          {/* ================= RIGHT – METODOLOGÍA ================= */}
          <div className="border rounded-xl p-5 bg-white/80 dark:bg-[#0F2A3A] dark:border-[#1E3A4A] backdrop-blur">
            <div className="flex items-center gap-2 mb-3">
              <Info className="h-4 w-4 text-[#00B6E8]" />
              <p className="font-semibold text-slate-900 dark:text-slate-100">
                Metodología de cálculo
              </p>
            </div>

            <ul className="space-y-2 text-sm text-slate-700 dark:text-slate-300">
              <li>• Detección del seniority solicitado en cada vacante.</li>
              <li>• Normalización de experiencia (junior / mid / senior).</li>
              <li>• Asociación a carreras mediante competencias.</li>
              <li>• Cálculo porcentual por carrera.</li>
            </ul>

            <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">
              % nivel = (vacantes del nivel ÷ total vacantes de la carrera) × 100
            </p>

            <p className="mt-2 text-xs font-semibold text-[#005F7A] dark:text-[#7DD3FC]">
              Peso del indicador: 100%
            </p>

            {/* ===== ACTION ===== */}
            <button
              onClick={updateSeniority}
              disabled={updating}
              className={`
                mt-4
                w-full
                flex
                items-center
                justify-center
                gap-2
                rounded-lg
                border
                px-4
                py-2
                text-sm
                font-semibold
                transition
                ${
                  updating
                    ? "bg-slate-200 text-slate-500 cursor-not-allowed dark:bg-slate-700"
                    : "bg-[#E6F7FD] text-[#005F7A] hover:bg-[#DFF3FB] dark:bg-[#123A52] dark:hover:bg-[#1B4A63]"
                }
              `}
            >
              <RefreshCcw className={`h-4 w-4 ${updating ? "animate-spin" : ""}`} />
              {updating ? "Actualizando seniority…" : "Recalcular seniority"}
            </button>

            <p className="mt-2 text-[11px] text-slate-500 dark:text-slate-400 text-center">
              Aplica solo a vacantes sin seniority definido
            </p>
          </div>
        </div>
      </div>
    </header>
  );
}
