import { useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head, usePage, router } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";
import axios from "axios";

import { DashboardProvider } from "@/pages/dashboards/DashboardContext";

import TrendDetailModal from "./components/Ranking/TrendDetailModal";
import LanguageJobsModal from "./components/Ranking/LanguageJobsModal";

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
    title: "Ranking de Lenguajes",
    href: "/dashboard/ranking/languages",
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
    trendWeight: number;
  };
};

export default function RankingLenguajesPage() {
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
    setTrendModal({ open: true, trend });
  };

  const closeTrendModal = () => {
    setTrendModal({ open: false, trend: null });
  };

  /* =========================
     MODAL OFERTAS LABORALES
  ========================= */
  const [jobsModal, setJobsModal] = useState<{
    open: boolean;
    item: any | null;
  }>({
    open: false,
    item: null,
  });

  const openJobsModal = (item: any) => {
    setJobsModal({ open: true, item });
  };

  const closeJobsModal = () => {
    setJobsModal({ open: false, item: null });
  };

  /* =========================
     MODAL PONDERACIONES
  ========================= */
  const [isWeightModalOpen, setIsWeightModalOpen] = useState(false);

  const handleSaveWeights = (newWeights: WeightConfig) => {
    if (newWeights.laborWeight + newWeights.trendWeight !== 100) {
      Swal.fire(
        "Error",
        "Las ponderaciones deben sumar 100%",
        "error"
      );
      return;
    }

    Swal.fire({
      title: "Aplicando metodología",
      text: "Actualizando ranking de lenguajes…",
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading(),
    });

    router.post(
      "/dashboard/ranking/languages/weights",
      {
        labor_weight: newWeights.laborWeight / 100,
        trend_weight: newWeights.trendWeight / 100,
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
        <Head title="Ranking de Lenguajes | Observatorio ISIL" />

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
                    Ranking General de Lenguajes
                  </h2>

                  <p className="mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-3xl">
                    Clasificación de lenguajes ISIL y lenguajes en tendencia,
                    considerando demanda laboral y presencia en reportes
                    especializados.
                  </p>
                </div>

                {/* ================= FILTROS ================= */}
                <RankingFilters />

                {/* ================= RANKING ================= */}
                <RankingList
                  items={ranking.data}
                  pagination={ranking}
                  onSelectItem={(action, item) => {
                    console.log("CLICK RANKING LANG:", action, item);

                    /* ---------- TENDENCIA ---------- */
                    if (action === "trend") {
                      if (
                        !item.is_real_trend ||
                        Number(item.trend_reports) === 0
                      ) {
                        return;
                      }

                      axios
                        .get(
                          `/dashboard/ranking/languages/trend/${item.id}`
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

                    /* ---------- LABORAL ---------- */
                    if (action === "laboral") {
                      if (item.entity_type !== "language") {
                        return;
                      }

                      openJobsModal(item);
                    }
                  }}
                />
              </div>
            </div>
          </div>

          {/* ================= MODAL OFERTAS ================= */}
          {jobsModal.open && jobsModal.item && (
            <LanguageJobsModal
              open={jobsModal.open}
              onClose={closeJobsModal}
              languageId={jobsModal.item.id}
              languageName={jobsModal.item.name}
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

      {/* ================= MODAL TENDENCIA ================= */}
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
