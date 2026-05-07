import { useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head, usePage, router } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";
import axios from "axios";

import { DashboardProvider } from "@/pages/dashboards/DashboardContext";

import { Header as RankingHeader } from "./components/Header/RankingHeader";
import {
  WeightConfigModal,
  WeightConfig,
} from "./components/Header/WeightConfigModal";

import KpiGrid from "./components/KPIs/KpiGrid";
import RankingFilters from "./components/Filters/RankingFilters";
import RankingList from "./components/Ranking/RankingList";

import CertificationJobsModal from "./components/Ranking/CertificationJobsModal";
import CertificationTrendModal from "./components/Ranking/CertificationTrendModal";
import TrendDetailModal from "./components/Ranking/TrendDetailModal";
import { WeeklyEvolutionCertificationsModal } from "./components/Header/WeeklyEvolutionCertificationsModal";
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

  /* =========================================================
     MODAL: Jobs por certificación
  ========================================================= */
  const [certJobsModal, setCertJobsModal] = useState({
    open: false,
    certification: null as any | null,
    jobs: [] as any[],
    pagination: null as any | null,
  });

  /* =========================================================
     MODAL: Tendencias por certificación
  ========================================================= */
const [certTrendsModal, setCertTrendsModal] = useState({
  open: false,

  certification: null as any | null,

  trends: [] as any[],

  pagination: null as any | null,

  stats: undefined as
    | {
        tavily_total: number;
        gpt_total: number;
      }
    | undefined,
});

  /* =========================================================
     MODAL: Detalle de tendencia
  ========================================================= */
  const [trendDetailModal, setTrendDetailModal] = useState({
    open: false,
    trend: null as any | null,
  });

  /* =========================================================
     MODAL: Ponderaciones
  ========================================================= */
  const [isWeightModalOpen, setIsWeightModalOpen] = useState(false);
const [openEvolutionModal, setOpenEvolutionModal] = useState(false);
  /* =========================================================
     Handlers
  ========================================================= */

  const loadCertificationTrends = (
  certification: any,
  page = 1
) => {

  axios
    .get(
      `/dashboard/ranking-certificaciones/${certification.id}/reports`,
      {
        params: {
          year: meta.year,
          period: meta.period,
          page,
        },
      }
    )

    .then((res) => {

      setCertTrendsModal({

        open: true,

        certification,

        trends:
          res.data?.data ?? [],

        pagination:
          res.data?.pagination ?? null,

        stats:
          res.data?.stats,
      });
    });
};

  const loadCertificationJobs = (certification: any) => {
    axios
      .get(`/dashboard/ranking-certificaciones/${certification.id}/jobs`)
      .then((res) => {
        const paginator = res.data.data;
        setCertJobsModal({
          open: true,
          certification,
          jobs: paginator.data,
          pagination: paginator,
        });
      });
  };

  const openTrendDetail = (trend: any) => {
    setTrendDetailModal({
      open: true,
      trend,
    });
  };

  const closeTrendDetail = () => {
    setTrendDetailModal({
      open: false,
      trend: null,
    });
  };

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
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Ranking de Certificaciones | Observatorio ISIL" />

      <DashboardProvider>
        <div className="bg-background px-6 py-6">
          <div className="flex gap-6">
            <div className="flex-1 space-y-6">

              {/* ================= HEADER ================= */}
              <RankingHeader
                weights={weights}
                meta={meta}
                onEditWeights={() => setIsWeightModalOpen(true)}
                onOpenWeekly={() => setOpenEvolutionModal(true)} // 🔥 ESTA LÍNEA ES LA CLAVE
              />

              {/* ================= KPIs ================= */}
              <KpiGrid items={kpis} />

              {/* ================= DESCRIPCIÓN ================= */}
              <div className="pt-4 border-t border-slate-200 dark:border-slate-700">
                <h2 className="text-lg font-semibold">
                  Ranking General de Certificaciones
                </h2>
                <p className="mt-1 text-sm text-slate-500 max-w-3xl">
                  Clasificación unificada que integra certificaciones ISIL
                  y certificaciones en tendencia global.
                </p>
              </div>

              {/* ================= FILTROS ================= */}
              <RankingFilters />

              {/* ================= RANKING ================= */}
              <RankingList
                items={ranking.data}
                pagination={ranking}
                onSelectItem={(action, item) => {
                  if (action === "trend") {
                    if (item.entity_type === "certification") {
                      loadCertificationTrends(item);
                    }
                    if (item.entity_type === "trend") {
                      openTrendDetail(item);
                    }
                  }

                  if (action === "laboral") {
                    if (item.entity_type === "certification") {
                      loadCertificationJobs(item);
                    }
                  }
                }}
              />
            </div>
          </div>
        </div>

        {/* ================= MODAL JOBS ================= */}
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
            onPageChange={(paginator) =>
              setCertJobsModal((prev) => ({
                ...prev,
                jobs: paginator.data,
                pagination: paginator,
              }))
            }
          />
        )}

        {/* ================= MODAL TRENDS POR CERT ================= */}
        {certTrendsModal.open && (
  <CertificationTrendModal
    open={certTrendsModal.open}

    certification={
      certTrendsModal.certification
    }

    trends={
      certTrendsModal.trends
    }

    pagination={
      certTrendsModal.pagination
    }

    stats={
      certTrendsModal.stats
    }

    onPageChange={(page) =>
      loadCertificationTrends(
        certTrendsModal.certification,
        page
      )
    }

    onClose={() =>
      setCertTrendsModal({

        open: false,

        certification: null,

        trends: [],

        pagination: null,

        stats: undefined,
      })
    }
  />
)}

        {/* ================= MODAL DETALLE TENDENCIA ================= */}
        {trendDetailModal.open && (
          <TrendDetailModal
            open={trendDetailModal.open}
            trend={trendDetailModal.trend}
            onClose={closeTrendDetail}
          />
        )}

        {/* ================= MODAL PONDERACIONES ================= */}
        <WeightConfigModal
          open={isWeightModalOpen}
          onOpenChange={setIsWeightModalOpen}
          weights={weights}
          onSave={handleSaveWeights}
        />
        {/* ================= MODAL EVOLUCIÓN ================= */}
<WeeklyEvolutionCertificationsModal
  open={openEvolutionModal}
  onClose={() => setOpenEvolutionModal(false)} // 🔥 más seguro
/>
      </DashboardProvider>
    </AppLayout>
  );
}
