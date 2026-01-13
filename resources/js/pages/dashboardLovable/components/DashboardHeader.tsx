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
  // onAddSection?: () => void; // 🔕 presente pero desactivado
}

/* ======================================================
   COMPONENTE
====================================================== */
export default function DashboardHeader({
  activeDashboard,
  dashboards,
  onCreateDashboard,
}: DashboardHeaderProps) {
  const { refreshDashboard, isRefreshing } = useDashboard();

  return (
    <div
      className="
        w-full
        bg-[#ECFAFD]
        border border-[#A7E5F6]
        dark:bg-slate-900
        dark:border-slate-700
        rounded-2xl
        px-6 py-5
        space-y-5
      "
    >
      {/* =========================
          TÍTULO / CONTEXTO
      ========================= */}
      <div>
        <h1 className="text-2xl font-semibold text-[#0A4E61] dark:text-slate-100">
          {activeDashboard.title}
        </h1>
        <p className="text-sm text-[#4A7F8D] dark:text-slate-400">
          Gráficos generados por el asistente VERA IA
        </p>
      </div>

      {/* =========================
          BARRA DE CONTROLES
      ========================= */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        {/* -------------------------
            DASHBOARDS (TABS)
        ------------------------- */}
        <div className="flex items-center gap-2 flex-wrap">
          {dashboards.map((dashboard) => {
            const isActive = dashboard.id === activeDashboard.id;

            return (
              <button
                key={dashboard.id}
                onClick={() =>
                  router.visit(`/dashboard/${dashboard.slug}`, {
                    preserveScroll: true,
                    preserveState: false,
                  })
                }
                className={`
                  flex items-center gap-2
                  px-4 py-2 rounded-xl
                  text-sm font-medium
                  transition-all duration-200
                  ${
                    isActive
                      ? "bg-[#1CBCE8] text-white shadow-sm"
                      : `
                        bg-white text-[#0A4E61]
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
              px-4 py-2 rounded-xl
              text-sm font-medium
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

        {/* -------------------------
            ACCIONES
        ------------------------- */}
        <div className="flex items-center gap-2">
          {/*
          // ➕ Nueva sección (reservado)
          <button
            onClick={onAddSection}
            className="
              flex items-center gap-2
              bg-[#1CBCE8]
              hover:bg-[#1399BE]
              text-white
              text-sm font-medium
              px-4 py-2 rounded-xl
              transition
            "
          >
            <Plus size={16} />
            Nueva Sección
          </button>
          */}

          {/* 🔄 Refrescar */}
          <button
            onClick={refreshDashboard}
            disabled={isRefreshing}
            className={`
              flex items-center gap-2
              px-4 py-2 rounded-xl
              text-sm font-medium
              border border-[#A7E5F6]
              text-[#0A4E61]
              dark:border-slate-700
              dark:text-slate-200
              hover:bg-white
              dark:hover:bg-slate-800
              transition
              ${isRefreshing ? "opacity-60 cursor-not-allowed" : ""}
            `}
          >
            <RefreshCw
              size={16}
              className={isRefreshing ? "animate-spin" : ""}
            />
          </button>
        </div>
      </div>
    </div>
  );
}
