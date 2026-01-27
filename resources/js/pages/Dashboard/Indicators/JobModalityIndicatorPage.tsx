import AppLayout from "@/layouts/app-layout";
import { Head, router, usePage } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";

import ModalityKpiGrid from "./components/KPIs/ModalityKpiGrid";
import ModalityDoughnutChart from "./components/Charts/ModalityDoughnutChart";
import ModalitySummaryTable from "./components/Table/ModalitySummaryTable";
import { JobModalityIndicatorHeader } from "./components/Header/JobModalityIndicatorHeader";
import ModalityTrendChart from "./components/Charts/ModalityTrendChart";
import { useModalityInsights } from "./components/hooks/useModalityInsights";

import GeographicFilters from "./components/Filters/GeographicFilters";
import FilterChip from "./components/Filters/FilterChip";

/* =========================================================
   Breadcrumbs
========================================================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Indicadores", href: "/dashboard/indicadores" },
  {
    title: "Modalidad laboral",
    href: "/dashboard/indicadores/modalidad-laboral",
  },
];

/* =========================================================
   Types
========================================================= */
type ModalityItem = {
  modalidad: string;
  vacantes: number;
  porcentaje: number;
};

type TrendItem = {
  month: string;
  remoto: number;
  hibrido: number;
  presencial: number;
};

type PageProps = {
  data: ModalityItem[];
  trendData: TrendItem[];
  filters: {
    region?: string | null;
    country?: string | null;
    city?: string | null;
    source?: string | null;
    year: number;
    period: "s1" | "s2";
  };
  meta: {
    year: number;
    period: "s1" | "s2";
    periodo_label: string;
    total_vacantes: number;
  };
};

export default function JobModalityIndicatorPage() {
  const { data, trendData, filters, meta } =
    usePage<PageProps>().props;

  const { insights } = useModalityInsights(data, trendData);

  /* =====================================================
     Navegación filtros (UNIFICADA)
  ===================================================== */
  const updateFilters = (newFilters: Partial<PageProps["filters"]>) => {
    router.get(
      "/dashboard/indicadores/modalidad-laboral",
      {
        ...filters,
        ...newFilters,
      },
      { preserveState: true, replace: true }
    );
  };

  const clearFilters = () => {
    router.get(
      "/dashboard/indicadores/modalidad-laboral",
      {
        year: meta.year,
        period: meta.period,
      },
      { replace: true }
    );
  };

  const removeFilter = (key: "region" | "country" | "city") => {
    const reset: any = { [key]: null };

    if (key === "region") {
      reset.country = null;
      reset.city = null;
    }

    if (key === "country") {
      reset.city = null;
    }

    updateFilters(reset);
  };

  /* =====================================================
     Render
  ===================================================== */
  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Indicador de Modalidad Laboral | Observatorio ISIL" />

      {/* HEADER */}
      <JobModalityIndicatorHeader meta={meta} filters={filters} />

      <div className="bg-background px-6 py-6 space-y-8">

        {/* ================= FILTROS ================= */}
        <GeographicFilters
          filters={filters}
          onChange={(f) => updateFilters(f)}
          onClear={clearFilters}
        />

        {/* ================= CHIPS ================= */}
        {(filters.region || filters.country || filters.city) && (
          <div className="flex flex-wrap gap-2">
            {filters.region && (
              <FilterChip
                label={`Región: ${filters.region}`}
                onRemove={() => removeFilter("region")}
              />
            )}
            {filters.country && (
              <FilterChip
                label={`País: ${filters.country}`}
                onRemove={() => removeFilter("country")}
              />
            )}
            {filters.city && (
              <FilterChip
                label={`Ciudad: ${filters.city}`}
                onRemove={() => removeFilter("city")}
              />
            )}
          </div>
        )}

        {/* ================= KPIs ================= */}
        <ModalityKpiGrid
          data={data}
          totalVacantes={meta.total_vacantes}
        />

        {/* ================= CHARTS ================= */}
        <ModalityDoughnutChart data={data} />
        <ModalityTrendChart data={trendData} />

        {/* ================= TABLE ================= */}
        <ModalitySummaryTable data={data} />

        {/* ================= INSIGHTS ================= */}
        <div className="grid gap-4 md:grid-cols-1">
          {insights.map(
            (i) =>
              i.visible && (
                <div
                  key={i.key}
                  className="rounded-xl border bg-[#ECFAFD] p-4"
                >
                  <p className="font-semibold text-[#0A2540]">
                    {i.title}
                  </p>
                  <p className="text-sm text-slate-700">
                    {i.text}
                  </p>
                </div>
              )
          )}
        </div>
      </div>
    </AppLayout>
  );
}
