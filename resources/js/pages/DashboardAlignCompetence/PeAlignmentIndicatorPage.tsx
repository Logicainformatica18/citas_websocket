import { useEffect, useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head, usePage, router } from "@inertiajs/react";
import { DashboardProvider } from "@/pages/dashboards/DashboardContext";
import axios from "axios";

import CareerFilter from "./components/Filters/CareerFilter";
import PeAlignmentHeader from "./components/Header/PeAlignmentHeader";
import PeAlignmentKpis from "./components/KPIs/PeAlignmentKpis";
import CompetencyTable from "./components/Table/CompetencyTable";
// import AlignmentExplanationDrawer from "./components/Drawer/AlignmentExplanationDrawer";
import AlignmentExplanationBlock from "./AlignmentExplanationBlock";
import CompetencyAIDrawer from "./components/Drawer/CompetencyAIDrawer";
import {
    WeightConfigModal,
    WeightConfig,
} from "./components/Header/WeightConfigModal";

import CompetencyCoursesModal from "./components/Modals/CompetencyCoursesModal";

import Swal from "sweetalert2";

export default function PeAlignmentIndicatorPage() {

    const {
        summary,
        meta,
        filters,
        weights,
        availableCareers,
        competencies: initialCompetencies,
        career, // 🔥 IMPORTANTE: debe venir desde backend
    } = usePage<any>().props;

    /* =========================
       STATE
    ========================== */
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [explanationOpen, setExplanationOpen] = useState(false);

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
       MODAL CURSOS
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
        } catch {
            setCourses([]);
        } finally {
            setIsCoursesLoading(false);
        }
    };

    /* ======================================================
       IA POR COMPETENCIA
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

  /* 🔵 Loader bonito */
  Swal.fire({
    title: "Analizando competencia",
    text: "VERA IA está generando la recomendación...",
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  try {

    const res = await axios.post(
      `/dashboard/indicators/pe-alignment/competency/${competency.id}/analyze`,
      {
        career_id: filters.career_id,
        year: filters.year,
        period: filters.period,
      }
    );

  const analysis = res.data?.analysis ?? {};

setCompetencies((prev) => {

  const updated = prev.map((c) =>
    c.id === competency.id
      ? {
          ...c,
          analysis: {
            status: "ready",
            diagnosis: analysis?.diagnosis ?? null,
            recommendation: analysis?.recommendation ?? null,
          },
        }
      : c
  );

  const updatedCompetency = updated.find(
    c => c.id === competency.id
  );

  setSelectedCompetency(updatedCompetency); // 🔥 abre / refresca drawer

  return updated;
});

    /* ✅ Éxito */
    Swal.fire({
      icon: "success",
      title: "Análisis generado",
      text: "La recomendación estratégica fue creada correctamente.",
      timer: 1600,
      showConfirmButton: false,
    });

  } catch (error) {

    setCompetencies((prev) =>
      prev.map((c) =>
        c.id === competency.id
          ? { ...c, analysis: { status: "error" } }
          : c
      )
    );

    Swal.fire({
      icon: "error",
      title: "Error en análisis",
      text: "No se pudo generar la recomendación IA.",
    });

  }
};
/* =========================
   REFRESH DRAWER DATA
========================= */

useEffect(() => {

  if (!selectedCompetency) return;

  const updated = competencies.find(
    c => c.id === selectedCompetency.id
  );

  if (updated) {
    setSelectedCompetency(updated);
  }

}, [competencies]);
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

                    router.reload({
                        only: ["summary", "weights", "competencies"],
                    });
                },
            }
        );
    };

    /* ======================================================
       RENDER
    ====================================================== */

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

                    {/* HEADER */}
                    <PeAlignmentHeader
                        meta={meta}
                        weights={weights}
                        onEditWeights={() => setIsWeightModalOpen(true)}
                    />

                    {/* DRAWER METODOLOGÍA + IA */}
                    {/* <AlignmentExplanationDrawer
                        open={explanationOpen}
                        onClose={() => setExplanationOpen(false)}
                        recommendation={career?.strategic_recommendation ?? null}
                        recommendationYear={career?.recommendation_year ?? null}
                        recommendationDate={career?.recommendation_generated_at ?? null}
                    /> */}

                    {/* FILTRO CARRERA */}
                    <CareerFilter />

                    {/* BLOQUE EXPLICACIÓN + IA PREVIEW */}
                   

                    {/* KPIS */}
                    {summary && (
                        <PeAlignmentKpis summary={summary} />
                    )}
 
                    {/* TABLA */}
                    {hasCareer && competencies.length > 0 && (
                        <CompetencyTable
                            competencies={competencies}
                            onAnalyze={analyzeCompetency}
                      onSelectCompetency={(comp) => {
  const current = competencies.find(c => c.id === comp.id);
  setSelectedCompetency(current);
}}
                        />
                    )}
                    <AlignmentExplanationBlock
                        onOpenDrawer={() => setExplanationOpen(true)}
                        recommendation={career?.strategic_recommendation ?? null}
                    />
                </div>

                {/* MODAL CURSOS */}
                <CompetencyCoursesModal
                    open={isCoursesModalOpen}
                    onClose={() => setCoursesModalOpen(false)}
                    competencyName={selectedCompetency?.name ?? ""}
                    courses={courses}
                />

                {/* MODAL PESOS */}
                <WeightConfigModal
                    open={isWeightModalOpen}
                    onOpenChange={setIsWeightModalOpen}
                    weights={weights}
                    onSave={handleSaveWeights}
                />
<CompetencyAIDrawer
  competency={selectedCompetency}
  onClose={() => setSelectedCompetency(null)}
/>
            </DashboardProvider>
        </AppLayout>
    );
}
