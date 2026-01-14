import { useEffect, useState } from "react";
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
import RankingList from "./components/Ranking/RankingList";
import CertificationJobsModal from "./components/Ranking/CertificationJobsModal";

import TrendingCertificationCard from "./components/Ranking/TrendingCertificationCard";
import { TrendingCertification } from "./types/trending-certification";

import { useRankingData } from "./hooks/useRankingData";

import axios from "axios";
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

  const { data } = useRankingData(ranking.data);

  const [isWeightModalOpen, setIsWeightModalOpen] = useState(false);

  /* =========================
     MODAL OFERTAS
  ========================= */
  const [openJobsModal, setOpenJobsModal] = useState(false);
  const [selectedCert, setSelectedCert] = useState<any>(null);

  const handleOpenJobs = (cert: any) => {
    setSelectedCert(cert);
    setOpenJobsModal(true);
  };

  /* =========================
     CERTIFICACIONES EN TENDENCIA
  ========================= */
  const [trending, setTrending] = useState<TrendingCertification[]>([]);
  const [loadingTrending, setLoadingTrending] = useState(true);
  const [hasTrendingData, setHasTrendingData] = useState(true);

  useEffect(() => {
    setLoadingTrending(true);

    axios
      .get("/dashboard/ranking-certificaciones/trending", {
        params: {
          year: meta.year,
          period: meta.period,
        },
      })
      .then(res => {
        setTrending(res.data.items ?? []);
        setHasTrendingData(!res.data.empty);
      })
      .finally(() => setLoadingTrending(false));
  }, [
    meta.year,
    meta.period,
    weights.laborWeight,
    weights.trendsWeight,
  ]);

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
      text: "Recalculando ranking global…",
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
                onEditWeights={() => setIsWeightModalOpen(true)}
                meta={meta}
              />

              {/* ================= KPIs ================= */}
              <KpiGrid items={kpis} />

              {/* ================= CERTIFICACIONES EN TENDENCIA ================= */}
              <section className="space-y-4">
                <div className="space-y-1">
                  <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                    Certificaciones en Tendencia
                  </h2>

                  <p className="text-sm text-slate-500 dark:text-slate-400 max-w-3xl">
                    Resumen de certificaciones con mayor proyección,
                    calculado a partir de la demanda laboral y tendencias
                    tecnológicas globales.
                  </p>
                </div>

                {loadingTrending ? (
                  <div className="text-sm text-gray-500">
                    Cargando tendencias…
                  </div>
                ) : !hasTrendingData ? (
                  <div className="
                    rounded-lg border border-dashed
                    border-slate-300 dark:border-slate-600
                    p-4 text-sm text-slate-500
                  ">
                    No existen datos suficientes para mostrar certificaciones
                    en tendencia en el período seleccionado.
                  </div>
                ) : (
                  <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {trending.map(cert => (
                      <TrendingCertificationCard
                        key={cert.id}
                        data={cert}
                      />
                    ))}
                  </div>
                )}
              </section>

              {/* ================= DIVISOR SEMÁNTICO ================= */}
              <div className="pt-6 border-t border-slate-200 dark:border-slate-700">
                <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                  Ranking de Certificaciones
                </h2>

                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-3xl">
                  Clasificación detallada de certificaciones según demanda
                  laboral, tendencias tecnológicas y ponderaciones configuradas.
                </p>
              </div>

              {/* ================= FILTROS ================= */}
              <RankingFilters />

              {/* ================= RANKING PRINCIPAL ================= */}
              <RankingList
                items={data}
                pagination={ranking}
                onSelectCertification={handleOpenJobs}
              />
            </div>
          </div>
        </div>

        {/* ================= MODAL OFERTAS ================= */}
        <CertificationJobsModal
          open={openJobsModal}
          onClose={() => setOpenJobsModal(false)}
          certificationId={selectedCert?.id}
          certificationName={selectedCert?.name}
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
  );
}
