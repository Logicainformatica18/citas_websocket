import { useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head, usePage, router } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";

import { DashboardProvider } from "@/pages/dashboards/DashboardContext";

import { MacroTrendsHeader } from "./components/Header/MacroTrendsHeader";
import MacroTrendCard from "./components/Cards/MacroTrendCard";
import MacroTrendDetailModal from "./components/Detail/MacroTrendDetailModal";

/* =========================================================
   Breadcrumbs
========================================================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Macro-Tendencias", href: "/dashboard/macro-trends" },
];

type Trend = {
  id: number;
  trend_name: string;
  description: string;
  source_name?: string;
  source_title?: string;
  source_url?: string;
  source_type?: string;
  year: number;
  quarter: number;
  created_at: string;
};

type PageProps = {
  ranking: {
    data: Trend[];
    current_page: number;
    last_page: number;
    per_page: number;
  };
  meta: {
    year: number;
    period: "s1" | "s2";
    periodo_label: string;
    total_registros: number;
    actualizado: string;
  };
};

export default function MacroTrendsIndicatorPage() {
  const { ranking, meta } = usePage<PageProps>().props;

  /* =========================================================
     MODAL: Detalle Macro
  ========================================================= */
  const [selectedTrend, setSelectedTrend] = useState<Trend | null>(null);

  const openMacroDetail = (trend: Trend) => {
    setSelectedTrend(trend);
  };

  const closeMacroDetail = () => {
    setSelectedTrend(null);
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Macro-Tendencias | Observatorio ISIL" />

      <DashboardProvider>
        <div className="bg-background px-6 py-6">
          <div className="flex gap-6">
            <div className="flex-1 space-y-8">

              {/* ================= HEADER ================= */}
              <MacroTrendsHeader meta={meta} />

              {/* ================= CARDS ================= */}
              <div className="flex flex-col gap-6">

                {ranking.data.map((item) => (
                  <MacroTrendCard
                    key={item.id}
                    data={item}
                    onOpen={() => openMacroDetail(item)}
                  />
                ))}

              </div>

              {/* ================= PAGINACIÓN ================= */}
              {ranking.last_page > 1 && (
                <div className="flex justify-center items-center gap-2 mt-10">

                  {/* Prev */}
                  <button
                    disabled={ranking.current_page === 1}
                    onClick={() =>
                      router.get(
                        "/dashboard/macro-trends",
                        {
                          year: meta.year,
                          period: meta.period,
                          page: ranking.current_page - 1,
                        },
                        { preserveState: true }
                      )
                    }
                    className="px-3 py-1 rounded-lg border text-sm disabled:opacity-40"
                  >
                    ←
                  </button>

                  {/* Números */}
                  {[...Array(ranking.last_page)].map((_, i) => {
                    const page = i + 1;
                    const active = page === ranking.current_page;

                    return (
                      <button
                        key={page}
                        onClick={() =>
                         router.get(route("dashboard.indicators.macro-trends"), {
                              year: meta.year,
                              period: meta.period,
                              page,
                            },
                            { preserveState: true }
                          )
                        }
                        className={`px-3 py-1 rounded-lg text-sm ${
                          active
                            ? "bg-[#00B6E8] text-white"
                            : "border hover:bg-slate-100 dark:hover:bg-slate-800"
                        }`}
                      >
                        {page}
                      </button>
                    );
                  })}

                  {/* Next */}
                  <button
                    disabled={ranking.current_page === ranking.last_page}
                    onClick={() =>
                      router.get(
                        "/dashboard/macro-trends",
                        {
                          year: meta.year,
                          period: meta.period,
                          page: ranking.current_page + 1,
                        },
                        { preserveState: true }
                      )
                    }
                    className="px-3 py-1 rounded-lg border text-sm disabled:opacity-40"
                  >
                    →
                  </button>
                </div>
              )}

            </div>
          </div>
        </div>

        {/* ================= MODAL DETALLE ================= */}
        {selectedTrend && (
          <MacroTrendDetailModal
            open={true}
            trend={selectedTrend}
            onClose={closeMacroDetail}
          />
        )}
      </DashboardProvider>
    </AppLayout>
  );
}
