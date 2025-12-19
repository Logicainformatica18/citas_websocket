import { useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";

// 👉 CONTEXTO
import { DashboardProvider } from "@/pages/dashboards/DashboardContext";

// 👉 Wrappers Lovable
import DashboardHeader from "./components/DashboardHeader";
import DashboardSection from "./components/DashboardSection";
import WidgetCard from "./components/WidgetCard";

import DashboardLovableWidgets from "./components/DashboardLovableWidgets";

// 👉 Chat VERA
import AiChatView from "@/pages/dashboards/components/AiChat/AiChatView";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Dashboard Lovable", href: "/dashboard/lovable" },
];

export default function DashboardLovable() {
  const [isChatOpen, setIsChatOpen] = useState(true);

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Dashboard Lovable" />

      <DashboardProvider>
        <div className="min-h-screen bg-[#F7FBFD] dark:bg-gray-950 p-4 md:p-6">

          {/* ===============================
              LAYOUT PRINCIPAL (RESPONSIVE)
          =============================== */}
          <div
            className="
              mt-4
              flex flex-col xl:flex-row
              gap-6
              items-stretch
            "
          >
            {/* =================================================
                COLUMNA IZQUIERDA → HEADER + DASHBOARD
            ================================================= */}
            <div
              className="
                flex-1
                min-w-0   /* 👈 CLAVE ABSOLUTA */
                space-y-6
                transition-all duration-300
              "
            >
              {/* ---------- HEADER ---------- */}
              <DashboardHeader
                isChatOpen={isChatOpen}
                onToggleChat={() => setIsChatOpen((v) => !v)}
              />

              {/* ---------- GRID ---------- */}
              <DashboardLovableWidgets />
            </div>

            {/* =================================================
                COLUMNA DERECHA → CHAT VERA
            ================================================= */}
            {isChatOpen && (
              <div
                className="
                  w-full xl:w-[380px]
                  shrink-0
                  transition-all duration-300
                "
              >
                <div
                  className="
                    xl:sticky xl:top-6
                    h-[420px] xl:h-[calc(100vh-120px)]
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
