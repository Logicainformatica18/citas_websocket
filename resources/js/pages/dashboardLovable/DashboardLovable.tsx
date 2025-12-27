import { useState, useRef } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";

import { DashboardProvider } from "@/pages/dashboards/DashboardContext";

import DashboardHeader from "./components/DashboardHeader";
import DashboardLovableWidgets from "./components/DashboardLovableWidgets";
import AiChatView from "@/pages/dashboards/components/AiChat/AiChatView";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Dashboard Lovable", href: "/dashboard/lovable" },
];

export default function DashboardLovable() {
  const [isChatOpen, setIsChatOpen] = useState(true);

  // 👉 REF para exponer funciones del grid
  const widgetsRef = useRef<{ addSection: () => void }>(null);

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Dashboard Lovable" />

      <DashboardProvider>
        {/* ===== CONTENEDOR GENERAL ===== */}
        <div className="bg-[#F7FBFD] dark:bg-gray-950 px-6 py-6">

          {/* ===== LAYOUT LOVABLE (2 columnas) ===== */}
          <div className="flex gap-6 items-start">

            {/* ==============================
                COLUMNA IZQUIERDA (DASHBOARD)
            ============================== */}
            <div className="flex-1 min-w-0 space-y-6">

              {/* 🔑 HEADER CONECTADO */}
              <DashboardHeader
                onAddSection={() => widgetsRef.current?.addSection()}
              />

              {/* 🔑 GRID EXPONE addSection */}
              <DashboardLovableWidgets ref={widgetsRef} />

            </div>

            {/* ==============================
                COLUMNA DERECHA (CHAT VERA)
            ============================== */}
            {isChatOpen && (
              <div className="w-[420px] shrink-0">
                <div
                  className="
                    bg-[#ECFAFD] dark:bg-gray-900
                    border border-[#A7E5F6] dark:border-gray-700
                    rounded-xl
                    min-h-[720px]
                    flex flex-col
                  "
                >
                  <AiChatView />
                </div>
              </div>
            )}

          </div>
        </div>
      </DashboardProvider>
    </AppLayout>
  );
}
