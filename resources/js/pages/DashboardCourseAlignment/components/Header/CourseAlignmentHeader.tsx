import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  BookOpen,
  TrendingUp,
  Database,
  Brain,
} from "lucide-react";
import { router, usePage } from "@inertiajs/react";

interface Props {
  meta: {
    year: number;
    vacantes_analizadas?: number;
    reportes_analizados?: number;
    actualizado?: string;
  };
  viewMode: "courses" | "competencies";
}

export default function CourseAlignmentHeader({
  meta,
  viewMode,
}: Props) {
  const pageProps = usePage().props as any;
  const filters = pageProps?.filters ?? {};

  const switchMode = (mode: "courses" | "competencies") => {
    router.get(
      "/dashboard/indicators/course-alignment",
      {
        ...filters,
        view: mode, // 🔥 backend espera "view"
      },
      {
        preserveScroll: true,
        preserveState: false, // 🔥 fuerza re-render correcto
        replace: true,
      }
    );
  };

  const isCourses = viewMode === "courses";
  const isCompetencies = viewMode === "competencies";

  return (
    <header
      className="
        relative overflow-hidden border
        bg-[#E6F7FD] dark:bg-[#0A2540]
        px-6 lg:px-10 py-10
        rounded-2xl shadow-lg
      "
    >
      {/* Background Blur */}
      <div className="absolute inset-0 pointer-events-none">
        <div className="absolute -left-24 -top-24 h-96 w-96 rounded-full bg-[#00B6E8]/30 blur-3xl" />
        <div className="absolute right-0 bottom-0 h-80 w-80 rounded-full bg-[#1CBCE8]/20 blur-3xl" />
      </div>

      <div className="relative z-10 max-w-7xl mx-auto">
        <div className="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-8">

          {/* ================= LEFT ================= */}
          <div className="space-y-6 max-w-3xl">

            {/* Title */}
            <div className="flex items-center gap-4">
              <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#00B6E8] shadow-md">
                {isCourses ? (
                  <BookOpen className="h-6 w-6 text-white" />
                ) : (
                  <Brain className="h-6 w-6 text-white" />
                )}
              </div>

              <div>
                <p className="text-xs font-semibold text-[#005F7A] dark:text-[#7DD3FC]">
                  Observatorio Tecnológico ISIL
                </p>

                <h1 className="text-2xl font-bold tracking-tight text-[#0A2540] dark:text-slate-100">
                  {isCourses
                    ? "Conexión Curso–Tendencia (CCTC)"
                    : "Alineación Estratégica por Competencia"}
                </h1>
              </div>
            </div>

            {/* Description */}
            <p className="text-sm leading-relaxed text-slate-700 dark:text-slate-300">
              {isCourses
                ? "Indicador estratégico que mide la alineación entre cursos académicos y señales reales del mercado laboral."
                : "Indicador que evalúa la correspondencia entre competencias formativas y las demandas reales del mercado."}
            </p>

            {/* 🔥 SWITCH CORREGIDO */}
            {/* <div className="flex gap-3 pt-2">
              <Button
                size="sm"
                onClick={() => switchMode("courses")}
                className={
                  isCourses
                    ? "bg-[#00B6E8] text-white hover:bg-[#0099c6] shadow"
                    : "bg-white text-[#0A2540] border border-slate-300 hover:bg-slate-100"
                }
              >
                Cursos
              </Button>

              <Button
                size="sm"
                onClick={() => switchMode("competencies")}
                className={
                  isCompetencies
                    ? "bg-[#00B6E8] text-white hover:bg-[#0099c6] shadow"
                    : "bg-white text-[#0A2540] border border-slate-300 hover:bg-slate-100"
                }
              >
                Competencias
              </Button>
            </div> */}

            {/* ================= BADGES ================= */}
            <div className="flex flex-wrap items-center gap-4 pt-4">

              <Badge className="bg-white text-[#0A2540] shadow gap-1.5 text-xs px-3 py-1">
                <Database className="h-3 w-3 text-[#00B6E8]" />
                {(meta?.vacantes_analizadas ?? 0).toLocaleString()} vacantes
              </Badge>

              <Badge className="bg-white text-[#0A2540] shadow gap-1.5 text-xs px-3 py-1">
                <TrendingUp className="h-3 w-3 text-[#00B6E8]" />
                {(meta?.reportes_analizados ?? 0).toLocaleString()} tendencias
              </Badge>

            </div>

           
          </div>

          {/* ================= RIGHT CARD ================= */}
          <div
            className="
              w-full max-w-sm rounded-2xl
              border border-[#00B6E8]/40
              bg-white dark:bg-[#102C3C]
              p-6 shadow-lg
            "
          >
            <p className="text-xs font-bold uppercase tracking-wider text-[#00B6E8] mb-3">
              Enfoque estratégico
            </p>

            <div className="space-y-2 text-sm text-[#0A2540] dark:text-gray-300">
              {isCourses ? (
                <>
                  <p>✔ Detecta brechas entre cursos y mercado</p>
                  <p>✔ Prioriza actualización curricular</p>
                  <p>✔ Integra datos laborales + tendencias</p>
                </>
              ) : (
                <>
                  <p>✔ Evalúa cobertura de competencias</p>
                  <p>✔ Detecta vacíos formativos</p>
                  <p>✔ Optimiza diseño curricular</p>
                </>
              )}
            </div>

            <p className="mt-4 text-xs text-[#0A2540]/70 dark:text-gray-400">
              Basado en señales reales consolidadas del observatorio.
            </p>
          </div>
        </div>
      </div>
    </header>
  );
}
