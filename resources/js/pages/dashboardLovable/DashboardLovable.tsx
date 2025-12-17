import { useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";

// 👉 CONTEXTO (OBLIGATORIO)
import { DashboardProvider } from "@/pages/dashboards/DashboardContext";

// 👉 Wrappers Lovable
import DashboardHeader from "./components/DashboardHeader";
import DashboardSection from "./components/DashboardSection";
import WidgetCard from "./components/WidgetCard";

// 👉 Chat VERA REAL
import AiChatView from "@/pages/dashboards/components/AiChat/AiChatView";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Dashboard Lovable", href: "/dashboard/lovable" },
];

export default function DashboardLovable() {
  // 🔑 Estado del chat (Lovable-style)
  const [isChatOpen, setIsChatOpen] = useState(true);

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Dashboard Lovable" />

      {/* ✅ TODO el dashboard vive dentro del Provider */}
      <DashboardProvider>
        <div className="min-h-screen bg-[#F7FBFD] dark:bg-gray-950 p-6">

          {/* ===============================
              HEADER LOVABLE
          =============================== */}
          <DashboardHeader
            isChatOpen={isChatOpen}
            onToggleChat={() => setIsChatOpen((v) => !v)}
          />

          {/* ===============================
              GRID PRINCIPAL (Lovable style)
          =============================== */}
 {/* ===============================
    LAYOUT PRINCIPAL
=============================== */}
<div className="mt-6 flex gap-6">




            {/* =================================================
                COLUMNA IZQUIERDA → DASHBOARD
            ================================================= */}
         <div className="flex-1 space-y-10 transition-all duration-300">
              {/* ---------- HERO ---------- */}
              <DashboardSection>
                <WidgetCard
                  span="col-span-12"
                  title="Top 20 Lenguajes Más Demandados"
                  subtitle="Basado en ofertas laborales 2024"
                  insight="SQL lidera con un crecimiento del 12% respecto al trimestre anterior. Python muestra la mayor tasa de crecimiento interanual (+18%)."
                >
                  <div
                    className="
                      h-[420px]
                      flex items-center justify-center
                      text-gray-400 dark:text-gray-500
                      border border-dashed border-[#A7E5F6]
                      rounded-lg
                    "
                  >
                    Aquí irá el gráfico principal
                  </div>
                </WidgetCard>
              </DashboardSection>

              {/* ---------- TENDENCIAS ---------- */}
              <DashboardSection title="Tendencias de Demanda">
                <WidgetCard
                  span="col-span-6"
                  title="Tendencia de Demanda Tecnológica"
                  subtitle="Evolución temporal"
                >
                  <div
                    className="
                      h-56
                      flex items-center justify-center
                      text-gray-400 dark:text-gray-500
                      border border-dashed border-[#A7E5F6]
                      rounded-lg
                    "
                  >
                    Gráfico de tendencia
                  </div>
                </WidgetCard>

                <WidgetCard
                  span="col-span-6"
                  title="Distribución por Área"
                  subtitle="Sectores tecnológicos"
                >
                  <div
                    className="
                      h-56
                      flex items-center justify-center
                      text-gray-400 dark:text-gray-500
                      border border-dashed border-[#A7E5F6]
                      rounded-lg
                    "
                  >
                    Distribución por área
                  </div>
                </WidgetCard>
              </DashboardSection>
            </div>

            {/* =================================================
                COLUMNA DERECHA → CHAT VERA
            ================================================= */}
            {isChatOpen && (
  <div
    className="
      w-[380px]        /* ⬅️ ancho real tipo Lovable */
      shrink-0
      transition-all duration-300
    "
  >
    <div
      className="
        sticky top-6
        h-[calc(100vh-120px)]
        bg-[#ECFAFD] dark:bg-gray-900
        border border-[#A7E5F6] dark:border-gray-700
        rounded-xl
        overflow-hidden
      "
    >
      <AiChatView docked />
    </div>
  </div>
            )}
          </div>
        </div>
      </DashboardProvider>
    </AppLayout>
  );
}
