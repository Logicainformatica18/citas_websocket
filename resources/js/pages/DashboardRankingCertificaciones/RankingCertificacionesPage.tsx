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
  ranking: any[];
  kpis: any;
  meta: any;
};

export default function RankingCertificacionesPage() {
  /* =========================
     DATOS DESDE BACKEND (INERTIA)
  ========================= */
  const { ranking, kpis, meta } = usePage<PageProps>().props;

  /* =========================
     Normalización de datos
  ========================= */
  const { data } = useRankingData(ranking);

  /* =========================
     Header state
  ========================= */
  const [period, setPeriod] = useState<Period>("s1");
  const [weights, setWeights] = useState<WeightConfig>(defaultWeights);
  const [isWeightModalOpen, setIsWeightModalOpen] = useState(false);

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Ranking de Certificaciones | Observatorio ISIL" />

      <DashboardProvider>
        {/* ===== CONTENEDOR GENERAL ===== */}
        <div className="bg-background px-6 py-6">
          {/* ===== LAYOUT 2 COLUMNAS ===== */}
          <div className="flex gap-6 items-start">
            {/* ==============================
                COLUMNA IZQUIERDA (DASHBOARD)
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

              {/* ===== RANKING ===== */}
              <RankingList items={data} />
            </div>

            {/* 👉 Chat VERA vendrá luego */}
          </div>
        </div>

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
