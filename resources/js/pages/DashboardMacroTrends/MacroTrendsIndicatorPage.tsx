import AppLayout from "@/layouts/app-layout";
import { Head } from "@inertiajs/react";
import { BreadcrumbItem } from "@/types";

import { MacroTrendsHeader } from "./components/Header/MacroTrendsHeader";
import MacroTrendsKpiGrid from "./components/KPIs/MacroTrendsKpiGrid";
import MacroTrendsFilters from "./components/Filters/MacroTrendsFilters";
import MacroTrendsRankingTable from "./components/Ranking/MacroTrendsRankingTable";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Indicadores", href: "/dashboard/indicators" },
  { title: "Macro-Tendencias", href: "/dashboard/indicators/macro-trends" },
];

interface Props {
  ranking: {
    data: any[];
    total: number;
    links: any[];
  };
  meta: any;
  regions: string[];
  careers: {
    id: number;
    name: string;
    slug: string;
  }[];
}

export default function MacroTrendsIndicatorPage({
  ranking,
  meta,
  regions,
  careers,
}: Props) {
  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Indicador de Macro-Tendencias | Observatorio ISIL" />

      <div className="px-6 py-6 space-y-6">

        {/* ================= HEADER ================= */}
        <MacroTrendsHeader meta={meta} />

        {/* ================= FILTROS ================= */}
        <MacroTrendsFilters
          regions={regions}
          careers={careers}
        />

        {/* ================= KPIs ================= */}
        {/* <MacroTrendsKpiGrid
          meta={meta}
          total={ranking?.total ?? 0}
        /> */}

        {/* ================= RANKING ================= */}
        <MacroTrendsRankingTable
          data={ranking.data}
        />

      </div>
    </AppLayout>
  );
}
