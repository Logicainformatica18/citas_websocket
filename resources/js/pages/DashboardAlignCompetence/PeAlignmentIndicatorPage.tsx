import { useEffect, useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head, usePage, router } from "@inertiajs/react";
import { DashboardProvider } from "@/pages/dashboards/DashboardContext";
import axios from "axios";

import CareerFilter from "./components/Filters/CareerFilter";
import PeAlignmentHeader from "./components/Header/PeAlignmentHeader";
import PeAlignmentKpis from "./components/KPIs/PeAlignmentKpis";
import CompetencyTable from "./components/Table/CompetencyTable";


import {
  WeightConfigModal,
  WeightConfig,
} from "./components/Header/WeightConfigModal";

import CompetencyCoursesModal from "./components/Modals/CompetencyCoursesModal";

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
 
console.log("SUMMARY DEBUG:", summary);
  /* =========================
     STATE
  ========================== */

  const [competencies, setCompetencies] = useState<any[]>(
    initialCompetencies ?? []
  );

  const [isWeightModalOpen, setIsWeightModalOpen] = useState(false);

  const [selectedCompetency, setSelectedCompetency] = useState<any>(null);
  const [courses, setCourses] = useState<any[]>([]);
  const [isCoursesModalOpen, setCoursesModalOpen] = useState(false);
  const [isCoursesLoading, setIsCoursesLoading] = useState(false);

  const hasCareer = Boolean(filters?.career_id);

  /* =========================
     SYNC BACKEND
  ========================== */
  useEffect(() => {
    setCompetencies(initialCompetencies ?? []);
  }, [initialCompetencies]);

  /* ======================================================
     ABRIR MODAL CURSOS
  ====================================================== */
  const openCompetencyModal = async (competency: any) => {
    setSelectedCompetency(competency);
    setCoursesModalOpen(true);
    setIsCoursesLoading(true);

    try {
      const res = await axios.get(
        `/dashboard/indicators/pe-alignment/competency/${competency.id}/courses`,
        {
        params: {
  career_id: filters.career_id,
  year: filters.year,
},
        }
      );

      setCourses(res.data.data ?? res.data);
    } catch (e) {
      setCourses([]);
    } finally {
      setIsCoursesLoading(false);
    }
  };

  /* ======================================================
     IA ANALYZE
  ====================================================== */
  const analyzeCompetency = async (competency: {
    id: number;
    name: string;
  }) => {
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
     SAVE WEIGHTS
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

          <PeAlignmentHeader
            meta={meta}
            weights={weights}
            onEditWeights={() => setIsWeightModalOpen(true)}
          />

         

        {summary && (
  <PeAlignmentKpis summary={summary} />
)}

 <CareerFilter careers={availableCareers} filters={filters} />
          {hasCareer && competencies.length > 0 && (
            <CompetencyTable
              competencies={competencies}
              onAnalyze={analyzeCompetency}
              onSelectCompetency={openCompetencyModal}
            />
          )}
        </div>

        {/* =========================
           MODAL CURSOS (Headless UI)
        ========================== */}
        <CompetencyCoursesModal
          open={isCoursesModalOpen}
          onClose={() => setCoursesModalOpen(false)}
          competencyName={selectedCompetency?.name ?? ""}
          courses={courses}
        />

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
