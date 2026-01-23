import { Badge } from "@/components/ui/badge";
import {
    BarChart3,
    Database,
    Briefcase,
    CheckCircle2,
} from "lucide-react";
import { router, usePage } from "@inertiajs/react";

/* =====================================================
   Types
===================================================== */
interface HeaderProps {
    meta?: {
        year?: number;
        period?: "s1" | "s2";
        periodo_label?: string;
        vacantes_analizadas?: number;
    };
}

/* =====================================================
   Component
===================================================== */
export function RankingCarrerasHeader({ meta }: HeaderProps) {
    const page = usePage().props as any;

    /* =====================================================
       🛡️ NORMALIZACIÓN (ANTI-CRASH + REACTIVIDAD)
    ===================================================== */
    const safeMeta = {
        year: meta?.year ?? page?.filters?.year ?? new Date().getFullYear(),
        period: meta?.period ?? page?.filters?.period ?? "s2",
        periodo_label: meta?.periodo_label ?? "",
        vacantes_analizadas: meta?.vacantes_analizadas ?? 0,
    };

    const baseFilters = {
        ...(page?.filters ?? {}),
        year: safeMeta.year,
        period: safeMeta.period,
    };

    /* =====================================================
       Actions
    ===================================================== */
    const onChange = (params: { year?: number; period?: "s1" | "s2" }) => {
        router.get(
            "/dashboard/ranking-carreras",
            {
                ...baseFilters,
                year: params.year ?? safeMeta.year,
                period: params.period ?? safeMeta.period,
                page: 1,
            },
            {
                preserveState: true,
                replace: true,
            }
        );
    };

    /* =====================================================
       Render
    ===================================================== */
    return (
        <header className="relative overflow-hidden border-b bg-[#E6F7FD] dark:bg-[#0A2540] px-4 sm:px-6 lg:px-8">

            {/* ===== BACKGROUND ===== */}
            <div className="absolute inset-0 pointer-events-none">
                <div className="absolute -left-20 -top-20 h-96 w-96 rounded-full bg-[#00B6E8]/30 blur-3xl" />
                <div className="absolute right-0 bottom-0 h-80 w-80 rounded-full bg-[#1CBCE8]/20 blur-3xl" />
            </div>

            {/* ===== CONTENT ===== */}
            <div className="relative mx-auto max-w-7xl py-10 md:py-14">
                <div className="flex flex-col gap-10 md:flex-row md:items-start md:justify-between">

                    {/* ================= LEFT ================= */}
                    <div className="space-y-6 max-w-3xl">

                        {/* Title */}
                        <div className="flex items-center gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#00B6E8] shadow-lg">
                                <Briefcase className="h-6 w-6 text-white" />
                            </div>

                            <div>
                                <p className="text-sm font-semibold text-[#005F7A] dark:text-[#7DD3FC]">
                                    Observatorio Tecnológico ISIL
                                </p>

                                <h1 className="text-3xl font-extrabold tracking-tight text-[#0A2540] dark:text-slate-100">
                                    Demanda laboral por carrera
                                </h1>
                            </div>
                        </div>

                        {/* Description */}
                        <p className="text-[15px] leading-relaxed text-slate-700 dark:text-slate-300">
                            Análisis de la demanda laboral por carrera académica,
                            basado exclusivamente en la identificación de roles
                            tecnológicos dentro de ofertas laborales reales.
                        </p>

                        {/* ===== CONTROLES ===== */}
                        <div className="flex flex-wrap items-end gap-8">

                            {/* ===== AÑO ===== */}
                            <div className="flex flex-col gap-2">
                                <span className="text-xs font-semibold text-[#005F7A] dark:text-slate-300">
                                    Año de análisis
                                </span>

                                <div className="relative group rounded-xl border bg-white shadow-sm transition-all hover:border-[#00B6E8] hover:shadow-md hover:-translate-y-[1px] dark:bg-[#0F2A3A]">
                                    <select
                                        value={safeMeta.year}
                                        onChange={(e) => onChange({ year: Number(e.target.value) })}
                                        className="w-[120px] appearance-none bg-transparent px-4 py-2 text-sm font-semibold text-[#0A2540] cursor-pointer focus:outline-none dark:text-slate-100"
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

                            {/* ===== SEMESTRE (FEEDBACK FUERTE) ===== */}
                            <div className="flex flex-col gap-2">
                                <span className="text-xs font-semibold text-[#005F7A] dark:text-slate-300">
                                    Semestre
                                </span>

                                <div className="flex rounded-xl border bg-white shadow-sm overflow-hidden dark:bg-[#0F2A3A]">
                                    {[
                                        { value: "s1", label: "Ene – Jun" },
                                        { value: "s2", label: "Jul – Dic" },
                                    ].map((s) => {
                                        const active = safeMeta.period === s.value;

                                        return (
                                            <button
                                                key={s.value}
                                                onClick={() => onChange({ period: s.value as "s1" | "s2" })}
                                                className={`
                                                    px-6 py-2 text-sm font-semibold transition-all
                                                    flex items-center gap-2
                                                    ${active
                                                        ? "bg-[#00B6E8] text-white shadow-inner"
                                                        : "text-[#005F7A] hover:bg-[#E6F7FD] dark:text-slate-300 dark:hover:bg-[#123A52]"
                                                    }
                                                `}
                                            >
                                                {active && <CheckCircle2 className="w-4 h-4" />}
                                                {s.label}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* ===== BADGES ===== */}
                            <div className="flex flex-wrap items-center gap-3">
                                <Badge className="gap-1.5 bg-white text-[#0A2540] shadow dark:bg-[#102C3C] dark:text-slate-100">
                                    <Database className="h-3 w-3 text-[#00B6E8]" />
                                    {safeMeta.vacantes_analizadas.toLocaleString()} vacantes analizadas
                                </Badge>

                                <Badge className="gap-1.5 bg-white text-[#0A2540] shadow dark:bg-[#102C3C] dark:text-slate-100">
                                    <BarChart3 className="h-3 w-3 text-[#00B6E8]" />
                                    Peso laboral: 100%
                                </Badge>
                            </div>
                        </div>

                        {/* Active period */}
                        {safeMeta.periodo_label && (
                            <p className="pt-4 text-sm text-[#0A2540]/70 dark:text-gray-400">
                                <span className="font-semibold text-[#0A2540] dark:text-white">
                                    Periodo activo:
                                </span>{" "}
                                {safeMeta.periodo_label}
                            </p>
                        )}
                    </div>

                    {/* ================= RIGHT ================= */}
                  {/* ================= RIGHT ================= */}
<div className="w-full max-w-sm rounded-2xl border border-[#00B6E8]/40 bg-white p-6 text-left shadow-xl dark:bg-[#102C3C]">

    {/* ===== TITLE ===== */}
    <p className="text-xs font-bold uppercase tracking-wider text-[#00B6E8] mb-3">
        Metodología de cálculo
    </p>

    {/* ===== ACTION ===== */}
 

    {/* ===== STEPS ===== */}
    <div className="space-y-2 text-sm text-[#0A2540] dark:text-gray-300">
        <p>1. Identificación de roles en ofertas laborales</p>
        <p>2. Asociación de roles a carreras académicas</p>
        <p>3. Conteo de vacantes únicas por carrera</p>
    </div>

    {/* ===== FOOTER ===== */}
    <p className="mt-4 mb-4 border-t border-[#00B6E8]/30 pt-3 text-xs text-[#0A2540]/70 dark:text-gray-400">
        Los cálculos se realizan únicamente con datos del período seleccionado.
        
    </p>
       <button
        onClick={async () => {
            try {
                await fetch("/dashboard/ranking-carreras/sync", {
                    method: "POST",
                    headers: {
                        "X-Requested-With": "XMLHttpRequest",
                    },
                });
                window.location.reload();
            } catch {
                alert("No se pudo actualizar los datos laborales");
            }
        }}
        className="
            mb-4
            inline-flex
            items-center
            gap-2
            rounded-xl
            bg-[#00B6E8]
            px-4
            py-2
            text-sm
            font-semibold
            text-white
            shadow
            hover:bg-[#0EA5E9]
            transition
        "
    >
        <CheckCircle2 className="h-4 w-4" />
        Actualizar datos laborales
    </button>
</div>

                </div>
            </div>
        </header>
    );
}
