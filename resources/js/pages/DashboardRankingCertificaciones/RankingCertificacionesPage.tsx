import { useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head, usePage, router } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";

import { DashboardProvider } from "@/pages/dashboards/DashboardContext";

import TrendDetailModal from "./components/Ranking/TrendDetailModal";

import { Header as RankingHeader } from "./components/Header/RankingHeader";
import {
    WeightConfigModal,
    WeightConfig,
} from "./components/Header/WeightConfigModal";

import KpiGrid from "./components/KPIs/KpiGrid";
import RankingFilters from "./components/Filters/RankingFilters";
import RankingList from "./components/Ranking/RankingList";


import Swal from "sweetalert2";

/* =========================================================
   Breadcrumbs
========================================================= */
const breadcrumbs: BreadcrumbItem[] = [
    { title: "Dashboard", href: "/dashboard" },
    {
        title: "Ranking de Certificaciones",
        href: "/dashboard/ranking-certificaciones",
    },
];

type PageProps = {
    ranking: {
        data: any[];
        current_page: number;
        last_page: number;
        per_page: number;
        prev_page_url?: string | null;
        next_page_url?: string | null;
    };
    kpis: any;
    meta: {
        year: number;
        period: string;
    };
    weights: {
        laborWeight: number;
        trendsWeight: number;
    };
};

export default function RankingCertificacionesPage() {
    const { ranking, kpis, meta, weights } =
        usePage<PageProps>().props;

    /* =========================
       MODAL OFERTAS
    ========================= */
    /* =========================
       MODAL TENDENCIAS / OFERTAS
    ========================= */
    const [trendModal, setTrendModal] = useState<{
        open: boolean;
        trendId: number | null;
        tab: "trend" | "jobs";
    }>({
        open: false,
        trendId: null,
        tab: "trend",
    });


    const openTrendModal = (
        item: any,
        tab: "trend" | "jobs" = "trend"
    ) => {
        setTrendModal({
            open: true,
            trendId: item.id,
            tab,
        });
    };


    const closeTrendModal = () => {
        setTrendModal({
            open: false,
            trendId: null,
            tab: "trend",
        });
    };


    /* =========================
       MODAL PONDERACIONES
    ========================= */
    const [isWeightModalOpen, setIsWeightModalOpen] = useState(false);

    const handleSaveWeights = (newWeights: WeightConfig) => {
        if (newWeights.laborWeight + newWeights.trendsWeight !== 100) {
            Swal.fire("Error", "Las ponderaciones deben sumar 100%", "error");
            return;
        }

        Swal.fire({
            title: "Aplicando metodología",
            text: "Actualizando ranking…",
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
        });

        router.post(
            "/dashboard/ranking-certificaciones/weights",
            {
                labor_weight: newWeights.laborWeight / 100,
                trend_weight: newWeights.trendsWeight / 100,
            },
            {
                onSuccess: () => {
                    Swal.fire({
                        icon: "success",
                        title: "Metodología actualizada",
                        timer: 1400,
                        showConfirmButton: false,
                    });

                    router.reload({
                        only: ["ranking", "weights", "meta", "kpis"],
                    });
                },
            }
        );
    };

    return (
        <>

            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Ranking de Certificaciones | Observatorio ISIL" />

                <DashboardProvider>
                    <div className="bg-background px-6 py-6">
                        <div className="flex gap-6">
                            <div className="flex-1 space-y-6">

                                {/* ================= HEADER ================= */}
                                <RankingHeader
                                    weights={weights}
                                    onEditWeights={() => setIsWeightModalOpen(true)}
                                    meta={meta}
                                />

                                {/* ================= KPIs ================= */}
                                <KpiGrid items={kpis} />

                                {/* ================= DESCRIPCIÓN ================= */}
                                <div className="pt-4 border-t border-slate-200 dark:border-slate-700">
                                    <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                                        Ranking General de Certificaciones
                                    </h2>

                                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-3xl">
                                        Clasificación unificada que integra certificaciones ISIL
                                        y certificaciones en tendencia global, ordenadas según
                                        la metodología seleccionada.
                                    </p>
                                </div>

                                {/* ================= FILTROS ================= */}
                                <RankingFilters />

                                {/* ================= RANKING ÚNICO ================= */}
                                <RankingList
                                    items={ranking.data}
                                    pagination={ranking}
                                    onSelectItem={(action, item) => {
                                        console.log("RECIBwwwwwIDO EN PAGE:", action, item.name);



                                        if (action === "trend") {
                                            openTrendModal(item, "trend");
                                        }

                                        if (action === "laboral") {
                                            openTrendModal(item, "jobs");
                                        }
                                    }}
                                />



                            </div>
                        </div>
                    </div>

                    {/* ================= MODAL OFERTAS ================= */}




                    {/* ================= MODAL PONDERACIONES ================= */}
                    <WeightConfigModal
                        open={isWeightModalOpen}
                        onOpenChange={setIsWeightModalOpen}
                        weights={weights}
                        onSave={handleSaveWeights}
                    />

                </DashboardProvider>
            </AppLayout>

         {trendModal.open && trendModal.trendId && (
  <TrendDetailModal
    open={trendModal.open}
    trendId={trendModal.trendId}
    activeTab={trendModal.tab}
    onClose={closeTrendModal}
    onTabChange={(tab) =>
      setTrendModal((s) => ({ ...s, tab }))
    }
  />
)}


        </>

    );
}
