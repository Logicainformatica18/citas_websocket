import { Globe2, Calendar, Database, TrendingUp, RefreshCcw } from "lucide-react";
import { router, usePage } from "@inertiajs/react";
import { useState } from "react";

interface HeaderProps {
  meta: {
    year: number;
    period: "s1" | "s2";
    periodo_label: string;
    total_semestre: number;
    total_year: number;
    total_historico: number;
    actualizado: string;
  };
}

export function MacroTrendsHeader({ meta }: HeaderProps) {
  const pageProps = usePage().props as any;
const filters = pageProps?.filters ?? {};
  const [loading, setLoading] = useState(false);

  const onChange = (params: { year?: number; period?: "s1" | "s2" }) => {
    router.get(
      route("dashboard.indicators.macro-trends"),
      {
        ...filters,
        year: params.year ?? meta.year,
        period: params.period ?? meta.period,
        page: 1,
      },
      { preserveState: true, replace: true }
    );
  };

  const handleRunDiscover = () => {
    setLoading(true);

    router.post(
      route("macro-trends.run"),
      {
        year: meta.year,
        period: meta.period,
      },
      {
        preserveScroll: true,
        onFinish: () => setLoading(false),
      }
    );
  };

return (
  <header className="relative border-b bg-gradient-to-br from-purple-50 to-purple-50 dark:from-[#0A2540] dark:to-[#081A2C] px-6 py-10">

    <div className="mx-auto max-w-7xl">

      {/* ================= HEADER TOP ================= */}
      <div className="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">

        {/* LEFT */}
        <div className="flex items-center gap-4">
          <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-purple-400 shadow-lg">
            <Globe2 className="h-6 w-6 text-white" />
          </div>

          <div>
            <p className="text-xs font-semibold uppercase tracking-widest text-purple-500 dark:text-purple-400">
              Observatorio Tecnológico ISIL
            </p>

            <h1 className="text-3xl font-bold text-[#0A2540] dark:text-white">
              Macro Tendencias Estratégicas
            </h1>
          </div>
        </div>

        {/* RIGHT CONTROLS */}
        <div className="flex flex-wrap items-center gap-4">

          {/* Año */}
          <select
            value={meta.year}
            onChange={(e) =>
              onChange({ year: Number(e.target.value) })
            }
            className="rounded-xl border bg-white dark:bg-slate-800 dark:border-slate-600 px-4 py-2 text-sm font-medium shadow-md"
          >
            {[2025, 2026].map((y) => (
              <option key={y} value={y}>
                {y}
              </option>
            ))}
          </select>

          {/* Semestre */}
          <div className="flex rounded-xl overflow-hidden border bg-white dark:bg-slate-800 dark:border-slate-600 shadow-md">
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
                  className={`px-5 py-2 text-sm font-semibold transition ${
                    active
                      ? "bg-purple-400 text-white"
                      : "text-purple-500 dark:text-slate-300 hover:bg-purple-50 dark:hover:bg-slate-700"
                  }`}
                >
                  {s.label}
                </button>
              );
            })}
          </div>

        </div>
      </div>

      {/* ================= KPI CARDS ================= */}
      <div className="mt-10 grid grid-cols-1 md:grid-cols-3 gap-6">

        {/* SEMESTRE */}
        <div className="rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-lg hover:shadow-xl transition">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-xs uppercase tracking-wide text-slate-500">
                Reportes semestre
              </p>
              <p className="text-3xl font-bold text-[#0A2540] dark:text-white mt-2">
                {meta.total_semestre.toLocaleString()}
              </p>
            </div>
            <Calendar className="h-8 w-8 text-purple-400" />
          </div>
        </div>

        {/* AÑO */}
        <div className="rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-lg hover:shadow-xl transition">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-xs uppercase tracking-wide text-slate-500">
                Reportes del año
              </p>
              <p className="text-3xl font-bold text-[#0A2540] dark:text-white mt-2">
                {meta.total_year.toLocaleString()}
              </p>
            </div>
            <TrendingUp className="h-8 w-8 text-purple-400" />
          </div>
        </div>

        {/* HISTORICO */}
        <div className="rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-lg hover:shadow-xl transition">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-xs uppercase tracking-wide text-slate-500">
                Histórico total
              </p>
              <p className="text-3xl font-bold text-[#0A2540] dark:text-white mt-2">
                {meta.total_historico.toLocaleString()}
              </p>
            </div>
            <Database className="h-8 w-8 text-emerald-500" />
          </div>
        </div>

      </div>

      {/* ================= FOOTER INFO ================= */}
      <div className="mt-8 flex flex-col gap-2 text-sm text-[#0A2540]/80 dark:text-slate-400 md:flex-row md:items-center md:justify-between">

        <div>
          <span className="font-semibold">Periodo activo:</span>{" "}
          {meta.periodo_label}
        </div>

        <div className="text-xs opacity-70">
          Última actualización: {meta.actualizado}
        </div>

      </div>

    </div>
  </header>
);
}
