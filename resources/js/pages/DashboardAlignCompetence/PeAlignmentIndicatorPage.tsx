import { useEffect, useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head, usePage, router } from "@inertiajs/react";
import { DashboardProvider } from "@/pages/dashboards/DashboardContext";
import axios from "axios";

import CareerFilter from "./components/Filters/CareerFilter";
import PeAlignmentHeader from "./components/Header/PeAlignmentHeader";
import PeAlignmentKpis from "./components/KPIs/PeAlignmentKpis";
import CompetencyAlignmentTable from "./components/Table/CompetencyAlignmentTable";

import CompetencyJobsModal from "./components/Modals/CompetencyJobsModal";
import CompetencyTrendsModal from "./components/Modals/CompetencyTrendsModal";

import {
  WeightConfigModal,
  WeightConfig,
} from "./components/Header/WeightConfigModal";

import Swal from "sweetalert2";
import { type BreadcrumbItem } from "@/types";

/* ======================================================
   Breadcrumbs
====================================================== */
const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  {
    title: "Alineación Perfil de Egreso",
    href: "/dashboard/indicators/pe-alignment",
  },
];

export default function PeAlignmentIndicatorPage() {
  /* ======================================================
     PROPS DESDE INERTIA
  ====================================================== */
  const {
    summary,
    meta,
    filters,
    weights,
    availableCareers,
  } = usePage<any>().props;

  /* ======================================================
     STATE
  ====================================================== */
  const [competencies, setCompetencies] = useState<any[]>([]);
  const [loadingCompetencies, setLoadingCompetencies] = useState(false);

  const [jobsModal, setJobsModal] = useState<any>(null);
  const [trendsModal, setTrendsModal] = useState<any>(null);

  const [isWeightModalOpen, setIsWeightModalOpen] = useState(false);

  const hasCareer = Boolean(filters?.career_id);

  /* ======================================================
     CARGAR COMPETENCIAS POR CARRERA
  ====================================================== */
  useEffect(() => {
    if (!filters?.career_id) {
      setCompetencies([]);
      return;
    }

    setLoadingCompetencies(true);

    axios
      .get(
        `/dashboard/indicators/pe-alignment/competencies/${filters.career_id}`,
        { params: filters }
      )
      .then((res) => {
        setCompetencies(res.data.data ?? []);
      })
      .catch(() => {
        setCompetencies([]);
      })
      .finally(() => {
        setLoadingCompetencies(false);
      });
  }, [filters?.career_id, filters?.year, filters?.period]);

  /* ======================================================
     GUARDAR PONDERACIONES
  ====================================================== */
  const handleSaveWeights = (newWeights: WeightConfig) => {
    if (newWeights.laborWeight + newWeights.trendsWeight !== 100) {
      Swal.fire("Error", "Las ponderaciones deben sumar 100%", "error");
      return;
    }

    Swal.fire({
      title: "Aplicando metodología",
      text: "Actualizando indicador…",
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading(),
    });

    router.post(
      "/dashboard/indicators/pe-alignment/weights",
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
            only: ["summary", "weights"],
          });
        },
      }
    );
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Alineación del Perfil de Egreso | Observatorio ISIL" />

      <DashboardProvider>
        <div className="bg-background px-6 py-6 space-y-6">

          {/* ================= HEADER ================= */}
          <PeAlignmentHeader
            meta={meta}
            weights={weights}
            onEditWeights={() => setIsWeightModalOpen(true)}
          />

          {/* ================= FILTRO CARRERA ================= */}
          <CareerFilter careers={availableCareers} filters={filters} />

          {/* ================= KPIs ================= */}
          {summary ? (
            <PeAlignmentKpis
              market={summary.market}
              prospective={summary.prospective}
              finalIndex={summary.final_index}
            />
          ) : (
            <div className="text-sm text-slate-500">
              Selecciona una carrera para ver el indicador.
            </div>
          )}

          {/* ================= TABLA COMPETENCIAS ================= */}
          {hasCareer && (
            <CompetencyAlignmentTable
              loading={loadingCompetencies}
              competencies={competencies.map((c) => ({
                id: c.competency_id,
                name: c.competency_name,
                market: c.market_match,
                prospective: c.trend_match,
                score: c.pe_score,
              }))}
              onViewJobs={(c) => {
                axios
                  .get(
                    `/dashboard/indicators/pe-alignment/competency/${c.id}/jobs`,
                    { params: filters }
                  )
                  .then((res) => {
                    setJobsModal({
                      competency: c,
                      jobs: res.data.data,
                    });
                  });
              }}
              onViewTrends={(c) => {
                axios
                  .get(
                    `/dashboard/indicators/pe-alignment/competency/${c.id}/trends`,
                    { params: filters }
                  )
                  .then((res) => {
                    setTrendsModal({
                      competency: c,
                      trends: res.data.data,
                    });
                  });
              }}
            />
          )}
        </div>

        {/* ================= MODALS ================= */}
        {jobsModal && (
          <CompetencyJobsModal
            competency={jobsModal.competency}
            jobs={jobsModal.jobs}
            onClose={() => setJobsModal(null)}
          />
        )}

        {trendsModal && (
          <CompetencyTrendsModal
            competency={trendsModal.competency}
            trends={trendsModal.trends}
            onClose={() => setTrendsModal(null)}
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
  );
}
