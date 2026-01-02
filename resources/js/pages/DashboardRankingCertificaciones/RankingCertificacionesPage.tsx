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
import Pagination from "./components/Pagination";

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
  ranking: any[];
  kpis: any;
  meta: any;
};

const ITEMS_PER_PAGE = 8;

export default function RankingCertificacionesPage() {
  /* =========================
     DATOS DESDE BACKEND
  ========================= */
  const { ranking, kpis, meta } = usePage<PageProps>().props;

  /* =========================
     NORMALIZACIÓN
  ========================= */
  const { data } = useRankingData(ranking);

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

  /* =========================
     PAGINACIÓN
  ========================= */
  const [page, setPage] = useState(1);

  const totalPages = Math.ceil(data.length / ITEMS_PER_PAGE);

  const paginatedData = data.slice(
    (page - 1) * ITEMS_PER_PAGE,
    page * ITEMS_PER_PAGE
  );

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

              {/* ===== RANKING (GRID 2 COLS) ===== */}
              <RankingList
                items={paginatedData}
                onSelectCertification={handleOpenJobs}
                startRank={(page - 1) * ITEMS_PER_PAGE}
              />

              {/* ===== PAGINACIÓN ===== */}
              <Pagination
                page={page}
                totalPages={totalPages}
                onChange={setPage}
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
