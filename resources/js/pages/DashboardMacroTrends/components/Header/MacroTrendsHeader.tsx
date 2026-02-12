import { Badge } from "@/components/ui/badge";
import { Globe2, Sparkles } from "lucide-react";
import { router, usePage } from "@inertiajs/react";

interface HeaderProps {
  meta: {
    year: number;
    period: "s1" | "s2";
    periodo_label: string;
    total_registros: number;
    actualizado: string;
  };
}

export function MacroTrendsHeader({ meta }: HeaderProps) {
  const { filters } = usePage().props as any;

  const onChange = (params: { year?: number; period?: "s1" | "s2" }) => {
    router.get(
      "/dashboard/macro-trends",
      {
        ...filters,
        year: params.year ?? meta.year,
        period: params.period ?? meta.period,
        page: 1,
      },
      { preserveState: true, replace: true }
    );
  };

  return (
    <header className="relative border-b bg-[#E6F7FD] dark:bg-[#0A2540] px-6 py-8">

      <div className="mx-auto max-w-7xl">

        {/* ===== TOP ROW ===== */}
        <div className="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">

          {/* LEFT: Title */}
          <div className="flex items-center gap-4">
            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-[#00B6E8] shadow-md">
              <Globe2 className="h-5 w-5 text-white" />
            </div>

            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-[#005F7A] dark:text-[#7DD3FC]">
                Observatorio Tecnológico ISIL
              </p>
              <h1 className="text-2xl font-bold text-[#0A2540] dark:text-white">
                Macro Tendencias Estratégicas
              </h1>
            </div>
          </div>

          {/* RIGHT: Filters inline */}
          <div className="flex flex-wrap items-center gap-4">

            {/* Año */}
            <select
              value={meta.year}
              onChange={(e) =>
                onChange({ year: Number(e.target.value) })
              }
              className="rounded-lg border bg-white dark:bg-slate-800 dark:border-slate-600 px-3 py-2 text-sm font-medium shadow-sm"
            >
              {[2025, 2026].map((y) => (
                <option key={y} value={y}>
                  {y}
                </option>
              ))}
            </select>

            {/* Semestre */}
            <div className="flex rounded-lg overflow-hidden border bg-white dark:bg-slate-800 dark:border-slate-600">
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
                    className={`px-4 py-2 text-sm font-medium transition ${
                      active
                        ? "bg-[#00B6E8] text-white"
                        : "text-[#005F7A] dark:text-slate-300 hover:bg-[#D9F3FB] dark:hover:bg-slate-700"
                    }`}
                  >
                    {s.label}
                  </button>
                );
              })}
            </div>

            {/* Badge total */}
            <Badge className="flex items-center gap-1 bg-white dark:bg-slate-800 text-[#0A2540] dark:text-white shadow-sm">
              <Sparkles className="h-3 w-3 text-[#00B6E8]" />
              {(meta.total_registros ?? 0).toLocaleString()} tendencias
            </Badge>
          </div>
        </div>

        {/* ===== SECOND ROW ===== */}
        <div className="mt-6 flex flex-col gap-1 text-sm text-[#0A2540]/80 dark:text-slate-400 md:flex-row md:items-center md:justify-between">
          <div>
            <span className="font-medium">Periodo activo:</span>{" "}
            {meta.periodo_label}
          </div>

          <div className="text-xs opacity-80">
            Última actualización: {meta.actualizado}
          </div>
        </div>

      </div>
    </header>
  );
}
