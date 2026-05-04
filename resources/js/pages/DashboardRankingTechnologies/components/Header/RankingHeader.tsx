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
  onOpenWeekly: () => void;
  weights: WeightConfig;
  onEditWeights: () => void;
}

export function Header({
  meta,
  weights,
  onOpenWeekly,
  onEditWeights,

}: HeaderProps) {
  const { filters, jobMarketStatus, scrapingStatus } = usePage().props as any;

  const [openMarketModal, setOpenMarketModal] = useState(false);
  const [runningIA, setRunningIA] = useState(false);

  const runGapDiscovery = async () => {
    setRunningIA(true);

    try {
      await axios.post("/dashboard/ranking/technologies/discover-gaps", {
        limit: 10,
        sleep: 2,
      });

      Swal.fire({
        icon: "success",
        title: "IA ejecutada",
        text: "Nuevas tecnologías estratégicas detectadas.",
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
  /* =========================================
     Cambio de Periodo / año
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
bg-teal-50
dark:bg-[#0A2540]
px-4 sm:px-6 lg:px-8
"
      >

        {/* BACKGROUND */}

        <div className="absolute inset-0 pointer-events-none">
          <div className="absolute -left-20 -top-20 h-96 w-96 rounded-full bg-teal-300/30 blur-3xl" />
          <div className="absolute right-0 bottom-0 h-80 w-80 rounded-full bg-teal-200/20 blur-3xl" />
        </div>

        {/* CONTENT */}

        <div className="relative mx-auto max-w-7xl py-10 md:py-14">
          <div className="flex flex-col gap-8 md:flex-row md:items-start md:justify-between">

            {/* LEFT */}

            <div className="space-y-6 max-w-3xl">

              {/* TITLE */}

              <div className="flex items-center gap-4">

                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-teal-400 shadow-lg">
                  <BarChart3 className="h-6 w-6 text-white" />
                </div>

                <div>

                  <p className="text-sm font-semibold text-teal-600 dark:text-teal-300">
                    Observatorio Tecnológico ISIL
                  </p>

                  <h1 className="text-3xl font-extrabold tracking-tight text-[#0A2540] dark:text-slate-100">
                    Ranking de Tecnologías
                  </h1>

                </div>
              </div>

              {/* DESCRIPTION */}

              <p className="text-[15px] leading-relaxed text-slate-700 dark:text-slate-300">
                Análisis automatizado de tecnologías más demandadas en el mercado
                laboral, integrando ofertas reales y reportes de tendencias
                tecnológicas globales.
              </p>

              {/* CONTROLES */}

             {/* CONTROLES */}
<div className="mt-4 rounded-2xl border bg-white/80 backdrop-blur p-4 shadow-sm">

  <div className="flex flex-wrap items-center justify-between gap-4">

    {/* LEFT: FILTROS */}
    <div className="flex flex-wrap items-center gap-6">

      {/* AÑO */}
      <div className="flex items-center gap-2">
        <span className="text-sm font-semibold text-teal-600">
          Año
        </span>

        <div className="relative rounded-xl border bg-white shadow-sm">
          <select
            value={meta.year}
            onChange={(e) => onChange({ year: Number(e.target.value) })}
            className="
            w-[100px]
            appearance-none
            bg-transparent
            px-3 py-2
            text-sm
            font-semibold
            text-[#0A2540]
            cursor-pointer
            focus:outline-none
            "
          >
            {[2025, 2026].map((y) => (
              <option key={y} value={y}>{y}</option>
            ))}
          </select>

          <span className="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-teal-400 opacity-70">
            ⌄
          </span>
        </div>
      </div>

      {/* SEMESTRE */}
      <div className="flex items-center gap-2">
        <span className="text-sm font-semibold text-teal-600">
          Semestre
        </span>

        <div className="flex rounded-xl overflow-hidden border bg-white shadow-sm">

          {[
            { value: "s1", label: "Ene – Jun" },
            { value: "s2", label: "Jul – Dic" },
          ].map((s) => (
            <button
              key={s.value}
              onClick={() => onChange({ period: s.value as "s1" | "s2" })}
              className={`px-5 py-2 text-sm font-semibold transition-all ${
                meta.period === s.value
                  ? "bg-teal-400 text-white"
                  : "text-teal-600 hover:bg-teal-50"
              }`}
            >
              {s.label}
            </button>
          ))}

        </div>
      </div>

    </div>

    {/* RIGHT: BOTÓN */}
    <button
      onClick={onOpenWeekly}
      className="
      px-5 py-2.5
      bg-teal-400
      text-white
      rounded-xl
      font-semibold
      shadow-md
      hover:bg-teal-500
      transition
      flex items-center gap-2
      "
    >
      Ver evolución
      <span>›</span>
    </button>

  </div>

  {/* DIVIDER */}
  <div className="my-4 border-t" />

  {/* BADGES */}
  <div className="flex flex-wrap items-center gap-3">

    <Badge className="gap-1.5 bg-white text-[#0A2540] shadow">
      <Database className="h-3 w-3 text-teal-400" />
      {meta.vacantes_analizadas.toLocaleString()} vacantes analizadas
    </Badge>

    <Badge className="gap-1.5 bg-white text-[#0A2540] shadow">
      <Sparkles className="h-3 w-3 text-teal-400" />
      {meta.reportes_analizados.toLocaleString()} reportes analizados
    </Badge>

    <button
      onClick={() => setOpenMarketModal(true)}
      className="
      group
      flex items-center gap-2
      rounded-xl border bg-white
      px-3 py-2
      shadow
      transition
      hover:border-teal-400
      hover:shadow-md
      "
    >
      <Database className="h-4 w-4 text-teal-400" />

      <span className="text-sm font-semibold text-[#0A2540]">
        Datos Generales
      </span>

      <span className="text-xs text-slate-500 group-hover:underline">
        ver detalle
      </span>
    </button>

  </div>

</div>

            </div>

            {/* RIGHT */}

            <div className="flex w-full max-w-sm flex-col">

              {/* METODOLOGÍA */}

              <button
                onClick={onEditWeights}
                className="
group
w-full
rounded-2xl
border border-teal-400/40
bg-white
p-5
text-left
shadow-xl
transition-all
hover:border-teal-400
hover:shadow-2xl
dark:bg-[#102C3C]
"
              >

                <div className="mb-2 flex items-center justify-between">

                  <p className="text-xs font-bold uppercase tracking-wider">
                    Metodología de cálculo
                  </p>

                  <Settings2 className="h-4 w-4 text-teal-400" />

                </div>

                {(() => {

                  const labor = Number(weights?.laborWeight ?? 70);
                  const trends = Number(weights?.trendsWeight ?? (100 - labor));

                  const laborFactor = (labor / 100).toFixed(1);
                  const trendsFactor = (trends / 100).toFixed(1);

                  return (
                    <>

                      <div className="space-y-1 text-sm text-[#0A2540] dark:text-gray-300">

                        <p>
                          <span className="font-bold text-teal-400">{labor}%</span> Demanda laboral
                        </p>

                        <p>
                          <span className="font-bold text-teal-400">{trends}%</span> Tendencias tecnológicas
                        </p>

                      </div>

                      <p className="mt-3 text-xs text-[#0A2540]/70 dark:text-gray-400">
                        Score = ({laborFactor} × Laboral) + ({trendsFactor} × Tendencias)
                      </p>

                      <p className="mt-3 border-t border-teal-400/30 pt-3 text-xs text-[#0A2540]/70 dark:text-gray-400">
                        Los cálculos se realizan únicamente con datos del Periodo seleccionado.
                      </p>

                      <p className="mt-3 text-xs font-semibold text-teal-400 group-hover:underline">
                        Haz clic para editar ponderaciones
                      </p>

                    </>
                  );

                })()}

              </button>

              {/* BOTÓN IA */}

              <button
                onClick={runGapDiscovery}
                disabled={runningIA}
                className="
mt-4
w-full
rounded-2xl
bg-teal-400
px-4
py-3
text-white
font-semibold
shadow-lg
transition
hover:bg-teal-500
disabled:opacity-60
flex
items-center
justify-center
gap-2
"
              >

                <Sparkles size={18} />

                {runningIA
                  ? "Detectando tecnologías..."
                  : "Detectar nuevas tecnologías con IA"}

              </button>

            </div>

          </div>
        </div>

      </header>

      <JobMarketStatusModal
        open={openMarketModal}
        onOpenChange={setOpenMarketModal}
        data={jobMarketStatus}
        scrapingStatus={scrapingStatus}
      />

    </>
  );
}
