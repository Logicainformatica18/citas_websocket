import { useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head, usePage } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";

import { DashboardProvider } from "@/pages/dashboards/DashboardContext";

import { Header as RankingHeader } from "./components/Header/RankingHeader";
import {
  WeightConfigModal,
  defaultWeights,
  WeightConfig,
} from "./components/Header/WeightConfigModal";
import { Period } from "./components/Header/PeriodSelector";

import KpiGrid from "./components/KPIs/KpiGrid";
import RankingFilters from "./components/Filters/RankingFilters";
import RankingList from "./components/Ranking/RankingList";
import CertificationJobsModal from "./components/Ranking/CertificationJobsModal";

import { useRankingData } from "./hooks/useRankingData";

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
  meta: any;
};

export default function RankingCertificacionesPage() {
  /* =========================
     DATOS DESDE BACKEND (Inertia)
  ========================= */
  const { ranking, kpis, meta } = usePage<PageProps>().props;

  /* =========================
     NORMALIZACIÓN (solo data)
  ========================= */
  const { data } = useRankingData(ranking.data);

  /* =========================
     HEADER STATE
  ========================= */
  const [period, setPeriod] = useState<Period>("s1");
  const [weights, setWeights] = useState<WeightConfig>(defaultWeights);
  const [isWeightModalOpen, setIsWeightModalOpen] = useState(false);

  /* =========================
     MODAL OFERTAS LABORALES
  ========================= */
  const [openJobsModal, setOpenJobsModal] = useState(false);
  const [selectedCert, setSelectedCert] = useState<any>(null);

  const handleOpenJobs = (cert: any) => {
    setSelectedCert(cert);
    setOpenJobsModal(true);
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Ranking de Certificaciones | Observatorio ISIL" />

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
                period={period}
                onPeriodChange={setPeriod}
                weights={weights}
                onEditWeights={() => setIsWeightModalOpen(true)}
                meta={meta}
              />

              {/* ===== KPIs ===== */}
              <KpiGrid items={kpis} />

              {/* ===== FILTROS ===== */}
              <RankingFilters />

              {/* ===== RANKING (PAGINADO BACKEND) ===== */}
              <RankingList
                items={data}
                pagination={ranking}
                onSelectCertification={handleOpenJobs}
              />
            </div>
          </div>
        </div>

        {/* ===== MODAL OFERTAS LABORALES ===== */}
        <CertificationJobsModal
          open={openJobsModal}
          onClose={() => setOpenJobsModal(false)}
          certificationId={selectedCert?.id}
          certificationName={selectedCert?.name}
        />

        {/* ===== MODAL DE PONDERACIONES ===== */}
        <WeightConfigModal
          open={isWeightModalOpen}
          onOpenChange={setIsWeightModalOpen}
          weights={weights}
          onSave={setWeights}
        />
      </DashboardProvider>
    </AppLayout>
  );
}
