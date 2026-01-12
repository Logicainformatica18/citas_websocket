import { useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head, usePage, router } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";

import { DashboardProvider } from "@/pages/dashboards/DashboardContext";

import { Header as RankingHeader } from "./components/Header/RankingHeader";
import {
  WeightConfigModal,
  WeightConfig,
} from "./components/Header/WeightConfigModal";

import KpiGrid from "./components/KPIs/KpiGrid";
import RankingFilters from "./components/Filters/RankingFilters";
import TechnologyRankingList from "./components/Ranking/TechnologyRankingList";
import TechnologyJobsModal from "./components/Ranking/TechnologyJobsModal";

//import { useRankingData } from "./hooks/useRankingData";

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

/* =========================================================
   Types desde Backend (Inertia)
========================================================= */
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
  meta: any;
  weights: {
    laborWeight: number;
    trendsWeight: number;
  };
};

export default function RankingTechnologiesPage() {
  /* =========================
     DATOS DESDE BACKEND
  ========================= */
  const { ranking, kpis, meta, weights } = usePage<PageProps>().props;

  /* =========================
     NORMALIZACIÓN DATA
  ========================= */
  //const { data } = useRankingData(ranking.data);

  /* =========================
     UI STATE
  ========================= */
  const [isWeightModalOpen, setIsWeightModalOpen] = useState(false);

  /* =========================
     MODAL OFERTAS LABORALES
  ========================= */
  const [openJobsModal, setOpenJobsModal] = useState(false);
  const [selectedTechnology, setSelectedTechnology] = useState<any>(null);

  const handleOpenJobs = (technology: any) => {
    setSelectedTechnology(technology);
    setOpenJobsModal(true);
  };

  /* =========================
     GUARDAR PONDERACIONES
  ========================= */
  const handleSaveWeights = (newWeights: WeightConfig) => {
    if (newWeights.laborWeight + newWeights.trendsWeight !== 100) {
      Swal.fire("Error", "Las ponderaciones deben sumar 100%", "error");
      return;
    }

    Swal.fire({
      title: "Aplicando metodología",
      text: "Recalculando ranking de tecnologías…",
      allowOutsideClick: false,
      allowEscapeKey: false,
      didOpen: () => Swal.showLoading(),
    });

    router.post(
      "/dashboard/ranking/technologies/weights",
      {
        labor_weight: newWeights.laborWeight / 100,
        trend_weight: newWeights.trendsWeight / 100,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          Swal.fire({
            icon: "success",
            title: "Metodología actualizada",
            timer: 1500,
            showConfirmButton: false,
          });

          router.reload({
            only: ["ranking", "weights", "meta", "kpis"],
          });
        },
        onError: () => {
          Swal.fire(
            "Error",
            "No se pudo aplicar la ponderación",
            "error"
          );
        },
      }
    );
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Ranking de Tecnologías | Observatorio ISIL" />

      <DashboardProvider>
        {/* ===== CONTENEDOR GENERAL ===== */}
        <div className="bg-background px-6 py-6">
          <div className="flex gap-6 items-start">
            {/* ==============================
                COLUMNA PRINCIPAL
            ============================== */}
            <div className="flex-1 min-w-0 space-y-6">
              {/* ===== HEADER ===== */}
              <RankingHeader
                weights={weights}
                onEditWeights={() => setIsWeightModalOpen(true)}
                meta={meta}
              />

              {/* ===== KPIs ===== */}
              <KpiGrid items={kpis} />

              {/* ===== FILTROS ===== */}
              <RankingFilters />

              {/* ===== RANKING ===== */}
            <TechnologyRankingList
  items={ranking.data}
  pagination={ranking}
  onSelectTechnology={handleOpenJobs}
/>
            </div>
          </div>
        </div>

        {/* ===== MODAL OFERTAS LABORALES ===== */}
        <TechnologyJobsModal
          open={openJobsModal}
          onClose={() => setOpenJobsModal(false)}
          technologyId={selectedTechnology?.id}
          technologyName={selectedTechnology?.name}
        />

        {/* ===== MODAL DE PONDERACIONES ===== */}
        <WeightConfigModal
          open={isWeightModalOpen}
          onOpenChange={setIsWeightModalOpen}
          weights={weights}
          onSave={handleSaveWeights}
        />
      </DashboardProvider>
    </AppLayout>
  );
}
