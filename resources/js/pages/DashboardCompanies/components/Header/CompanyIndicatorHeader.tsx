import { useState } from "react";
import { router, usePage } from "@inertiajs/react";
import { Badge } from "@/components/ui/badge";
import { JobMarketStatusModal } from "./JobMarketStatusModal";

import {
    Briefcase,
    Database,
    Building2,
    Info,
} from "lucide-react";

interface HeaderProps {
    meta: {
        year: number;
        period: "s1" | "s2";
        periodo_label: string;
        vacantes_analizadas?: number;
        empresas_activas: number;
    };
    onOpenEvolution: () => void; // 🔥 agregar
}

export function CompanyIndicatorHeader({ meta, onOpenEvolution }: HeaderProps) {
    const { filters, jobMarketStatus } = usePage().props as any;

    const [openMarketModal, setOpenMarketModal] = useState(false);

    const vacantes = meta.vacantes_analizadas ?? 0;

    const onChange = (params: { year?: number; period?: "s1" | "s2" }) => {
        router.get(
            "/dashboard/indicators/companies",
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
                    <div className="absolute -left-20 -top-20 h-96 w-96 rounded-full bg-teal-300/30 blur-3xl" />
                    <div className="absolute right-0 bottom-0 h-80 w-80 rounded-full bg-teal-200/20 blur-3xl" />
                </div>

                {/* ===== CONTENT ===== */}
                <div className="relative mx-auto max-w-7xl py-10 md:py-14">
                    <div className="flex flex-col gap-8 md:flex-row md:items-start md:justify-between">

                        {/* ================= LEFT ================= */}
                        <div className="space-y-6 max-w-3xl">

                            {/* Title */}
                            <div className="flex items-center gap-4">
                                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-teal-400 shadow-lg">
                                    <Briefcase className="h-6 w-6 text-white" />
                                </div>

                                <div>
                                    <p className="text-sm font-semibold text-teal-600 dark:text-teal-300">
                                        Observatorio Tecnológico ISIL
                                    </p>

                                    <h1 className="text-3xl font-extrabold tracking-tight text-[#0A2540] dark:text-slate-100">
                                        Ranking de Empresas
                                    </h1>
                                </div>
                            </div>

                            {/* Description */}
                            <p className="text-[15px] leading-relaxed text-slate-700 dark:text-slate-300">
                                Identificación y ranking de las empresas que concentran
                                mayor número de vacantes tecnológicas publicadas en
                                portales de empleo durante el Periodo seleccionado.
                            </p>

                            {/* ===== CONTROLES ===== */}
                           {/* ===== CONTROLES ===== */}
<div className="mt-4 rounded-2xl border bg-white/80 backdrop-blur p-4 shadow-sm">

  {/* TOP: filtros + botón */}
  <div className="flex flex-wrap items-center justify-between gap-4">

    {/* LEFT */}
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
            className="w-[100px] px-3 py-2 text-sm font-semibold"
          >
            {[2025, 2026].map((y) => (
              <option key={y} value={y}>{y}</option>
            ))}
          </select>
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
              className={`px-5 py-2 text-sm font-semibold ${
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

    {/* RIGHT: botón evolución */}
    <button
      onClick={onOpenEvolution}
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

  {/* BOTTOM: métricas */}
  <div className="flex flex-wrap items-center gap-3">

    <Badge className="gap-1.5 bg-white text-[#0A2540] shadow">
      <Database className="h-3 w-3 text-teal-400" />
      {vacantes.toLocaleString()} vacantes
    </Badge>

    <Badge className="gap-1.5 bg-white text-[#0A2540] shadow">
      <Building2 className="h-3 w-3 text-teal-400" />
      {meta.empresas_activas.toLocaleString()} empresas
    </Badge>

    <button
      onClick={() => setOpenMarketModal(true)}
      className="
      group flex items-center gap-2
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

                            {/* Active period */}
                            <p className="pt-4 text-sm text-[#0A2540]/70 dark:text-gray-400">
                                <span className="font-semibold text-[#0A2540] dark:text-white">
                                    Periodo activo:
                                </span>{" "}
                                {meta.periodo_label}
                            </p>
                        </div>

                        {/* ================= RIGHT – METODOLOGÍA ================= */}
                        <div className="w-full max-w-sm rounded-2xl border border-teal-400/40 bg-white p-5 shadow-xl dark:bg-[#102C3C]">

                            <div className="mb-2 flex items-center justify-between">
                                <p className="text-xs font-bold uppercase tracking-wider ">
                                    Metodología
                                </p>

                                <Info className="h-4 w-4 text-teal-400 opacity-70" />
                            </div>

                            <div className="space-y-2 text-sm text-[#0A2540] dark:text-gray-300">
                                <p>• Identificación de empresa por vacante</p>
                                <p>• Conteo total de vacantes publicadas</p>
                                <p>• Ordenamiento de mayor a menor</p>
                            </div>

                            <p className="mt-3 border-t border-teal-400/30 pt-3 text-xs text-[#0A2540]/70 dark:text-gray-400">
                                Fuente: Portales de empleo (100%)
                            </p>

                            <p className="mt-3 text-xs text-[#0A2540]/70 dark:text-gray-400">
                                El ranking refleja volumen de publicación,
                                no calidad ni seniority de las vacantes.
                            </p>
                        </div>
                    </div>
                </div>
            </header>

            {/* ===== MODAL ===== */}
            <JobMarketStatusModal
                open={openMarketModal}
                onOpenChange={setOpenMarketModal}
                data={jobMarketStatus}
            />
        </>
    );
}
