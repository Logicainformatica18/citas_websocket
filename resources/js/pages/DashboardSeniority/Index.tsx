import AppLayout from "@/layouts/app-layout";
import { Head, usePage } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";

import { SeniorityHeader } from "./components/Header/SeniorityHeader";
import { SeniorityKpiGrid } from "./components/KPIs/SeniorityKpiGrid";
import { SeniorityBarChart } from "./components/Charts/SeniorityBarChart";
import { SeniorityModalityPieChart } from "./components/Charts/SeniorityModalityPieChart";

import { useSeniorityData } from "./components/hooks/useSeniorityData";
import { useSeniorityModalityData } from "./components/hooks/useSeniorityModalityData";

/* =====================================================
   Breadcrumbs
===================================================== */
const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Indicadores", href: "/dashboard/indicators" },
  { title: "Distribución de Seniority", href: "/dashboard/indicators/seniority" },
];

/* =====================================================
   Types
===================================================== */
interface PageProps {
  meta: {
    year: number;
    period: "s1" | "s2";
    periodo_label: string;
    vacantes_analizadas: number;
  };
}

/* =====================================================
   Page
===================================================== */
export default function SeniorityIndicatorPage() {
  const { meta } = usePage<PageProps>().props;

  const { data, loading } = useSeniorityData();
  const { data: modalityData } = useSeniorityModalityData();

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Distribución de Seniority" />

      <div className="flex flex-col gap-6">
        {/* HEADER */}
        <SeniorityHeader meta={meta} />

        {/* CONTENT */}
        {loading ? (
          <div className="px-6 py-4 text-sm text-slate-500">
            Cargando indicador…
          </div>
        ) : (
          <>
            {/* KPIs */}
            <div className="px-6">
              <SeniorityKpiGrid data={data} />
            </div>

            {/* Charts */}
            <div className="px-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
              <SeniorityBarChart data={data} />
              <SeniorityModalityPieChart data={modalityData} />
            </div>
          </>
        )}
      </div>
    </AppLayout>
  );
}
