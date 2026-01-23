import { Briefcase, Trophy } from "lucide-react";

interface CareerKpiGridProps {
    total?: number;
    topCareer?: string | null;
}

export function CareerKpiGrid({
    total = 0,
    topCareer = null,
}: CareerKpiGridProps) {
    return (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-5 mb-8">
            <KpiCard
                icon={<Briefcase className="h-5 w-5 text-[#00B6E8]" />}
                label="Vacantes analizadas"
                value={total.toLocaleString()}
                helper="Ofertas laborales únicas consideradas"
            />

            <KpiCard
                icon={<Trophy className="h-5 w-5 text-[#8B5CF6]" />}
                label="Carrera líder"
                value={topCareer ?? "—"}
                highlight
                helper={
                    topCareer
                        ? "Mayor demanda laboral en el período"
                        : "Sin datos suficientes"
                }
            />
        </div>
    );
}

/* =====================================================
   KPI Card — MEDIANO (ISIL balanced)
===================================================== */
function KpiCard({
    icon,
    label,
    value,
    helper,
    highlight = false,
}: {
    icon: React.ReactNode;
    label: string;
    value: string;
    helper?: string;
    highlight?: boolean;
}) {
    return (
        <div
            className={`
                relative
                rounded-2xl
                border
                bg-white
                px-6 py-5
                shadow-sm
                transition-all
                hover:shadow-md
                dark:bg-[#0F2A3A]
                ${highlight
                    ? "border-[#8B5CF6]/40"
                    : "border-[#00B6E8]/30"}
            `}
        >
            {/* Accent bar */}
            <div
                className={`absolute inset-x-0 top-0 h-1 rounded-t-2xl ${
                    highlight ? "bg-[#8B5CF6]" : "bg-[#00B6E8]"
                }`}
            />

            <div className="flex items-center gap-4 mb-3">
                <div
                    className={`
                        flex h-11 w-11 items-center justify-center
                        rounded-xl
                        ${highlight
                            ? "bg-purple-100 text-purple-600 dark:bg-purple-900/30"
                            : "bg-[#E6F7FD] text-[#00B6E8] dark:bg-[#123A52]"
                        }
                    `}
                >
                    {icon}
                </div>

                <p className="text-xs font-semibold uppercase tracking-wide text-[#005F7A] dark:text-slate-300">
                    {label}
                </p>
            </div>

            <p
                className={`
                    text-2xl font-extrabold tracking-tight
                    ${highlight
                        ? "text-[#8B5CF6]"
                        : "text-[#0C647A] dark:text-[#1CBCE8]"}
                `}
            >
                {value}
            </p>

            {helper && (
                <p className="mt-2 text-xs text-[#0A2540]/70 dark:text-gray-400">
                    {helper}
                </p>
            )}
        </div>
    );
}
