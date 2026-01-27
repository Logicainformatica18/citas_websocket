import AppLayout from "@/layouts/app-layout";
import { usePage } from "@inertiajs/react";

import { RankingCarrerasHeader } from "./components/Header/RankingCarrerasHeader";
import { CareerKpiGrid } from "./components/KPIs/CareerKpiGrid";
import { CareerRankingTable } from "./components/Table/CareerRankingTable";
import { RankingCarrerasFilters } from "./components/Filters/RankingCarrerasFilters";
import { Head } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";

const breadcrumbs: BreadcrumbItem[] = [
    { title: "Dashboard", href: "/dashboard" },

    {
        title: "Demanda laboral por carrera",
        href: "/dashboard/ranking-carreras",
    },
];

/* =====================================================
   Page
===================================================== */
export default function RankingCarrerasPage() {
    const {
        ranking,
        meta,
        filters,
        availableCareers,
    } = usePage().props as {
        ranking: {
            data: any[];
        };
        meta: {
            vacantes_analizadas: number;
            periodo_label: string;
            year: number;
            period: "s1" | "s2";
        };
        filters: {
            year: number;
            period: "s1" | "s2";
            career: string[];
        };
        availableCareers: {
            id: number;
            name: string;
            slug: string;
        }[];
    };

    /* =====================================================
       KPIs derivados
    ===================================================== */
    const totalVacantes = meta?.vacantes_analizadas ?? 0;
    const topCareer = ranking?.data?.[0]?.name ?? null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Demanda laboral por carrera | Observatorio ISIL" />

        <div className="min-h-screen bg-gray-50 dark:bg-gray-900">

            {/* ================= HEADER ================= */}
            <RankingCarrerasHeader meta={meta} />
                {/* ================= CONTENIDO ================= */}
                <div className="mx-auto max-w-7xl px-6 lg:px-8">

                    {/* ================= KPIs ================= */}
                    <div className="pt-8">
                        <CareerKpiGrid
                            total={totalVacantes}
                            topCareer={topCareer}
                        />
                    </div>

                    {/* ================= FILTROS ================= */}
                    <div className="mt-6">
                        <RankingCarrerasFilters
                            careers={filters.career}
                            availableCareers={availableCareers}
                        />
                    </div>

                    {/* ================= RANKING ================= */}
                    <div className="mt-8">
                        <CareerRankingTable rows={ranking.data} />
                    </div>

                    {/* ================= METODOLOGÍA ================= */}
                

                </div>
            </div>
        </AppLayout>
    );
}
