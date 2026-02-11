import { useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head, usePage, router } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";
import axios from "axios";
import Swal from "sweetalert2";

import { DashboardProvider } from "@/pages/dashboards/DashboardContext";

import { MacroTrendsHeader } from "./components/Header/MacroTrendsHeader";
import {
  WeightConfigModal,
  WeightConfig,
} from "./components/Header/WeightConfigModal";

import MacroTrendCard from "./components/Cards/MacroTrendCard";
import MacroTrendDetailModal from "./components/Detail/MacroTrendDetailModal";

/* =========================================================
   Breadcrumbs
========================================================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  {
    title: "Macro-Tendencias",
    href: "/dashboard/macro-trends",
  },
];

type PageProps = {
  ranking: {
    data: any[];
    current_page: number;
    last_page: number;
    per_page: number;
  };
  meta: {
    year: number;
    period: "s1" | "s2";
    periodo_label: string;
    vacantes_analizadas: number;
    reportes_analizados: number;
  };
  weights: {
    laborWeight: number;
    trendsWeight: number;
  };
};

export default function MacroTrendsIndicatorPage() {
  const { ranking, meta, weights } =
    usePage<PageProps>().props;

  /* =========================================================
     MODAL: Detalle Macro
  ========================================================= */
  const [macroDetailModal, setMacroDetailModal] = useState({
    open: false,
    macroId: null as number | null,
  });

  /* =========================================================
     MODAL: Ponderaciones
  ========================================================= */
  const [isWeightModalOpen, setIsWeightModalOpen] =
    useState(false);

  /* =========================================================
     Handlers
  ========================================================= */

  const openMacroDetail = (id: number) => {
    setMacroDetailModal({
      open: true,
      macroId: id,
    });
  };

  const closeMacroDetail = () => {
    setMacroDetailModal({
      open: false,
      macroId: null,
    });
  };

  /* =========================================================
     Guardar ponderaciones
  ========================================================= */
  const handleSaveWeights = (newWeights: WeightConfig) => {
    if (
      newWeights.laborWeight + newWeights.trendsWeight !==
      100
    ) {
      Swal.fire(
        "Error",
        "Las ponderaciones deben sumar 100%",
        "error"
      );
      return;
    }

    Swal.fire({
      title: "Aplicando metodología",
      text: "Actualizando ranking…",
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading(),
    });

    router.post(
      route("macro-trends.weights"),
      {
        labor_weight: newWeights.laborWeight / 100,
        trend_weight: newWeights.trendsWeight / 100,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          Swal.fire({
            icon: "success",
            title: "Metodología actualizada",
            timer: 1400,
            showConfirmButton: false,
          });

          router.reload({
            only: ["ranking", "weights", "meta"],
          });
        },
      }
    );
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Macro-Tendencias | Observatorio ISIL" />

      <DashboardProvider>
        <div className="bg-background px-6 py-6">
          <div className="flex gap-6">
            <div className="flex-1 space-y-8">

              {/* ================= HEADER ================= */}
              <MacroTrendsHeader
                weights={weights}
                meta={meta}
                onEditWeights={() =>
                  setIsWeightModalOpen(true)
                }
              />

              {/* ================= DESCRIPCIÓN ================= */}
              <div className="pt-4 border-t border-slate-200 dark:border-slate-700">
                <h2 className="text-lg font-semibold">
                  Ranking General de Macro-Tendencias
                </h2>
                <p className="mt-1 text-sm text-slate-500 max-w-3xl">
                  Clasificación estratégica que integra impacto
                  laboral y relevancia internacional.
                </p>
              </div>

              {/* ================= CARDS ================= */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {ranking.data.map((item) => (
                  <MacroTrendCard
                    key={item.id}
                    data={item}
                    onClick={() =>
                      openMacroDetail(item.id)
                    }
                  />
                ))}
              </div>
            </div>
          </div>
        </div>

        {/* ================= MODAL DETALLE ================= */}
        {macroDetailModal.open && (
          <MacroTrendDetailModal
            macroId={macroDetailModal.macroId}
            open={macroDetailModal.open}
            onClose={closeMacroDetail}
          />
        )}

        {/* ================= MODAL PONDERACIONES ================= */}
        <WeightConfigModal
          open={isWeightModalOpen}
          onOpenChange={setIsWeightModalOpen}
          weights={weights}
          onSave={handleSaveWeights}
        />
      </DashboardProvider>
    </AppLayout>
  );
}
