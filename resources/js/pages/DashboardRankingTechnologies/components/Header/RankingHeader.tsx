import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import {
  BarChart3,
  Database,
  Sparkles,
  Settings2,
} from "lucide-react";
import { router, usePage } from "@inertiajs/react";
import { WeightConfig } from "./WeightConfigModal";
import { JobMarketStatusModal } from "./JobMarketStatusModal";

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

export function Header({
  meta,
  weights,
  onEditWeights,
}: HeaderProps) {
  const { filters, jobMarketStatus, scrapingStatus } = usePage().props as any;

  const [openMarketModal, setOpenMarketModal] = useState(false);

  /* =========================================
     Cambio de período / año
  ========================================= */
  const onChange = (params: { year?: number; period?: "s1" | "s2" }) => {
    router.get(
      "/dashboard/ranking/technologies",
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
    <>
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
          <div className="flex flex-col gap-8 md:flex-row md:items-start md:justify-between">

            {/* ================= LEFT ================= */}
            <div className="space-y-6 max-w-3xl">
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
                    Ranking de Tecnologías
                  </h1>
                </div>
              </div>

              {/* Description */}
              <p className="text-[15px] leading-relaxed text-slate-700 dark:text-slate-300">
                Análisis automatizado de tecnologías más demandadas en el mercado
                laboral, integrando ofertas reales y reportes de tendencias
                tecnológicas globales.
              </p>

              {/* ===== CONTROLES DE PERÍODO ===== */}
              <div className="flex flex-wrap items-end gap-8">

                {/* ===== AÑO ===== */}
                <div className="flex flex-col gap-2">
                  <span className="text-xs font-semibold text-[#005F7A] dark:text-slate-300">
                    Año de análisis
                  </span>

                  <div className="relative rounded-xl border bg-white shadow-sm">
                    <select
                      value={meta.year}
                      onChange={(e) =>
                        onChange({ year: Number(e.target.value) })
                      }
                      className="
                        w-[120px]
                        appearance-none
                        bg-transparent
                        px-4
                        py-2
                        text-sm
                        font-semibold
                        text-[#0A2540]
                        cursor-pointer
                        focus:outline-none
                      "
                    >
                      {[2024, 2025, 2026].map((y) => (
                        <option key={y} value={y}>
                          {y}
                        </option>
                      ))}
                    </select>
                    <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[#00B6E8] opacity-70">
                      ⌄
                    </span>
                  </div>
                </div>

                {/* ===== SEMESTRE ===== */}
                <div className="flex flex-col gap-2">
                  <span className="text-xs font-semibold text-[#005F7A] dark:text-slate-300">
                    Semestre
                  </span>

                  <div className="flex rounded-xl overflow-hidden border bg-white shadow-sm">
                    {[
                      { value: "s1", label: "Ene – Jun" },
                      { value: "s2", label: "Jul – Dic" },
                    ].map((s) => (
                      <button
                        key={s.value}
                        onClick={() =>
                          onChange({ period: s.value as "s1" | "s2" })
                        }
                        className={`px-6 py-2 text-sm font-semibold transition-all ${
                          meta.period === s.value
                            ? "bg-[#00B6E8] text-white shadow-inner"
                            : "text-[#005F7A] hover:bg-[#E6F7FD]"
                        }`}
                      >
                        {s.label}
                      </button>
                    ))}
                  </div>
                </div>

                {/* ===== BADGES ===== */}
                <div className="flex flex-wrap items-center gap-3">
                  <Badge className="gap-1.5 bg-white text-[#0A2540] shadow">
                    <Database className="h-3 w-3 text-[#00B6E8]" />
                    {meta.vacantes_analizadas.toLocaleString()} vacantes analizadas
                  </Badge>

                  <Badge className="gap-1.5 bg-white text-[#0A2540] shadow">
                    <Sparkles className="h-3 w-3 text-[#00B6E8]" />
                    {meta.reportes_analizados.toLocaleString()} reportes analizados
                  </Badge>

                  {/* 🔥 ESTADO DEL MERCADO */}
                  <button
                    onClick={() => setOpenMarketModal(true)}
                    className="
                      group
                      flex
                      items-center
                      gap-2
                      rounded-xl
                      border
                      bg-white
                      px-3
                      py-2
                      shadow
                      transition
                      hover:border-[#00B6E8]
                      hover:shadow-md
                    "
                  >
                    <Database className="h-4 w-4 text-[#00B6E8]" />
                    <span className="text-sm font-semibold text-[#0A2540]">
                      Estado del mercado
                    </span>
                    <span className="text-xs text-slate-500 group-hover:underline">
                      ver detalle
                    </span>
                  </button>
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

            {/* ================= RIGHT ================= */}
            <button
              onClick={onEditWeights}
              className="
                group
                w-full
                max-w-sm
                rounded-2xl
                border border-[#00B6E8]/40
                bg-white
                p-5
                text-left
                shadow-xl
                transition-all
                hover:border-[#00B6E8]
                hover:shadow-2xl
                dark:bg-[#102C3C]
              "
            >
              <div className="mb-2 flex items-center justify-between">
                <p className="text-xs font-bold uppercase tracking-wider text-[#00B6E8]">
                  Metodología de cálculo
                </p>
                <Settings2 className="h-4 w-4 text-[#00B6E8]" />
              </div>

              <div className="space-y-1 text-sm text-[#0A2540] dark:text-gray-300">
                <p>
                  <span className="font-bold text-[#00B6E8]">
                    {weights.laborWeight}%
                  </span>{" "}
                  Demanda laboral
                </p>
                <p>
                  <span className="font-bold text-[#00B6E8]">
                    {weights.trendsWeight}%
                  </span>{" "}
                  Tendencias tecnológicas
                </p>
              </div>

              <p className="mt-3 text-xs text-[#0A2540]/70 dark:text-gray-400">
                Score = ({weights.laborWeight / 100} × Laboral) + (
                {weights.trendsWeight / 100} × Tendencias)
              </p>

              <p className="mt-3 border-t border-[#00B6E8]/30 pt-3 text-xs text-[#0A2540]/70 dark:text-gray-400">
                Los cálculos se realizan únicamente con datos del período seleccionado.
              </p>

              <p className="mt-3 text-xs font-semibold text-[#00B6E8] group-hover:underline">
                Haz clic para editar ponderaciones
              </p>
            </button>
          </div>
        </div>
      </header>

      {/* ===== MODAL ===== */}
      <JobMarketStatusModal
        open={openMarketModal}
        onOpenChange={setOpenMarketModal}
        data={jobMarketStatus}
        scrapingStatus={scrapingStatus}
      />
    </>
  );
}
