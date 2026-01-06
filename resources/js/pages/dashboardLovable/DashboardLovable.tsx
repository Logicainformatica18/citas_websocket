import { useState, useRef } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";

import { DashboardProvider } from "@/pages/dashboards/DashboardContext";

import DashboardHeader from "./components/DashboardHeader";
import DashboardLovableWidgets from "./components/DashboardLovableWidgets";
import AiChatView from "@/pages/dashboards/components/AiChat/AiChatView";

/* =========================================================
   Breadcrumbs
========================================================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Dashboard Lovable", href: "/dashboard_vera" },
];

/* =========================================================
   Page
========================================================= */
export default function DashboardLovable() {
  const [isChatOpen] = useState(true);
  const widgetsRef = useRef<{ addSection: () => void }>(null);

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Dashboard Lovable" />

      <DashboardProvider>
        {/* CONTENEDOR GENERAL (SIN SCROLL) */}
        <div className="bg-[#F7FBFD] dark:bg-gray-950 px-6 py-6 h-[calc(100vh-64px)] overflow-hidden">

          {/* LAYOUT 2 COLUMNAS */}
          <div className="flex gap-6 h-full">

            {/* =========================
                COLUMNA IZQUIERDA
                👉 AQUÍ VA EL SCROLL
            ========================= */}
            <section className="flex-1 min-w-0 flex flex-col h-full">

              {/* HEADER FIJO */}
              <div className="mb-6 shrink-0">
                <DashboardHeader
                  onAddSection={() => widgetsRef.current?.addSection()}
                />
              </div>

              {/* 👇 SCROLL SOLO DE CARDS */}
              <div className="flex-1 overflow-y-auto pr-2">
                <DashboardLovableWidgets ref={widgetsRef} />
              </div>

            </section>

            {/* =========================
                COLUMNA DERECHA — VERA
                👉 FIJO, SIN SCROLL
            ========================= */}
            {isChatOpen && (
              <aside className="w-[420px] shrink-0 h-full">
                <div className="h-full bg-white dark:bg-gray-900 border border-[#D9EEF5] dark:border-gray-700 rounded-xl flex flex-col">
                  <AiChatView embedded />
                </div>
              </aside>
            )}

          </div>
        </div>
      </DashboardProvider>
    </AppLayout>
  );
}

