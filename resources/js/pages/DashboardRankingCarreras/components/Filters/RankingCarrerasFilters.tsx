import { router, usePage } from "@inertiajs/react";
import { X, Filter } from "lucide-react";

/* =====================================================
   Types
===================================================== */
interface Props {
    careers?: string[];
    availableCareers: { id: number; name: string; slug: string }[];
}

/* =====================================================
   Component
===================================================== */
export function RankingCarrerasFilters({
    careers = [],
    availableCareers,
}: Props) {
    const page = usePage().props as any;

    const activeCareers: string[] = careers ?? page?.filters?.career ?? [];

    const apply = (careerSlugs: string[]) => {
        router.get(
            "/dashboard/ranking-carreras",
            {
                ...page.filters,
                career: careerSlugs,
                page: 1,
            },
            {
                preserveState: true,
                replace: true,
            }
        );
    };

    const toggleCareer = (slug: string) => {
        if (activeCareers.includes(slug)) {
            apply(activeCareers.filter((c) => c !== slug));
        } else {
            apply([...activeCareers, slug]);
        }
    };

    const clearAll = () => apply([]);

    /* =====================================================
       Render
    ===================================================== */
    return (
        <div
            className="
                rounded-2xl
                border
                bg-white
                p-5
                shadow-sm
                dark:bg-[#0F2A3A]
                dark:border-[#1E3A4A]
            "
        >
            {/* ===== HEADER ===== */}
            <div className="flex items-center justify-between mb-4">
                <div className="flex items-center gap-2">
                    <Filter className="h-4 w-4 text-[#1CBCE8]" />
                    <p className="text-sm font-semibold text-[#0A2540] dark:text-slate-100">
                        Filtrar por carrera
                    </p>
                </div>

                {activeCareers.length > 0 && (
                    <button
                        onClick={clearAll}
                        className="
                            flex items-center gap-1
                            text-xs
                            font-semibold
                            text-[#1CBCE8]
                            hover:underline
                        "
                    >
                        <X className="h-3 w-3" />
                        Limpiar
                    </button>
                )}
            </div>

            {/* ===== CHIPS ===== */}
            <div className="flex flex-wrap gap-2">
                {availableCareers.map((career) => {
                    const active = activeCareers.includes(career.slug);

                    return (
                        <button
                            key={career.id}
                            onClick={() => toggleCareer(career.slug)}
                            className={`
                                rounded-full
                                px-4
                                py-1.5
                                text-xs
                                font-semibold
                                transition-all
                                border
                                ${
                                    active
                                        ? "bg-[#1CBCE8] text-white border-[#1CBCE8]"
                                        : "bg-[#E6F7FD] text-[#005F7A] border-[#BEEAF7] hover:bg-[#DFF3FB]"
                                }
                                dark:${
                                    active
                                        ? "bg-[#1CBCE8] text-white"
                                        : "bg-[#123A52] text-slate-300 border-[#1E3A4A]"
                                }
                            `}
                        >
                            {career.name}
                        </button>
                    );
                })}
            </div>

            {/* ===== FOOTER ===== */}
            {activeCareers.length > 0 && (
                <p className="mt-4 text-xs text-[#0A2540]/70 dark:text-gray-400">
                    {activeCareers.length} carrera
                    {activeCareers.length !== 1 && "s"} seleccionada
                    {activeCareers.length !== 1 && "s"}
                </p>
            )}
        </div>
    );
}
