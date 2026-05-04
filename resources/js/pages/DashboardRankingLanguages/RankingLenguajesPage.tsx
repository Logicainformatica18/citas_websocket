import { useEffect, useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head, usePage, router } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";
import axios from "axios";
import { WeeklyEvolutionLanguagesModal } from "./components/Header/WeeklyEvolutionLanguagesModal";
import { DashboardProvider } from "@/pages/dashboards/DashboardContext";

import LanguageJobsModal from "./components/Ranking/LanguageJobsModal";
import LanguageTrendModal from "./components/Ranking/LanguageTrendModal";

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

  /* =========================================================
     MODAL LABORAL
  ========================================================= */
  const [jobsModal, setJobsModal] = useState<{
    open: boolean;
    language: any | null;
  }>({ open: false, language: null });

  /* =========================================================
     MODAL TENDENCIAS (ENTITY_TRENDS)
  ========================================================= */
  const [trendModal, setTrendModal] = useState<{
    open: boolean;
    language: any | null;
    trends: any[];
    pagination: any | null;
    page: number;
  }>({
    open: false,
    language: null,
    trends: [],
    pagination: null,
    page: 1,
  });

  /* =========================
     ABRIR MODAL TENDENCIAS
  ========================= */
  const openTrendModal = async (language: any, page = 1) => {
    try {
      const res = await axios.get(
        `/dashboard/ranking/languages/${language.id}/trends`,
        { params: { page } }
      );

      setTrendModal({
        open: true,
        language,
        trends: res.data?.data ?? [],
        pagination: res.data?.pagination ?? null,
        page,
      });
    } catch (e) {
      console.error("❌ Error cargando tendencias", e);
    }
  };

  const closeTrendModal = () => {
    setTrendModal({
      open: false,
      language: null,
      trends: [],
      pagination: null,
      page: 1,
    });
  };
const [openEvolutionModal, setOpenEvolutionModal] = useState(false);
  /* =========================================================
     MODAL PONDERACIONES
  ========================================================= */
  const [isWeightModalOpen, setIsWeightModalOpen] = useState(false);

  const handleSaveWeights = (newWeights: WeightConfig) => {
    if (newWeights.laborWeight + newWeights.trendWeight !== 100) {
      Swal.fire("Error", "Las ponderaciones deben sumar 100%", "error");
      return;
    }

    Swal.fire({
      title: "Aplicando metodología",
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
            <div className="space-y-6">

              <RankingHeader
                weights={weights}
                onEditWeights={() => setIsWeightModalOpen(true)}
                meta={meta}
                  onOpenWeekly={() => setOpenEvolutionModal(true)} // 🔥 CLAVE
              />

              <KpiGrid items={kpis} />

              <RankingFilters />

              <RankingList
                items={ranking.data}
                pagination={ranking}
                onSelectItem={(action, item) => {

                  console.log("🔥 CLICK:", action, item);

                  /* ---------- TENDENCIAS ---------- */
                  if (action === "trend") {
                    if (Number(item.trend_reports ?? 0) === 0) return;
                    openTrendModal(item, 1);
                    return;
                  }

                  /* ---------- LABORAL ---------- */
                  if (action === "laboral") {
                    if (Number(item.total_jobs ?? 0) === 0) return;
                    setJobsModal({ open: true, language: item });
                    return;
                  }
                }}
              />
            </div>
          </div>

          {/* ================= MODAL LABORAL ================= */}
          {jobsModal.open && jobsModal.language && (
            <LanguageJobsModal
              open={jobsModal.open}
              onClose={() =>
                setJobsModal({ open: false, language: null })
              }
              languageId={jobsModal.language.id}
              languageName={jobsModal.language.name}
            />
          )}

          {/* ================= MODAL TENDENCIAS ================= */}
          {trendModal.open && trendModal.language && (
            <LanguageTrendModal
              open={trendModal.open}
              language={trendModal.language}
              trends={trendModal.trends}
              pagination={trendModal.pagination}
              onClose={closeTrendModal}
              onPageChange={(page) =>
                openTrendModal(trendModal.language, page)
              }
            />
          )}
{/* ================= MODAL EVOLUCIÓN ================= */}
<WeeklyEvolutionLanguagesModal
  open={openEvolutionModal}
  onClose={setOpenEvolutionModal}
/>
          {/* ================= MODAL PONDERACIONES ================= */}
          <WeightConfigModal
            open={isWeightModalOpen}
            onOpenChange={setIsWeightModalOpen}
            weights={weights}
            onSave={handleSaveWeights}
          />
          
        </DashboardProvider>
        
      </AppLayout>
      
    </>
  );
}
