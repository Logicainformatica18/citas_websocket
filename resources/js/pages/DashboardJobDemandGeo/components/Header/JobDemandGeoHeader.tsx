import { Badge } from "@/components/ui/badge";
import {
    MapPin,
    Database,
    Building2,
    Percent,
    Info,
} from "lucide-react";
import { router, usePage } from "@inertiajs/react";

interface HeaderProps {
    meta: {
        year: number;
        period?: "s1" | "s2";
        periodo_label: string;
        total_jobs: number;
        cities_count: number;
        top_city?: string;
        top5_concentration: number;
    };
}

export default function JobDemandGeoHeader({ meta }: HeaderProps) {
    const { filters } = usePage().props as any;

    const activePeriod: "s1" | "s2" =
        meta.period === "s1" || meta.period === "s2"
            ? meta.period
            : "s2";

    const onChange = (params: { year?: number; period?: "s1" | "s2" }) => {
        router.get(
            "/dashboard/indicators/job-demand-geo",
            {
                ...filters,
                year: params.year ?? meta.year,
                period: params.period ?? activePeriod,
                page: 1,
            },
            {
                preserveState: true,
                replace: true,
            }
        );
    };

    return (
        <header className="relative overflow-hidden border-b bg-[#E6F7FD] dark:bg-[#0A2540] px-4 sm:px-6 lg:px-8">
            <div className="absolute inset-0 pointer-events-none">
                <div className="absolute -left-20 -top-20 h-96 w-96 rounded-full bg-[#00B6E8]/30 blur-3xl" />
                <div className="absolute right-0 bottom-0 h-80 w-80 rounded-full bg-[#1CBCE8]/20 blur-3xl" />
            </div>

            <div className="relative mx-auto max-w-7xl py-10 md:py-14">
                <div className="flex flex-col gap-8 md:flex-row md:items-start md:justify-between">
                    <div className="space-y-6 max-w-3xl">
                        <div className="flex items-center gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#00B6E8] shadow-lg">
                                <MapPin className="h-6 w-6 text-white" />
                            </div>
                            <div>
                                <p className="text-sm font-semibold text-[#005F7A] dark:text-[#7DD3FC]">
                                    Observatorio Tecnológico ISIL
                                </p>
                                <h1 className="text-3xl font-extrabold tracking-tight text-[#0A2540] dark:text-slate-100">
                                    Demanda laboral por ciudad
                                </h1>
                            </div>
                        </div>

                        <p className="text-[15px] leading-relaxed text-slate-700 dark:text-slate-300">
                            Análisis geográfico de la demanda laboral, basado en el
                            volumen real de vacantes publicadas por ciudad y su
                            nivel de concentración territorial.
                        </p>

                        <div className="flex flex-wrap items-end gap-8">
                            <div className="flex flex-col gap-2">
                                <span className="text-xs font-semibold text-[#005F7A] dark:text-slate-300">
                                    Año de análisis
                                </span>
                                <div className="relative group rounded-xl border bg-white shadow-sm transition-all hover:border-[#00B6E8] hover:shadow-md hover:-translate-y-[1px]">
                                    <select
                                        value={meta.year}
                                        onChange={(e) =>
                                            onChange({
                                                year: Number(e.target.value),
                                            })
                                        }
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

                            <div className="relative flex flex-col gap-2">
                                <span className="text-xs font-semibold text-[#005F7A] dark:text-slate-300">
                                    Semestre
                                </span>
                                <div className="flex rounded-xl border bg-white shadow-sm overflow-hidden transition-all cursor-pointer hover:border-[#00B6E8] hover:shadow-md hover:-translate-y-[1px] dark:bg-[#0F2A3A]">
                                    {[
                                        { value: "s1", label: "Ene – Jun" },
                                        { value: "s2", label: "Jul – Dic" },
                                    ].map((s) => {
                                        const active =
                                            activePeriod === s.value;

                                        return (
                                            <button
                                                key={s.value}
                                                onClick={() =>
                                                    onChange({
                                                        period:
                                                            s.value as
                                                                | "s1"
                                                                | "s2",
                                                    })
                                                }
                                                className={`px-6 py-2 text-sm font-semibold transition-all cursor-pointer ${
                                                    active
                                                        ? `
                                                            bg-[#00B6E8]
                                                            text-white
                                                            shadow-inner
                                                            ring-2
                                                            ring-[#00B6E8]/60
                                                            ring-offset-1
                                                            ring-offset-white
                                                            dark:ring-offset-[#0F2A3A]
                                                        `
                                                        : `
                                                            text-[#005F7A]
                                                            hover:bg-[#E6F7FD]
                                                            dark:text-slate-300
                                                            dark:hover:bg-[#123A52]
                                                        `
                                                }`}
                                            >
                                                {s.label}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center gap-3">
                                <Badge className="gap-1.5 bg-white text-[#0A2540] shadow">
                                    <Database className="h-3 w-3 text-[#00B6E8]" />
                                    {meta.total_jobs.toLocaleString()} vacantes
                                </Badge>
                                <Badge className="gap-1.5 bg-white text-[#0A2540] shadow">
                                    <Building2 className="h-3 w-3 text-[#00B6E8]" />
                                    {meta.cities_count.toLocaleString()} ciudades
                                </Badge>
                                <Badge className="gap-1.5 bg-white text-[#0A2540] shadow">
                                    <Percent className="h-3 w-3 text-[#00B6E8]" />
                                    Top 5: {meta.top5_concentration}%
                                </Badge>
                            </div>
                        </div>

                        <p className="pt-4 text-sm text-[#0A2540]/70 dark:text-gray-400">
                            <span className="font-semibold text-[#0A2540] dark:text-white">
                                Periodo activo:
                            </span>{" "}
                            {meta.periodo_label}
                        </p>
                    </div>

                    <div className="w-full max-w-sm rounded-2xl border border-[#00B6E8]/40 bg-white p-5 text-left shadow-xl dark:bg-[#102C3C]">
                        <div className="mb-2 flex items-center gap-2">
                            <Info className="h-4 w-4 text-[#00B6E8]" />
                            <p className="text-xs font-bold uppercase tracking-wider text-[#00B6E8]">
                                Metodología de cálculo
                            </p>
                        </div>

                        <div className="space-y-1 text-sm text-[#0A2540] dark:text-gray-300">
                            <p>• Conteo total de vacantes publicadas por ciudad</p>
                            <p>• Agrupación jerárquica ciudad → región → país</p>
                            <p>• Ranking por volumen absoluto de ofertas</p>
                            <p>
                                • <strong>Concentración Top 5</strong>: porcentaje
                                de ofertas acumuladas en las 5 ciudades con mayor
                                demanda
                            </p>
                        </div>

                        <p className="mt-3 border-t border-[#00B6E8]/30 pt-3 text-xs text-[#0A2540]/70 dark:text-gray-400">
                            Los cálculos se realizan únicamente con datos del
                            período seleccionado.
                        </p>
                    </div>
                </div>
            </div>
        </header>
    );
}
