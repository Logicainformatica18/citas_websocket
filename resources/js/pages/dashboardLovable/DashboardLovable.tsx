import { useState, useRef, useEffect } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head, router } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";

import { DashboardProvider, useDashboard } from "@/pages/dashboards/DashboardContext";

import DashboardHeader from "./components/DashboardHeader";
import DashboardLovableWidgets from "./components/DashboardLovableWidgets";
import AiChatView from "@/pages/dashboards/components/AiChat/AiChatView";

/* =========================================================
   Tipos
========================================================= */
interface Dashboard {
    id: number;
    title: string;
    slug: string;
}

interface DashboardLovableProps {
    dashboards: Dashboard[];
    activeDashboard: Dashboard;
    widgets: any[];
}

/* =========================================================
   Breadcrumbs
========================================================= */
const breadcrumbs: BreadcrumbItem[] = [
    { title: "Dashboard", href: "/dashboard" },
];

/* =========================================================
   Wrapper para sincronizar dashboard activo
========================================================= */
function DashboardSync({ dashboard }: { dashboard: Dashboard }) {
    const { setActiveDashboard } = useDashboard();

    useEffect(() => {
        setActiveDashboard(dashboard);
    }, [dashboard.id]);

    return null;
}

/* =========================================================
   Page
========================================================= */
export default function DashboardLovable({
    dashboards,
    activeDashboard,
    widgets,
}: DashboardLovableProps) {
    const [isChatOpen] = useState(true);
    const widgetsRef = useRef<{ addSection: () => void }>(null);

    /* ======================================================
       Navegar entre dashboards (tabs)
    ====================================================== */
    const handleChangeDashboard = (dashboard: Dashboard) => {
        if (dashboard.id === activeDashboard.id) return;

        router.visit(`/dashboard/${dashboard.slug}`, {
            preserveScroll: true,
        });
    };

    /* ======================================================
       Crear nuevo dashboard
    ====================================================== */
    const handleCreateDashboard = async () => {
        const title = prompt("Nombre del nuevo dashboard");
        if (!title) return;

        router.post(
            "/dashboard",
            { title },
            {
                preserveScroll: true,
            }
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={activeDashboard.title} />

            {/* ======================================================
          CONTEXTO GLOBAL (NO SE TOCA)
      ====================================================== */}
            <DashboardProvider>
                {/* 🔥 sincroniza dashboard activo en el context */}
                <DashboardSync dashboard={activeDashboard} />

                {/* CONTENEDOR GENERAL */}
                <div className="bg-[#F7FBFD] dark:bg-gray-950 px-6 py-6 h-[calc(100vh-64px)] overflow-hidden">
                    {/* LAYOUT 2 COLUMNAS */}
                    <div className="flex gap-6 h-full">
                        {/* =========================
                COLUMNA IZQUIERDA
            ========================= */}
                        <section className="flex-1 min-w-0 flex flex-col h-full">
                            {/* HEADER */}
                            <div className="mb-6 shrink-0">
                                <DashboardHeader
                                    dashboards={dashboards}
                                    activeDashboard={activeDashboard}        // 🔥 OBJETO
                                    onChangeDashboard={handleChangeDashboard} // 🔥 OBJETO
                                    onCreateDashboard={handleCreateDashboard}
                                    onAddSection={() => widgetsRef.current?.addSection()}
                                />

                            </div>

                            {/* SCROLL SOLO DE WIDGETS */}
                            <div className="flex-1 overflow-y-auto pr-2">
                                <DashboardLovableWidgets
                                    key={activeDashboard.id}   // 🔥 ESTO ES LA CLAVE
                                    ref={widgetsRef}
                                />
                            </div>
                        </section>

                        {/* =========================
                COLUMNA DERECHA — VERA AI
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
