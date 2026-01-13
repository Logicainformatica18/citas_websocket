import { Plus, RefreshCw, LayoutGrid } from "lucide-react";
import { router } from "@inertiajs/react";
import { useDashboard } from "@/pages/dashboards/DashboardContext";

/* ======================================================
   TIPOS
====================================================== */
export interface Dashboard {
  id: number;
  title: string;
  slug: string;
}

interface DashboardHeaderProps {
  activeDashboard: Dashboard;
  dashboards: Dashboard[];
  onCreateDashboard?: () => void;
  onAddSection?: () => void;
}

/* ======================================================
   COMPONENTE
====================================================== */
export default function DashboardHeader({
  activeDashboard,
  dashboards,
  onCreateDashboard,
  onAddSection,
}: DashboardHeaderProps) {
  const { refreshDashboard, isRefreshing } = useDashboard();

  return (
    <div className="w-full space-y-4">
      {/* ======================================================
          TABS DE DASHBOARDS
      ====================================================== */}
      <div className="flex items-center gap-2 flex-wrap">
        {dashboards.map((dashboard) => {
          const isActive = dashboard.id === activeDashboard.id;

          return (
            <button
              key={dashboard.id}
              onClick={() =>
                router.visit(`/dashboard/${dashboard.slug}`)
              }
              className={`
                flex items-center gap-2
                px-4 py-2 rounded-xl text-sm font-medium
                transition-all duration-200
                ${
                  isActive
                    ? "bg-[#1CBCE8] text-white shadow-sm"
                    : `
                      bg-[#ECFAFD] text-[#0A4E61]
                      hover:bg-[#D5F3FB]
                      dark:bg-slate-800
                      dark:text-slate-200
                      dark:hover:bg-slate-700
                    `
                }
              `}
            >
              <LayoutGrid size={16} />
              {dashboard.title}
            </button>
          );
        })}

        {/* ➕ Nuevo dashboard */}
        <button
          onClick={onCreateDashboard}
          className="
            flex items-center gap-2
            px-4 py-2 rounded-xl text-sm font-medium
            border border-dashed border-[#1CBCE8]
            text-[#1CBCE8]
            hover:bg-[#ECFAFD]
            dark:hover:bg-slate-800
            transition-all duration-200
          "
        >
          <Plus size={16} />
          Nuevo
        </button>
      </div>

      {/* ======================================================
          HEADER PRINCIPAL
      ====================================================== */}
      <div
        className="
          flex flex-col md:flex-row
          md:items-center md:justify-between
          gap-4
          bg-[#ECFAFD]
          border border-[#A7E5F6]
          dark:bg-slate-900
          dark:border-slate-700
          rounded-2xl
          px-6 py-5
        "
      >
        {/* IZQUIERDA */}
        <div className="flex items-center gap-4">
          <div>
            <h1 className="text-2xl font-bold text-[#0A4E61] dark:text-slate-100">
              {activeDashboard.title}
            </h1>
            <p className="text-sm text-[#4A7F8D] dark:text-slate-400">
              Vista creada automáticamente según tus consultas
            </p>
          </div>
        </div>

        {/* ==================================================
            ACCIONES
        ================================================== */}
        <div className="flex flex-wrap items-center gap-2">
          {/* ➕ Nueva sección */}
          <button
            onClick={onAddSection}
            disabled={!onAddSection}
            className={`
              flex items-center gap-2
              bg-[#1CBCE8] hover:bg-[#1399BE]
              text-white text-sm font-medium
              px-5 py-2.5 rounded-xl
              transition-all duration-200
              ${!onAddSection ? "opacity-60 cursor-not-allowed" : ""}
            `}
          >
            <Plus size={16} />
            Nueva Sección
          </button>

          {/* 🔄 Actualizar dashboard */}
          <button
            onClick={refreshDashboard}
            disabled={isRefreshing}
            className={`
              flex items-center gap-2
              border border-[#A7E5F6]
              text-[#0A4E61]
              dark:border-slate-700
              dark:text-slate-200
              px-4 py-2.5 rounded-xl text-sm
              hover:bg-[#ECFAFD]
              dark:hover:bg-slate-800
              transition-all duration-200
              ${isRefreshing ? "opacity-60 cursor-not-allowed" : ""}
            `}
          >
            <RefreshCw
              size={16}
              className={isRefreshing ? "animate-spin" : ""}
            />
            {isRefreshing ? "Actualizando..." : "Actualizar"}
          </button>
        </div>
      </div>
    </div>
  );
}
