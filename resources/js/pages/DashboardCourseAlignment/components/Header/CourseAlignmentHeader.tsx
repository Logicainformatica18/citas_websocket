import { Badge } from "@/components/ui/badge";
import {
  BookOpen,
  TrendingUp,
  Database,
} from "lucide-react";
import { router, usePage } from "@inertiajs/react";

interface Props {
  meta: {
    year: number;
    vacantes_analizadas?: number;
    reportes_analizados?: number;
    actualizado?: string;
  };
}

export default function CourseAlignmentHeader({ meta }: Props) {
  const { filters } = usePage().props as any;

  const onChangeYear = (year: number) => {
    router.get(
      "/dashboard/indicators/course-alignment",
      {
        ...filters,
        year,
        page: 1,
      },
      {
        preserveState: true,
        replace: true,
      }
    );
  };

  return (
    <header
      className="
        relative
        overflow-hidden
        border
        bg-[#E6F7FD]
        dark:bg-[#0A2540]
        px-6
        lg:px-10
        py-10
        rounded-2xl
        shadow-lg
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
                <BookOpen className="h-6 w-6 text-white" />
              </div>

              <div>
                <p className="text-xs font-semibold text-[#005F7A] dark:text-[#7DD3FC]">
                  Observatorio Tecnológico ISIL
                </p>

                <h1 className="text-2xl font-bold tracking-tight text-[#0A2540] dark:text-slate-100">
                  Conexión Curso–Tendencia (CCTC)
                </h1>
              </div>
            </div>

            {/* Description */}
            <p className="text-sm leading-relaxed text-slate-700 dark:text-slate-300">
              Indicador estratégico que mide la alineación entre cursos académicos
              y señales reales del mercado laboral (demanda + tendencias).
            </p>

            {/* Año */}
            <div className="flex items-end gap-6">

              {/* <div className="flex flex-col gap-2">
                <span className="text-xs font-semibold text-[#005F7A] dark:text-slate-300">
                  Año
                </span>

                <div className="relative rounded-xl border bg-white shadow-sm hover:border-[#00B6E8]">
                  <select
                    value={meta.year}
                    onChange={(e) =>
                      onChangeYear(Number(e.target.value))
                    }
                    className="
                      w-[120px]
                      appearance-none
                      bg-transparent
                      px-4 py-2
                      text-sm font-semibold
                      text-[#0A2540]
                      focus:outline-none
                    "
                  >
                    {[2025, 2026].map((y) => (
                      <option key={y} value={y}>
                        {y}
                      </option>
                    ))}
                  </select>

                  <span className="absolute right-3 top-1/2 -translate-y-1/2 text-[#00B6E8]">
                    ⌄
                  </span>
                </div>
              </div> */}

            </div>

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

            {/* Updated */}
            {meta?.actualizado && (
              <p className="text-xs text-[#0A2540]/70 dark:text-gray-400 pt-2">
                Actualizado: {meta.actualizado}
              </p>
            )}
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
              <p>✔ Detecta brechas entre cursos y mercado</p>
              <p>✔ Prioriza actualización curricular</p>
              <p>✔ Integra datos laborales + tendencias</p>
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
