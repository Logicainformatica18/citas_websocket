import { useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head, usePage, router } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";
import axios from "axios";


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
import CertificationJobsModal from "./components/Ranking/CertificationJobsModal";
import CertificationReportsModal from "./components/Ranking/CertificationReportsModal";
import TrendJobsModal from "./components/Ranking/TrendJobsModal";





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

const [trendJobsModal, setTrendJobsModal] = useState<{
  open: boolean;
  trend: any | null;
  jobs: any[];
  pagination: any | null;
}>({
  open: false,
  trend: null,
  jobs: [],
  pagination: null,
});

    const [reportsModal, setReportsModal] = useState<{
        open: boolean;
        certification: any | null;
        reports: any[];
    }>({
        open: false,
        certification: null,
        reports: [],
    });

    /* =========================
       MODAL OFERTAS
    ========================= */
    /* =========================
       MODAL TENDENCIAS / OFERTAS
    ========================= */
   const [trendModal, setTrendModal] = useState<{
  open: boolean;
  trend: any | null;
}>({
  open: false,
  trend: null,
});


    const [certJobsModal, setCertJobsModal] = useState<{
        open: boolean;
        certification: any | null;
        jobs: any[];
        pagination: any | null;
    }>({
        open: false,
        certification: null,
        jobs: [],
        pagination: null,
    });



  const openTrendModal = (item: any) => {
  setTrendModal({
    open: true,
    trend: item,
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
  console.log("RECIBIDO EN PAGE:", action, item.entity_type);

  /* =========================
     CLICK EN TENDENCIAS (DETALLE)
  ========================= */
  if (action === "trend") {

    // Certificación → reportes
    if (item.entity_type === "certification") {
      axios
        .get(`/dashboard/ranking-certificaciones/${item.id}/reports`)
        .then((res) => {
          setReportsModal({
            open: true,
            certification: item,
            reports: res.data.data,
          });
        });
      return;
    }

    // Trend → detalle de tendencia
    if (item.entity_type === "trend") {
      setTrendModal({
        open: true,
        trend: item,
      });
      return;
    }
  }

  /* =========================
     CLICK EN LABORAL (JOBS)
  ========================= */
  if (action === "laboral") {

    // Certificación → jobs por certificación
    if (item.entity_type === "certification") {
      axios
        .get(`/dashboard/ranking-certificaciones/${item.id}/jobs`)
        .then((res) => {
          const paginator = res.data.data;

          setCertJobsModal({
            open: true,
            certification: item,
            jobs: paginator.data,
            pagination: paginator,
          });
        });
      return;
    }

    // Trend → jobs por tendencia
    if (item.entity_type === "trend") {
      axios
        .get(`/dashboard/ranking-certificaciones/trend/${item.id}/jobs`)
        .then((res) => {
          const paginator = res.data.data;

          setTrendJobsModal({
            open: true,
            trend: item,
            jobs: paginator.data,
            pagination: paginator,
          });
        });
      return;
    }
  }
}}

                                />




                            </div>
                        </div>
                    </div>

                    {/* ================= MODAL OFERTAS ================= */}


                    {certJobsModal.open && (
                        <CertificationJobsModal
                            open={certJobsModal.open}
                            certification={certJobsModal.certification}
                            jobs={certJobsModal.jobs}
                            pagination={certJobsModal.pagination}
                            onClose={() =>
                                setCertJobsModal({
                                    open: false,
                                    certification: null,
                                    jobs: [],
                                    pagination: null,
                                })
                            }
                            onPageChange={(paginator) => {
                                setCertJobsModal((prev) => ({
                                    ...prev,
                                    jobs: paginator.data,
                                    pagination: paginator,
                                }));
                            }}
                        />



                    )}
                    {reportsModal.open && (
                        <CertificationReportsModal
                            open={reportsModal.open}
                            certification={reportsModal.certification}
                            reports={reportsModal.reports}
                            onClose={() =>
                                setReportsModal({
                                    open: false,
                                    certification: null,
                                    reports: [],
                                })
                            }
                        />
                    )}
{trendJobsModal.open && (
  <TrendJobsModal
    open={trendJobsModal.open}
    trend={trendJobsModal.trend}
    jobs={trendJobsModal.jobs}
    pagination={trendJobsModal.pagination}
    onClose={() =>
      setTrendJobsModal({
        open: false,
        trend: null,
        jobs: [],
        pagination: null,
      })
    }
    onPageChange={(paginator) => {
      setTrendJobsModal((prev) => ({
        ...prev,
        jobs: paginator.data,
        pagination: paginator,
      }));
    }}
  />
)}


                    {/* ================= MODAL PONDERACIONES ================= */}
                    <WeightConfigModal
                        open={isWeightModalOpen}
                        onOpenChange={setIsWeightModalOpen}
                        weights={weights}
                        onSave={handleSaveWeights}
                    />

                </DashboardProvider>
            </AppLayout>

          {trendModal.open && trendModal.trend && (
  <TrendDetailModal
    open={trendModal.open}
    trend={trendModal.trend}
    onClose={() =>
      setTrendModal({ open: false, trend: null })
    }
  />
)}



        </>

    );
}
