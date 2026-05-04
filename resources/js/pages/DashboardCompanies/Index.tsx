import { useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head, usePage } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";

import { DashboardProvider } from "@/pages/dashboards/DashboardContext";

import { CompanyIndicatorHeader } from "./components/Header/CompanyIndicatorHeader";
import CompanyKpiGrid from "./components/KPIs/CompanyKpiGrid";
import CompanyFilters from "./components/Filters/CompanyFilters";
import CompanyRankingList from "./components/Ranking/CompanyRankingList";
import CompanyPagination from "./components/Pagination/CompanyPagination";
import { CompanyEvolutionModal } from "./components/Header/CompanyEvolutionModal";

/* =========================================================
   Breadcrumbs
========================================================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },

  {
    title: "Empresas",
    href: "/dashboard/indicators/companies",
  },
];

type PageProps = {
  ranking: {
    data: any[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: any[];
  };
  meta: {
    year: number;
    period: "s1" | "s2";
    periodo_label: string;
    vacantes_analizadas: number;
    empresas_activas: number;
  };
  filters: {
    year: number;
    period: "s1" | "s2";
    region?: string | null;
    country?: string | null;
    perPage: number;
  };
  regions: string[];
};

export default function CompanyIndicatorIndex() {
  const { ranking, meta, filters, regions } =
    usePage<PageProps>().props;

    const [selectedCompany, setSelectedCompany] = useState<string | null>(null)
const [selectedCountry, setSelectedCountry] = useState<string | null>(null)
const [open, setOpen] = useState(false)
const [selectedFilters, setSelectedFilters] = useState(filters)
const [openEvolutionModal, setOpenEvolutionModal] = useState(false);

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Indicador | Ranking de Empresas | Observatorio ISIL" />

      <DashboardProvider>
        <div className="bg-background px-6 py-6">
          <div className="flex gap-6">
            <div className="flex-1 space-y-6">

              {/* ================= HEADER ================= */}
              <CompanyIndicatorHeader
                meta={meta}
                filters={filters}
                regions={regions}
                  onOpenEvolution={() => setOpenEvolutionModal(true)} // 🔥 NUEVO
              />

              {/* ================= KPIs ================= */}
              <CompanyKpiGrid meta={meta} />

              {/* ================= DESCRIPCIÓN ================= */}
              <div className="pt-4 border-t border-slate-200 dark:border-slate-700">
                <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                  Ranking de Empresas por Vacantes Tecnológicas
                </h2>

                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-3xl">
                  Clasificación de empresas que concentran el mayor número de
                  vacantes tecnológicas publicadas en portales de empleo,
                  según el Periodo, región y país seleccionados.
                </p>
              </div>

              {/* ================= FILTROS ================= */}
              <CompanyFilters
                filters={filters}
                regions={regions}
              />

              {/* ================= RANKING ================= */}
           <CompanyRankingList
  ranking={ranking.data}
  pagination={ranking}
    filters={filters} // 🔥 ESTA LÍNEA FALTABA
  onSelect={(company: string, country: string) => {
  setSelectedCompany(company)
  setSelectedCountry(country)
  setSelectedFilters(filters) // 🔥 IMPORTANTE
  setOpen(true)
}}
/>

              {/* ================= PAGINACIÓN ================= */}
              <CompanyPagination paginator={ranking} />

            </div>

            {/* ================= METODOLOGÍA ================= */}
            {/* <div className="hidden xl:block w-[340px] shrink-0">
              <CompanyMethodologyCard />
            </div> */}

          </div>
        </div>
      </DashboardProvider>
      <CompanyEvolutionModal
  open={openEvolutionModal}
  onClose={setOpenEvolutionModal}
/>
    </AppLayout>
  );
}
