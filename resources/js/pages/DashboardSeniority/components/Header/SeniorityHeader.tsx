import { useState } from "react";
import { router, usePage } from "@inertiajs/react";
import axios from "axios";

import { Badge } from "@/components/ui/badge";
import { JobMarketStatusModal } from "./JobMarketStatusModal";

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
   onOpenEvolution: (type: "general" | "career") => void;
}

export function SeniorityHeader({ meta, onOpenEvolution }: HeaderProps){
    const { filters, jobMarketStatus, jobMarketData } = usePage().props as any;

    const [updating, setUpdating] = useState(false);
    const hasCareerFilter = filters?.career && filters.career.length > 0;
    const [openMarketModal, setOpenMarketModal] = useState(false);
const [openEvolutionMenu, setOpenEvolutionMenu] = useState(false);
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
        bg-teal-50
        dark:bg-[#0A2540]
        px-4 sm:px-6 lg:px-8
      "
    >
        <JobMarketStatusModal
            open={openMarketModal}
            onOpenChange={setOpenMarketModal}
           data={jobMarketData} // ✅ CORRECTO
        />

        {/* ===== BACKGROUND ===== */}
        <div className="absolute inset-0 pointer-events-none">
            <div className="absolute -left-20 -top-20 h-96 w-96 rounded-full bg-teal-300/30 blur-3xl" />
            <div className="absolute right-0 bottom-0 h-80 w-80 rounded-full bg-teal-200/20 blur-3xl" />
        </div>

        {/* ===== CONTENT ===== */}
        <div className="relative mx-auto max-w-7xl py-10 md:py-14">
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">

                {/* ================= LEFT ================= */}
                <div className="space-y-6 lg:col-span-2 max-w-3xl">

                    {/* Title */}
                    <div className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-teal-400 shadow-lg">
                            <BarChart3 className="h-6 w-6 text-white" />
                        </div>

                        <div>
                            <p className="text-sm font-semibold text-teal-600 dark:text-teal-300">
                                Observatorio Tecnológico ISIL
                            </p>

                            <h1 className="text-3xl font-extrabold tracking-tight text-[#0A2540] dark:text-slate-100">
                                Distribución de Nivel Profesional
                            </h1>
                        </div>
                    </div>

                    {/* Description */}
                    <p className="text-[15px] leading-relaxed text-slate-700 dark:text-slate-300">
                        {hasCareerFilter
                            ? "Análisis del nivel de experiencia solicitado por el mercado laboral, alineado a la carrera seleccionada."
                            : "Análisis del nivel de experiencia solicitado por el mercado laboral, a nivel general."}
                    </p>

                    {/* ===== CONTROLES ===== */}
                   <div className="mt-4 rounded-2xl border bg-white/80 backdrop-blur p-4 shadow-sm">

  {/* 🔥 TOP: FILTROS + BOTÓN */}
<div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">

    {/* LEFT: FILTROS */}
    <div className="flex flex-wrap items-center gap-6">

      {/* AÑO */}
  <div className="flex items-center gap-2 flex-wrap justify-end">
        <span className="text-sm font-semibold text-teal-600">
          Año
        </span>

        <div className="relative rounded-xl border bg-white shadow-sm">
          <select
            value={meta.year}
            onChange={(e) => onChange({ year: Number(e.target.value) })}
            className="w-[100px] appearance-none bg-transparent px-3 py-2 text-sm font-semibold text-[#0A2540] cursor-pointer focus:outline-none"
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

    {/* RIGHT: BOTÓN EVOLUCIÓN */}
   {/* RIGHT: ACCIONES */}
<div className="flex items-center gap-2">

  {/* VER EVOLUCIÓN */}
 <div className="relative">

  {/* BOTÓN PRINCIPAL */}
  <button
    onClick={() => setOpenEvolutionMenu((prev) => !prev)}
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
    <span className="text-xs">▼</span>
  </button>

  {/* DROPDOWN */}
  {openEvolutionMenu && (
    <div className="
      absolute right-0 mt-2 w-56
      bg-white border rounded-xl shadow-xl
      z-50 overflow-hidden
    ">

      {/* GENERAL */}
      <button
        onClick={() => {
          setOpenEvolutionMenu(false);
          onOpenEvolution("general"); // 👈 importante
        }}
        className="w-full text-left px-4 py-3 text-sm hover:bg-slate-50"
      >
        📊 Evolución general
      </button>

      {/* POR CARRERA */}
      <button
        onClick={() => {
          setOpenEvolutionMenu(false);
          onOpenEvolution("career"); // 👈 importante
        }}
        className="w-full text-left px-4 py-3 text-sm hover:bg-slate-50"
      >
        🎓 Evolución por carrera
      </button>

    </div>
  )}

</div>

  {/* EXPORT GLOBAL */}


  {/* EXPORT POR CARRERA */}
  {hasCareerFilter && (
    <button
      onClick={() => {
        const params = new URLSearchParams({
          year: meta.year.toString(),
          period: meta.period,
          filter: "weekly",
          career: filters.career, // 🔥 importante
        });

        window.open(
          `/dashboard/indicators/seniority/evolution-careers/export?${params.toString()}`,
          "_blank"
        );
      }}
      className="
        px-4 py-2.5
        bg-white
        border
        rounded-xl
        font-semibold
        text-[#0A2540]
        shadow-sm
        hover:bg-slate-50
        transition
        flex items-center gap-2
      "
    >
      📊 Por carrera
    </button>
  )}

</div>

  </div>

  {/* DIVIDER */}
  <div className="my-4 border-t" />

  {/* 🔥 BADGES */}
  <div className="flex flex-wrap items-center gap-3">

    <Badge className="gap-1.5 bg-white text-[#0A2540] shadow">
      <Database className="h-3 w-3 text-teal-400" />
      {meta.vacantes_analizadas.toLocaleString()}{" "}
      {hasCareerFilter ? "vacantes alineadas" : "vacantes del mercado"}
    </Badge>

    <Badge className="gap-1.5 bg-white text-[#0A2540] shadow">
      <Briefcase className="h-3 w-3 text-teal-400" />
      Distribución por nivel
    </Badge>

    <button
      onClick={() => setOpenMarketModal(true)}
      className="
        flex items-center gap-2 rounded-xl border bg-white
        px-3 py-2 shadow-md hover:border-teal-400
      "
    >
      <Database className="h-4 w-4 text-teal-400" />
      <span className="text-sm font-semibold text-[#0A2540]">
        Datos Generales
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

                {/* ================= RIGHT – METODOLOGÍA ================= */}
                <div className="border rounded-xl p-5 bg-white/80 dark:bg-[#0F2A3A] dark:border-[#1E3A4A] backdrop-blur">

                    <div className="flex items-center gap-2 mb-3">
                        <Info className="h-4 w-4 text-teal-400" />
                        <p className="font-semibold  dark:text-slate-100">
                            Metodología de cálculo
                        </p>
                    </div>

                    <ul className="space-y-2 text-sm text-slate-700 dark:text-slate-300">
                        <li>• Detección del seniority solicitado en cada vacante.</li>
                        <li>• Normalización de experiencia (junior / mid / senior).</li>
                        <li>
                            • {hasCareerFilter
                                ? "Asociación directa a la carrera seleccionada mediante tech positions."
                                : "Cobertura del mercado laboral general (sin filtro por carrera)."}
                        </li>
                        <li>• Cálculo porcentual por carrera.</li>
                    </ul>

                    <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">
                        % nivel = (vacantes del nivel ÷ total vacantes de la carrera) × 100
                    </p>

                    <p className="mt-2 text-xs font-semibold text-teal-600 dark:text-teal-300">
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
                                : "bg-teal-50 text-teal-600 hover:bg-teal-100 dark:bg-[#123A52] dark:hover:bg-[#1B4A63]"
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
