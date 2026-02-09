import { useEffect, useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head, usePage, router } from "@inertiajs/react";
import { DashboardProvider } from "@/pages/dashboards/DashboardContext";
import axios from "axios";

import CareerFilter from "./components/Filters/CareerFilter";
import PeAlignmentHeader from "./components/Header/PeAlignmentHeader";
import PeAlignmentKpis from "./components/KPIs/PeAlignmentKpis";

import CompetencyCard from "./components/Cards/CompetencyCard";

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
  const {
    summary,
    meta,
    filters,
    weights,
    availableCareers,
  } = usePage<any>().props;

  const [competencies, setCompetencies] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);

  const [jobsModal, setJobsModal] = useState<any>(null);
  const [trendsModal, setTrendsModal] = useState<any>(null);
  const [isWeightModalOpen, setIsWeightModalOpen] = useState(false);

  const hasCareer = Boolean(filters?.career_id);

  /* ======================================================
     FETCH COMPETENCIAS
  ====================================================== */
  useEffect(() => {
    if (!filters?.career_id) {
      setCompetencies([]);
      return;
    }

    setLoading(true);

    axios
      .get(
        `/dashboard/indicators/pe-alignment/competencies/${filters.career_id}`,
        { params: filters }
      )
      .then((res) => {
        setCompetencies(res.data.data ?? []);
      })
      .catch(() => setCompetencies([]))
      .finally(() => setLoading(false));
  }, [filters?.career_id, filters?.year, filters?.period]);

  /* ======================================================
     GUARDAR PESOS
  ====================================================== */
  const handleSaveWeights = (newWeights: WeightConfig) => {
    if (newWeights.laborWeight + newWeights.trendsWeight !== 100) {
      Swal.fire("Error", "Las ponderaciones deben sumar 100%", "error");
      return;
    }

    Swal.fire({
      title: "Actualizando metodología",
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
            timer: 1200,
            showConfirmButton: false,
          });

          router.reload({ only: ["summary", "weights"] });
        },
      }
    );
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Alineación del Perfil de Egreso | Observatorio ISIL" />

      <DashboardProvider>
        <div className="bg-background px-6 py-6 space-y-6">

          {/* HEADER */}
          <PeAlignmentHeader
            meta={meta}
            weights={weights}
            onEditWeights={() => setIsWeightModalOpen(true)}
          />

          {/* FILTRO */}
          <CareerFilter careers={availableCareers} filters={filters} />

          {/* KPIs */}
          {summary ? (
            <PeAlignmentKpis
              market={summary.market}
              prospective={summary.prospective}
              finalIndex={summary.final_index}
            />
          ) : (
            <div className="text-sm text-muted-foreground">
              Selecciona una carrera para analizar la alineación.
            </div>
          )}

          {/* GRID DE COMPETENCIAS */}
          {hasCareer && (
            <div className="space-y-4">
              {loading && (
                <div className="text-sm text-muted-foreground">
                  Cargando competencias…
                </div>
              )}

              {!loading && competencies.length === 0 && (
                <div className="text-sm text-muted-foreground">
                  No se encontraron competencias.
                </div>
              )}

              <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                {competencies.map((c) => (
                  <CompetencyCard
                    key={c.id}
                    competency={c}
                    onViewJobs={() => {
                      axios
                        .get(
                          `/dashboard/indicators/pe-alignment/competency/${c.id}/jobs`,
                          { params: filters }
                        )
                        .then((res) =>
                          setJobsModal({
                            competency: c,
                            jobs: res.data.data,
                          })
                        );
                    }}
                    onViewTrends={() => {
                      axios
                        .get(
                          `/dashboard/indicators/pe-alignment/competency/${c.id}/trends`,
                          { params: filters }
                        )
                        .then((res) =>
                          setTrendsModal({
                            competency: c,
                            trends: res.data.data,
                          })
                        );
                    }}
                  />
                ))}
              </div>
            </div>
          )}
        </div>

        {/* MODALS */}
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
