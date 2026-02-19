import { Badge } from "@/components/ui/badge";
import {
    BarChart3,
    Database,
    Sparkles,
    Settings2,
    GraduationCap,
} from "lucide-react";
import { router, usePage } from "@inertiajs/react";
import { WeightConfig } from  "./WeightConfigModal";

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

export default function Header({
    meta,
    weights,
    onEditWeights,
}: HeaderProps) {
    const pageProps = usePage().props as any;
const filters = pageProps?.filters ?? {};

    const onChange = (params: { year?: number; period?: "s1" | "s2" }) => {
        router.get(
            "/dashboard/indicators/pe-alignment",
            {
                ...filters, // 🔥 MISMA LÓGICA QUE RANKING
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
                                <GraduationCap className="h-6 w-6 text-white" />
                            </div>

                            <div>
                                <p className="text-sm font-semibold text-[#005F7A] dark:text-[#7DD3FC]">
                                    Observatorio Tecnológico ISIL
                                </p>

                                <h1 className="text-3xl font-extrabold tracking-tight text-[#0A2540] dark:text-slate-100">
                                   Alineación de Programa de estudios con tendencias del sector
                                </h1>
                            </div>
                        </div>

                        {/* Description */}
                        <p className="text-[15px] leading-relaxed text-slate-700 dark:text-slate-300">
                            Indicador que mide el nivel de alineación del Perfil de Egreso con
                            la demanda real del mercado laboral y las competencias estratégicas
                            del futuro, basándose en vacantes y reportes globales.
                        </p>

                        {/* ===== CONTROLES DE PERÍODO ===== */}
                        <div className="flex flex-wrap items-end gap-8">

                            {/* ===== AÑO ===== */}
                            {/* <div className="flex flex-col gap-2">
                                <span className="text-xs font-semibold text-[#005F7A] dark:text-slate-300">
                                    Año de análisis
                                </span>

                                <div
                                    className="
                                        relative
                                        group
                                        rounded-xl
                                        border
                                        bg-white
                                        shadow-sm
                                        transition-all
                                        hover:border-[#00B6E8]
                                        hover:shadow-md
                                        hover:-translate-y-[1px]
                                    "
                                >
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
                                        {[ 2025, 2026].map((y) => (
                                            <option key={y} value={y}>
                                                {y}
                                            </option>
                                        ))}
                                    </select>

                                    <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[#00B6E8] opacity-70 group-hover:opacity-100 transition">
                                        ⌄
                                    </span>
                                </div>
                            </div> */}

                            {/* ===== SEMESTRE ===== */}
                            {/* <div className="relative flex flex-col gap-2">
                                <span className="text-xs font-semibold text-[#005F7A] dark:text-slate-300">
                                    Semestre
                                </span>

                                <div
                                    className="
                                        flex
                                        rounded-xl
                                        border
                                        bg-white
                                        shadow-sm
                                        overflow-hidden
                                        transition-all
                                        cursor-pointer
                                        hover:border-[#00B6E8]
                                        hover:shadow-md
                                        hover:-translate-y-[1px]
                                        dark:bg-[#0F2A3A]
                                    "
                                >
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
                                                    px-6
                                                    py-2
                                                    text-sm
                                                    font-semibold
                                                    transition-all
                                                    ${active
                                                        ? "bg-[#00B6E8] text-white shadow-inner"
                                                        : "text-[#005F7A] hover:bg-[#E6F7FD] dark:text-slate-300 dark:hover:bg-[#123A52]"
                                                    }
                                                `}
                                            >
                                                {s.label}
                                            </button>
                                        );
                                    })}
                                </div>

                                <span className="absolute -bottom-5 left-1/2 -translate-x-1/2 text-[11px] text-[#005F7A]/70 dark:text-slate-400 whitespace-nowrap">
                                    Haz clic para cambiar el período
                                </span>
                            </div> */}

                            {/* ===== BADGES ===== */}
                            <div className="flex flex-wrap items-center gap-3">
                                <Badge className="gap-1.5 bg-white text-[#0A2540] shadow hover:shadow-md transition">
                                    <Database className="h-3 w-3 text-[#00B6E8]" />
                                    {meta.vacantes_analizadas.toLocaleString()} vacantes analizadas
                                </Badge>

                                <Badge className="gap-1.5 bg-white text-[#0A2540] shadow hover:shadow-md transition">
                                    <Sparkles className="h-3 w-3 text-[#00B6E8]" />
                                    {meta.reportes_analizados.toLocaleString()} reportes analizados
                                </Badge>
                            </div>
                        </div>

                        {/* Active period */}
                        {/* <p className="pt-4 text-sm text-[#0A2540]/70 dark:text-gray-400">
                            <span className="font-semibold text-[#0A2540] dark:text-white">
                                Periodo activo:
                            </span>{" "}
                            {meta.periodo_label}
                        </p> */}
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
                                Metodología del indicador
                            </p>
                            <Settings2 className="h-4 w-4 text-[#00B6E8] opacity-60 group-hover:opacity-100" />
                        </div>

                        <div className="space-y-1 text-sm text-[#0A2540] dark:text-gray-300">
                            <p>
                                <span className="font-bold text-[#00B6E8]">
                                    {weights.laborWeight}%
                                </span>{" "}
                                Mercado laboral
                            </p>
                            <p>
                                <span className="font-bold text-[#00B6E8]">
                                    {weights.trendsWeight}%
                                </span>{" "}
                                Prospectiva y tendencias
                            </p>
                        </div>

                        <p className="mt-3 text-xs text-[#0A2540]/70 dark:text-gray-400">
                            Índice = ({weights.laborWeight / 100} × Mercado) + (
                            {weights.trendsWeight / 100} × Prospectiva)
                        </p>

                        <p className="mt-3 border-t border-[#00B6E8]/30 pt-3 text-xs text-[#0A2540]/70 dark:text-gray-400">
                            Los cálculos consideran únicamente datos del período seleccionado.
                        </p>

                        <p className="mt-3 text-xs font-semibold text-[#00B6E8] group-hover:underline">
                            Haz clic para editar ponderaciones
                        </p>
                    </button>
                </div>
            </div>
        </header>
    );
}
