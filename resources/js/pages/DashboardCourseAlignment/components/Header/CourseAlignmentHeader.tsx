import { Badge } from "@/components/ui/badge";
import {
  BookOpen,
  TrendingUp,
  Database,
  Sparkles,
} from "lucide-react";
import { router, usePage } from "@inertiajs/react";

interface Props {
  meta: {
    year: number;
    period: "s1" | "s2";
    periodo_label: string;
    vacantes_analizadas?: number;
    reportes_analizados?: number;
    actualizado?: string;
  };
}

export default function CourseAlignmentHeader({ meta }: Props) {
  const { filters } = usePage().props as any;

  const onChange = (params: { year?: number; period?: "s1" | "s2" }) => {
    router.get(
      "/dashboard/indicators/course-alignment",
      {
        ...filters,
        year: params.year ?? meta.year,
        period: params.period ?? meta.period,
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
        border-b
        bg-[#E6F7FD]
        dark:bg-[#0A2540]
        px-6
        lg:px-10
        py-10
        rounded-2xl
        shadow-xl
      "
    >
      {/* BACKGROUND */}
      <div className="absolute inset-0 pointer-events-none">
        <div className="absolute -left-24 -top-24 h-96 w-96 rounded-full bg-[#00B6E8]/30 blur-3xl" />
        <div className="absolute right-0 bottom-0 h-80 w-80 rounded-full bg-[#1CBCE8]/20 blur-3xl" />
      </div>

      <div className="relative z-10 max-w-7xl mx-auto">
        <div className="flex flex-col md:flex-row md:items-start md:justify-between gap-8">

          {/* ================= LEFT ================= */}
          <div className="space-y-6 max-w-3xl">

            {/* Title */}
            <div className="flex items-center gap-4">
              <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#00B6E8] shadow-lg">
                <BookOpen className="h-6 w-6 text-white" />
              </div>

              <div>
                <p className="text-sm font-semibold text-[#005F7A] dark:text-[#7DD3FC]">
                  Observatorio Tecnológico ISIL
                </p>

                <h1 className="text-3xl font-extrabold tracking-tight text-[#0A2540] dark:text-slate-100">
                  Conexión Curso–Tendencia (CCTC)
                </h1>
              </div>
            </div>

            {/* Description */}
            <p className="text-[15px] leading-relaxed text-slate-700 dark:text-slate-300">
              Indicador estratégico de actualización curricular que mide la
              alineación entre cursos académicos y tendencias tecnológicas
              del mercado laboral.
            </p>

            {/* ================= CONTROLES ================= */}
            <div className="flex flex-wrap items-end gap-8">

              {/* Año */}
              <div className="flex flex-col gap-2">
                <span className="text-xs font-semibold text-[#005F7A] dark:text-slate-300">
                  Año
                </span>

                <div className="relative rounded-xl border bg-white shadow-sm hover:border-[#00B6E8]">
                  <select
                    value={meta.year}
                    onChange={(e) =>
                      onChange({ year: Number(e.target.value) })
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
              </div>

              {/* Semestre */}
              <div className="flex flex-col gap-2">
                <span className="text-xs font-semibold text-[#005F7A] dark:text-slate-300">
                  Semestre
                </span>

                <div className="flex rounded-xl border bg-white overflow-hidden">
                  {[
                    { value: "s1", label: "Ene – Jun" },
                    { value: "s2", label: "Jul – Dic" },
                  ].map((s) => {
                    const active = meta.period === s.value;

                    return (
                      <button
                        key={s.value}
                        onClick={() =>
                          onChange({ period: s.value as "s1" | "s2" })
                        }
                        className={`
                          px-6 py-2 text-sm font-semibold transition-all
                          ${
                            active
                              ? "bg-[#00B6E8] text-white"
                              : "text-[#005F7A] hover:bg-[#E6F7FD]"
                          }
                        `}
                      >
                        {s.label}
                      </button>
                    );
                  })}
                </div>
              </div>
            </div>

            {/* ================= BADGES ================= */}
            <div className="flex flex-wrap items-center gap-4">

              {/* <Badge className="bg-white text-[#0A2540] shadow gap-1.5">
                <Sparkles className="h-3 w-3 text-[#00B6E8]" />
                {meta?.periodo_label}
              </Badge> */}

              <Badge className="bg-white text-[#0A2540] shadow gap-1.5">
                <Database className="h-3 w-3 text-[#00B6E8]" />
                {(meta?.vacantes_analizadas ?? 0).toLocaleString()} vacantes
              </Badge>

              <Badge className="bg-white text-[#0A2540] shadow gap-1.5">
                <TrendingUp className="h-3 w-3 text-[#00B6E8]" />
                {(meta?.reportes_analizados ?? 0).toLocaleString()} tendencias
              </Badge>
            </div>

            {/* Updated */}
            {/* <p className="pt-4 text-sm text-[#0A2540]/70 dark:text-gray-400">
              <b>Actualizado:</b> {meta?.actualizado ?? "-"}
            </p> */}
          </div>

          {/* ================= RIGHT CARD ================= */}
          <div
            className="
              w-full max-w-sm rounded-2xl
              border border-[#00B6E8]/40
              bg-white dark:bg-[#102C3C]
              p-6 shadow-xl
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
              El análisis se basa únicamente en datos reales del período seleccionado.
            </p>
          </div>
        </div>
      </div>
    </header>
  );
}
