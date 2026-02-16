import { useEffect, useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head, usePage } from "@inertiajs/react";
import { DashboardProvider } from "@/pages/dashboards/DashboardContext";

import CourseAlignmentHeader from "./components/Header/CourseAlignmentHeader";
import CourseAlignmentFilter from "./components/Filters/CourseAlignmentFilter";
import CourseAlignmentKpis from "./components/KPIs/CourseAlignmentKpis";
import CourseBoard from "./components/Board/CourseBoard";
import CourseAlignmentChart from "./components/Charts/CourseAlignmentChart";

export default function CourseAlignmentIndicatorPage() {
  const {
    summary,
    meta,
    filters,
    availableCareers,
    courses: initialCourses,
  } = usePage<any>().props;

  const [courses, setCourses] = useState<any[]>(initialCourses ?? []);

  const hasCareer = Boolean(filters?.career_id);

  useEffect(() => {
    setCourses(initialCourses ?? []);
  }, [initialCourses]);

  return (
    <AppLayout
      breadcrumbs={[
        { title: "Dashboard", href: "/dashboard" },
        {
          title: "Alineación Curricular por Curso",
          href: "/dashboard/indicators/course-alignment",
        },
      ]}
    >
      <Head title="Alineación Curricular | Observatorio ISIL" />

      <DashboardProvider>
        <div className="bg-background px-6 py-6 space-y-8">

          {/* =========================
             HEADER
          ========================== */}
          <CourseAlignmentHeader meta={meta} />

          {/* =========================
             FILTROS
          ========================== */}
          <CourseAlignmentFilter
            careers={availableCareers}
            filters={filters}
          />

          {/* =========================
             KPIs
          ========================== */}
          {summary ? (
            <CourseAlignmentKpis summary={summary} />
          ) : (
            <div className="text-sm text-muted-foreground">
              Selecciona una carrera para analizar la alineación curricular.
            </div>
          )}

          {/* =========================
             VISUAL
          ========================== */}
          {hasCareer && courses.length > 0 && (
            <div className="space-y-8">

              {/* 📊 Distribución */}
              <CourseAlignmentChart courses={courses} />

              {/* 📘 Board estratégico */}
              <CourseBoard courses={courses} />

            </div>
          )}
        </div>
      </DashboardProvider>
    </AppLayout>
  );
}
