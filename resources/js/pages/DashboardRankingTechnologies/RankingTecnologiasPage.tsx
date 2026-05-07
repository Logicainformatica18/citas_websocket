import { useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head, usePage, router } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";
import axios from "axios";

import { DashboardProvider } from "@/pages/dashboards/DashboardContext";

import TrendDetailModal from "./components/Ranking/TrendDetailModal";
import { WeeklyEvolutionModal } from "./components/Header/WeeklyEvolutionModal";
import { Header as RankingHeader } from "./components/Header/RankingHeader";
import TrendJobsModal from "./components/Ranking/TrendJobsModal";
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
  technologyName: string | null;
  reports: any[];
  pagination?: any;
  stats?: {
    tavily_total: number;
    gpt_total: number;
  };
}>({
  open: false,
  technologyName: null,
  reports: [],
  pagination: null,
  stats: undefined,
});
const [openWeeklyModal, setOpenWeeklyModal] = useState(false);

const openTrendModal = (payload: {
  technologyName: string;
  reports: any[];
  pagination?: any;
  stats?: {
    tavily_total: number;
    gpt_total: number;
  };
}) => {
  setTrendModal({
    open: true,
    technologyName: payload.technologyName,
    reports: payload.reports,
    pagination: payload.pagination,
    stats: payload.stats,
  });
};

const closeTrendModal = () => {
  setTrendModal({
    open: false,
    technologyName: null,
    reports: [],
    pagination: null,
    stats: undefined,
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
                onOpenWeekly={() => setOpenWeeklyModal(true)} // 👈 CLAVE
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
      if (!item.trend_reports || item.trend_reports === 0) return;

    axios
  .get(
    `/dashboard/ranking/technologies/${item.id}/reports`,
    {
      params: {
        year: meta.year,
        period: meta.period,
        page: 1,
      },
    }
  )
  .then((res) => {
    if (!res.data?.data?.length) return;

    openTrendModal({
      technologyName: item.name,
      reports: res.data.data,
      pagination: res.data.pagination,
      stats: res.data.stats,
    });
  });

return;

       
    }

    /* ========= LABORAL ========= */
    if (action === "laboral") {
      if (!item.total_jobs || item.total_jobs === 0) return;

      openJobsModal("technology", item);
      return;
    }
  }}
/>

            </div>
          </div>
        </div>

        {/* ================= MODAL OFERTAS LABORALES ================= */}
        {/* ================= MODAL JOBS TECNOLOGÍA ================= */}
{jobsModal.open &&
  jobsModal.type === "technology" &&
  jobsModal.item && (
    <TechnologyJobsModal
      open
      onClose={closeJobsModal}
      technologyId={jobsModal.item.id}
      title={jobsModal.item.name}
    />
)}

{/* ================= MODAL JOBS TENDENCIA ================= */}
{jobsModal.open &&
  jobsModal.type === "trend" &&
  jobsModal.item && (
    <TrendJobsModal
      open
      onClose={closeJobsModal}
      trendId={jobsModal.item.id}
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
   {trendModal.open && (
<TrendDetailModal
  open
  technologyName={trendModal.technologyName}
  reports={trendModal.reports}

  // 👇 NUEVO
  stats={trendModal.stats}

  pagination={trendModal.pagination}
  onClose={closeTrendModal}
/>
)}
<WeeklyEvolutionModal
  open={openWeeklyModal}
  onClose={() => setOpenWeeklyModal(false)}
/>
  </>
);
}
