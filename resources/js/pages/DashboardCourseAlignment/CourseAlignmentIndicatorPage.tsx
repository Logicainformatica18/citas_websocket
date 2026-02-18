import { useEffect, useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head, usePage } from "@inertiajs/react";
import { DashboardProvider } from "@/pages/dashboards/DashboardContext";

import CourseAlignmentHeader from "./components/Header/CourseAlignmentHeader";
import CourseAlignmentFilter from "./components/Filters/CourseAlignmentFilter";
import CourseAlignmentTable from "./components/Table/CourseAlignmentTable";
import CompetenciesAlignmentTable from "./components/Table/CompetenciesAlignmentTable";
import CourseAlignmentKPI from "./components/KPIs/CourseAlignmentKPI";

// 🔥 Drawer nuevo
import CourseDetailDrawer from "./components/Drawer/CourseDetailDrawer";

export default function CourseAlignmentIndicatorPage() {
  const {
  meta,
  filters,
  availableCareers,
  viewMode = "courses",
  data: initialData,
  final_index,
  market_rate,
  trend_rate,
  gap_total,
  aligned_count,
  total_courses,
} = usePage<any>().props;


  const [data, setData] = useState<any[]>(initialData ?? []);

  // 🔥 Nuevo estado para Drawer
  const [selectedCourse, setSelectedCourse] = useState<any | null>(null);

  const hasCareer = Boolean(filters?.career_id);

  useEffect(() => {
    setData(initialData ?? []);
  }, [initialData]);

  const isCourses = viewMode === "courses";
  const isCompetencies = viewMode === "competencies";

  return (
    <AppLayout
      breadcrumbs={[
        { title: "Dashboard", href: "/dashboard" },
        {
          title: "Alineación Curricular",
          href: "/dashboard/indicators/course-alignment",
        },
      ]}
    >
      <Head title="Alineación Curricular | Observatorio ISIL" />

      <DashboardProvider>
        <div className="bg-background px-6 py-6 space-y-8">

          {/* ================= HEADER ================= */}
          <CourseAlignmentHeader meta={meta} viewMode={viewMode} />

          {/* ================= FILTROS ================= */}
           <CourseAlignmentFilter
            careers={availableCareers}
            filters={filters}
            viewMode={viewMode}
          />
<CourseAlignmentKPI
  final_index={final_index ?? 0}
  market_rate={market_rate ?? 0}
  trend_rate={trend_rate ?? 0}
  gap_total={gap_total ?? 0}
  aligned_count={aligned_count ?? 0}
  total_courses={total_courses ?? 0}
/>


         

          {/* ================= CONTENIDO ================= */}

          {!hasCareer && (
            <div className="text-sm text-muted-foreground border rounded-xl p-6 bg-muted/20">
              Selecciona una carrera para analizar la alineación curricular.
            </div>
          )}

          {hasCareer && data.length === 0 && (
            <div className="text-sm text-muted-foreground border rounded-xl p-6 bg-muted/20">
              {isCourses
                ? "No se encontraron cursos asociados a esta carrera."
                : "No se encontraron competencias asociadas a esta carrera."}
            </div>
          )}

          {hasCareer && data.length > 0 && (
            <>
              {isCourses && (
                <CourseAlignmentTable
                  courses={data}
                  onSelectCourse={(course) => setSelectedCourse(course)}
                />
              )}

              {isCompetencies && (
                <CompetenciesAlignmentTable competencies={data} />
              )}
            </>
          )}
        </div>

        {/* 🔥 DRAWER LATERAL */}
        {selectedCourse && (
          <CourseDetailDrawer
            course={selectedCourse}
            onClose={() => setSelectedCourse(null)}
          />
        )}
      </DashboardProvider>
    </AppLayout>
  );
}
