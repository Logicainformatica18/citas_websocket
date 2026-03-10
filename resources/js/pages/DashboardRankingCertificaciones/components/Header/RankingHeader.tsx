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
import axios from "axios";
import Swal from "sweetalert2";
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

  /* =====================================================
     👇 AQUÍ ESTABA EL BUG
     Ahora traemos scrapingStatus también
  ===================================================== */
  const { filters, jobMarketStatus, scrapingStatus } = usePage().props as any;

  const [openMarketModal, setOpenMarketModal] = useState(false);
const [runningIA, setRunningIA] = useState(false);

const runGapDiscovery = async () => {
  setRunningIA(true);

  try {
    await axios.post("/dashboard/ranking-certificaciones/discover-gaps", {
      limit: 10,
      sleep: 2,
    });

    Swal.fire({
      icon: "success",
      title: "IA ejecutada",
      text: "Nuevas certificaciones estratégicas detectadas.",
    });

    router.reload();

  } catch (error: any) {
    Swal.fire({
      icon: "error",
      title: "Error",
      text:
        error.response?.data?.message ??
        "No se pudo ejecutar el descubrimiento.",
    });
  }

  setRunningIA(false);
};
  // 🔎 Log defensivo (puedes borrarlo luego)
  console.log("[HEADER CERTIFICATIONS] scrapingStatus:", scrapingStatus);

  const onChange = (params: { year?: number; period?: "s1" | "s2" }) => {
    router.get(
      "/dashboard/ranking-certificaciones",
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
          bg-teal-50
          dark:bg-[#0A2540]
          px-4 sm:px-6 lg:px-8
        "
    >
      {/* ===== BACKGROUND ===== */}
      <div className="absolute inset-0 pointer-events-none">
        <div className="absolute -left-20 -top-20 h-96 w-96 rounded-full bg-teal-400/30 blur-3xl" />
        <div className="absolute right-0 bottom-0 h-80 w-80 rounded-full bg-teal-300/20 blur-3xl" />
      </div>

      {/* ===== CONTENT ===== */}
      <div className="relative z-10 mx-auto max-w-7xl py-10 md:py-14">
        <div className="flex flex-col gap-8 md:flex-row md:items-start md:justify-between">

          {/* ================= LEFT ================= */}
          <div className="space-y-6 max-w-3xl">

            {/* Title */}
            <div className="flex items-center gap-4">
              <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-teal-500 shadow-lg">
                <BarChart3 className="h-6 w-6 text-white" />
              </div>

              <div>
                <p className="text-sm font-semibold text-teal-700 dark:text-teal-300">
                  Observatorio Tecnológico ISIL
                </p>
                <h1 className="text-3xl font-extrabold tracking-tight text-[#0A2540] dark:text-slate-100">
                  Ranking de Certificaciones
                </h1>
              </div>
            </div>

            {/* Description */}
            <p className="text-[15px] leading-relaxed text-slate-700 dark:text-slate-300">
              Análisis automatizado de certificaciones tecnológicas más demandadas,
              basado en ofertas laborales reales y datos verificados.
            </p>

            {/* ===== CONTROLES ===== */}
            <div className="flex flex-wrap items-end gap-8">

              {/* Año */}
              <div className="flex flex-col gap-2">
                <span className="text-xs font-semibold text-teal-700 dark:text-slate-300">
                  Año de análisis
                </span>

                <div className="relative group rounded-xl border bg-white shadow-sm hover:border-teal-500 hover:shadow-md">
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
                    {[2025, 2026].map((y) => (
                      <option key={y} value={y}>
                        {y}
                      </option>
                    ))}
                  </select>
                  <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-teal-500">
                    ⌄
                  </span>
                </div>
              </div>

              {/* Semestre */}
              <div className="flex flex-col gap-2">
                <span className="text-xs font-semibold text-teal-700 dark:text-slate-300">
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
                        className={`
                            px-6 py-2 text-sm font-semibold transition-all
                            ${
                              active
                                ? "bg-teal-500 text-white"
                                : "text-teal-700 hover:bg-teal-100"
                            }
                          `}
                      >
                        {s.label}
                      </button>
                    );
                  })}
                </div>
              </div>

              {/* Badges */}
              <div className="flex flex-wrap items-center gap-3">
                <Badge className="gap-1.5 bg-white text-[#0A2540] shadow">
                  <Database className="h-3 w-3 text-teal-500" />
                  {meta.vacantes_analizadas.toLocaleString()} vacantes
                </Badge>

                <Badge className="gap-1.5 bg-white text-[#0A2540] shadow">
                  <Sparkles className="h-3 w-3 text-teal-500" />
                  {meta.reportes_analizados.toLocaleString()} reportes
                </Badge>

                {/* 🔥 Datos Generales */}
                <button
                  onClick={() => setOpenMarketModal(true)}
                  className="
                      flex items-center gap-2 rounded-xl border bg-white
                      px-3 py-2 shadow-md hover:border-teal-500
                    "
                >
                  <Database className="h-4 w-4 text-teal-500" />
                  <span className="text-sm font-semibold text-[#0A2540]">
                    Datos Generales
                  </span>
                </button>
              </div>
            </div>


          </div>

          {/* ================= RIGHT ================= */}
          <div className="flex w-full max-w-sm flex-col">

            {/* ===== BOTÓN METODOLOGÍA ===== */}
            <button
              onClick={onEditWeights}
              className="
      group
      w-full
      rounded-2xl
      border border-teal-500/40
      bg-white
      p-6
      text-left
      shadow-xl
      transition-all
      hover:border-teal-500
      hover:shadow-2xl
      dark:bg-[#102C3C]
    "
            >
              <div className="mb-3 flex items-center justify-between">
                <p className="text-xs font-bold uppercase tracking-wider text-teal-500">
                  Metodología de cálculo
                </p>
                <Settings2 className="h-4 w-4 text-teal-500 opacity-60 group-hover:opacity-100" />
              </div>

              <div className="space-y-1 text-sm text-[#0A2540] dark:text-gray-300">
                <p>
                  <span className="font-bold text-teal-500">
                    {weights.laborWeight}%
                  </span>{" "}
                  Demanda laboral
                </p>
                <p>
                  <span className="font-bold text-teal-500">
                    {weights.trendsWeight}%
                  </span>{" "}
                  Tendencias tecnológicas
                </p>
              </div>

              <p className="mt-3 text-xs text-[#0A2540]/70 dark:text-gray-400">
                Score = ({weights.laborWeight / 100} × Laboral) + (
                {weights.trendsWeight / 100} × Tendencias)
              </p>

              <p className="mt-3 border-t border-teal-500/30 pt-3 text-xs text-[#0A2540]/70 dark:text-gray-400">
                Los cálculos se realizan únicamente con datos del Periodo seleccionado.
              </p>

              <p className="mt-3 text-xs font-semibold text-teal-500 group-hover:underline">
                Haz clic para editar ponderaciones
              </p>
            </button>

            {/* ===== BOTÓN IA CERTIFICACIONES ===== */}
            <button
              onClick={runGapDiscovery}
              disabled={runningIA}
              className="
      mt-4
      w-full
      rounded-2xl
      bg-teal-500
      px-4
      py-3
      text-white
      font-semibold
      shadow-lg
      transition
      hover:bg-teal-600
      disabled:opacity-60
      flex
      items-center
      justify-center
      gap-2
    "
            >
              <Sparkles size={18} />
              {runningIA
                ? "Detectando certificaciones..."
                : "Detectar nuevas certificaciones con IA"}
            </button>
          </div>
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
