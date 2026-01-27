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
import TechnologyJobsModal from "./components/Ranking/TechnologyJobsModal";

import Swal from "sweetalert2";

/* =========================================================
   Breadcrumbs
========================================================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  {
    title: "Ranking de Tecnologías",
    href: "/dashboard/ranking/technologies",
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

export default function RankingTecnologiasPage() {
  const { ranking, kpis, meta, weights } =
    usePage<PageProps>().props;

  /* =========================
     MODAL TENDENCIA
  ========================= */
  const [trendModal, setTrendModal] = useState<{
    open: boolean;
    trend: any | null;
  }>({
    open: false,
    trend: null,
  });

  const openTrendModal = (trend: any) => {
    setTrendModal({
      open: true,
      trend,
    });
  };

  const closeTrendModal = () => {
    setTrendModal({
      open: false,
      trend: null,
    });
  };

  /* =========================
     MODAL OFERTAS (LABORAL)
  ========================= */
  const [jobsModal, setJobsModal] = useState<{
    open: boolean;
    type: "technology" | "trend" | null;
    item: any | null;
  }>({
    open: false,
    type: null,
    item: null,
  });

  const openJobsModal = (
    type: "technology" | "trend",
    item: any
  ) => {
    if (!item || !item.total_jobs || item.total_jobs === 0) return;

    setJobsModal({
      open: true,
      type,
      item,
    });
  };

  const closeJobsModal = () => {
    setJobsModal({
      open: false,
      type: null,
      item: null,
    });
  };

  /* =========================
     MODAL PONDERACIONES
  ========================= */
  const [isWeightModalOpen, setIsWeightModalOpen] = useState(false);

  const handleSaveWeights = (newWeights: WeightConfig) => {
    if (newWeights.laborWeight + newWeights.trendsWeight !== 100) {
      Swal.fire(
        "Error",
        "Las ponderaciones deben sumar 100%",
        "error"
      );
      return;
    }

    Swal.fire({
      title: "Aplicando metodología",
      text: "Actualizando ranking de tecnologías…",
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading(),
    });

    router.post(
      "/dashboard/ranking/technologies/weights",
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
      <Head title="Ranking de Tecnologías | Observatorio ISIL" />

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
                  Ranking General de Tecnologías
                </h2>

                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-3xl">
                  Clasificación unificada que integra tecnologías ISIL y
                  tecnologías en tendencia global, ordenadas según demanda
                  laboral y presencia en reportes especializados.
                </p>
              </div>

              {/* ================= FILTROS ================= */}
              <RankingFilters />

              {/* ================= RANKING ================= */}
              <RankingList
                items={ranking.data}
                pagination={ranking}
                onSelectItem={(action, item) => {
                  console.log("CLICK RANKING TEC:", action, item);

                  /* ========= TENDENCIA ========= */
                  if (action === "trend") {
                    if (!item.is_real_trend || item.trend_reports === 0) return;

                    axios
                      .get(
                        `/dashboard/ranking/technologies/trend/${item.id}`
                      )
                      .then((res) => {
                        const trend = res.data?.data;
                        if (!trend) return;

                        openTrendModal({
                          id: trend.id,
                          name: trend.topic_name,
                          trend_score: trend.trend_score,
                          trend_reports: item.trend_reports,
                          year: trend.year,
                          quarter: trend.quarter,
                          source_title: trend.source_title,
                          source_url: trend.source_url,
                          source_type: trend.source_type,
                        });
                      });

                    return;
                  }

                  /* ========= LABORAL ========= */
                  if (action === "laboral") {
                    if (!item.total_jobs || item.total_jobs === 0) return;

                    if (item.entity_type === "technology") {
                      openJobsModal("technology", item);
                      return;
                    }

                    if (item.entity_type === "trend") {
                      openJobsModal("trend", item);
                      return;
                    }
                  }
                }}
              />
            </div>
          </div>
        </div>

        {/* ================= MODAL OFERTAS LABORALES ================= */}
        {jobsModal.open && jobsModal.item && (
          <TechnologyJobsModal
            open={jobsModal.open}
            onClose={closeJobsModal}
            technologyId={
              jobsModal.type === "technology"
                ? jobsModal.item.id
                : undefined
            }
            trendId={
              jobsModal.type === "trend"
                ? jobsModal.item.id
                : undefined
            }
            title={jobsModal.item.name}
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

    {/* ================= MODAL DETALLE TENDENCIA ================= */}
    {trendModal.open && trendModal.trend && (
      <TrendDetailModal
        open={trendModal.open}
        trend={trendModal.trend}
        onClose={closeTrendModal}
      />
    )}
  </>
);
}