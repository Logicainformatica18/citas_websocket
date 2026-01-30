import { useState } from "react";
import {
  Plus,
  RefreshCw,
  LayoutGrid,
  MoreVertical,
  Pencil,
  Trash2,
} from "lucide-react";
import { router } from "@inertiajs/react";
import Swal from "sweetalert2";
import { useDashboard } from "@/pages/dashboards/DashboardContext";

/* ======================================================
   TIPOS
====================================================== */
export interface Dashboard {
  id: number;
  title: string;
  slug: string;
  is_default?: boolean;
}

interface DashboardHeaderProps {
  activeDashboard: Dashboard;
  dashboards: Dashboard[];
  onCreateDashboard?: () => void;
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

  /* =====================================
     Estado edición título
  ===================================== */
  const [isEditing, setIsEditing] = useState(false);
  const [title, setTitle] = useState(activeDashboard.title);

  const saveTitle = () => {
    if (!title.trim() || title === activeDashboard.title) {
      setIsEditing(false);
      setTitle(activeDashboard.title);
      return;
    }

    router.put(
      route("dashboard.update", activeDashboard.id),
      { title },
      {
        preserveScroll: true,
        onFinish: () => setIsEditing(false),
      }
    );
  };

  /* =====================================
     Eliminar dashboard
  ===================================== */
  const deleteDashboard = () => {
    Swal.fire({
      title: "¿Eliminar dashboard?",
      text: "Esta acción no se puede deshacer",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Sí, eliminar",
      cancelButtonText: "Cancelar",
    }).then((result) => {
      if (result.isConfirmed) {
        router.delete(
          route("dashboard.destroy", activeDashboard.id)
        );
      }
    });
  };

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
      <div className="flex items-start justify-between gap-4">
        <div className="flex-1">
          {isEditing ? (
            <input
              autoFocus
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              onBlur={saveTitle}
              onKeyDown={(e) => {
                if (e.key === "Enter") saveTitle();
                if (e.key === "Escape") {
                  setIsEditing(false);
                  setTitle(activeDashboard.title);
                }
              }}
              className="
                w-full
                bg-white
                dark:bg-slate-800
                border border-[#A7E5F6]
                dark:border-slate-700
                rounded-lg
                px-3 py-2
                text-2xl font-semibold
                text-[#0A4E61]
                dark:text-slate-100
                focus:outline-none
              "
            />
          ) : (
            <h1
              onClick={() => setIsEditing(true)}
              className="
                text-2xl font-semibold
                text-[#0A4E61]
                dark:text-slate-100
                cursor-pointer
                hover:opacity-80
              "
            >
              {activeDashboard.title}
            </h1>
          )}

          <p className="text-sm text-[#4A7F8D] dark:text-slate-400">
            Gráficos generados por el asistente VERA IA
          </p>
        </div>

        {/* =========================
            MENÚ ⋮
        ========================= */}
        <div className="relative">
          <button
            className="
              p-2 rounded-lg
              hover:bg-[#D5F3FB]
              dark:hover:bg-slate-800
            "
            onClick={() => {
              Swal.fire({
                title: activeDashboard.title,
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: "Renombrar",
                denyButtonText: "Eliminar",
                cancelButtonText: "Cancelar",
              }).then((result) => {
                if (result.isConfirmed) {
                  setIsEditing(true);
                }
                if (result.isDenied && !activeDashboard.is_default) {
                  deleteDashboard();
                }
              });
            }}
          >
            <MoreVertical size={18} />
          </button>
        </div>
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

          {/* ➕ Nuevo */}
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
  );
}
