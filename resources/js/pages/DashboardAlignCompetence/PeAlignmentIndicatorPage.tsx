import { useEffect, useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head, usePage, router } from "@inertiajs/react";
import { DashboardProvider } from "@/pages/dashboards/DashboardContext";
import axios from "axios";

import CareerFilter from "./components/Filters/CareerFilter";
import PeAlignmentHeader from "./components/Header/PeAlignmentHeader";
import PeAlignmentKpis from "./components/KPIs/PeAlignmentKpis";
import CompetencyAlignmentChart from "./components/Charts/CompetencyAlignmentChart";
import CompetencyBoard from "./components/Board/CompetencyBoard";

import {
  WeightConfigModal,
  WeightConfig,
} from "./components/Header/WeightConfigModal";

import Swal from "sweetalert2";

/* ======================================================
   PAGE
====================================================== */
export default function PeAlignmentIndicatorPage() {
  const {
    summary,
    meta,
    filters,
    weights,
    availableCareers,
    competencies: initialCompetencies,
  } = usePage<any>().props;

  const [competencies, setCompetencies] = useState<any[]>(
    initialCompetencies ?? []
  );

  const [isWeightModalOpen, setIsWeightModalOpen] = useState(false);

  const hasCareer = Boolean(filters?.career_id);

  /* ======================================================
     SYNC COMPETENCIAS DESDE BACKEND
  ====================================================== */
  useEffect(() => {
    setCompetencies(initialCompetencies ?? []);
  }, [initialCompetencies]);

  /* ======================================================
     IA – ANALIZAR / REANALIZAR COMPETENCIA
  ====================================================== */
  const analyzeCompetency = async (competency: {
    id: number;
    name: string;
  }) => {
    // 🔥 marcar loading en ese card
    setCompetencies((prev) =>
      prev.map((c) =>
        c.id === competency.id
          ? { ...c, analysis: { status: "loading" } }
          : c
      )
    );

    try {
      const res = await axios.post(
        `/dashboard/indicators/pe-alignment/competency/${competency.id}/analyze`,
        {
          career_id: filters.career_id,
          year: filters.year,
          period: filters.period,
        }
      );

      const analysis = res.data.analysis;

      setCompetencies((prev) =>
        prev.map((c) =>
          c.id === competency.id
            ? {
                ...c,
                alignment_recommendation: analysis.recommendation,
                alignment_confidence: analysis.confidence,
                alignment_checked_at: analysis.updated_at,
                analysis: { status: "ready" },
              }
            : c
        )
      );
    } catch (e) {
      setCompetencies((prev) =>
        prev.map((c) =>
          c.id === competency.id
            ? { ...c, analysis: { status: "error" } }
            : c
        )
      );
    }
  };

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

          router.reload({ only: ["summary", "weights", "competencies"] });
        },
      }
    );
  };

  return (
    <AppLayout
      breadcrumbs={[
        { title: "Dashboard", href: "/dashboard" },
        {
          title: "Alineación Perfil de Egreso",
          href: "/dashboard/indicators/pe-alignment",
        },
      ]}
    >
      <Head title="Alineación del Perfil de Egreso | Observatorio ISIL" />

      <DashboardProvider>
        <div className="bg-background px-6 py-6 space-y-8">
          {/* =========================
             HEADER
          ========================== */}
          <PeAlignmentHeader
            meta={meta}
            weights={weights}
            onEditWeights={() => setIsWeightModalOpen(true)}
          />

          {/* =========================
             FILTRO
          ========================== */}
          <CareerFilter careers={availableCareers} filters={filters} />

          {/* =========================
             KPIs
          ========================== */}
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

          {/* =========================
             VISUALIZACIÓN
          ========================== */}
          {hasCareer && competencies.length > 0 && (
            <div className="space-y-8">
              {/* 📊 Gráfico general */}
 <CompetencyAlignmentChart competencies={competencies} />

              {/* 🧠 Tablero estratégico */}
              <CompetencyBoard
                competencies={competencies}
                onAnalyze={analyzeCompetency}
              />
               
            </div>
          )}
        </div>

        {/* =========================
           MODAL PESOS
        ========================== */}
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
