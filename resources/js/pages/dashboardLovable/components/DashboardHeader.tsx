import { Plus, Filter, RefreshCw, LayoutGrid } from "lucide-react";
import { useDashboard } from "@/pages/dashboards/DashboardContext";

interface DashboardHeaderProps {
  activeDashboard?: string;
  dashboards?: string[];

  onChangeDashboard?: (name: string) => void;
  onCreateDashboard?: () => void;

  /** 🔹 Dispara el SweetAlert + createSection del grid */
  onAddSection?: () => void;

  onOpenFilters?: () => void;
}

export default function DashboardHeader({
  activeDashboard = "Dashboard Principal",
  dashboards = ["Dashboard Principal", "Dashboard 2"],

  onChangeDashboard,
  onCreateDashboard,
  onAddSection,
  onOpenFilters,
}: DashboardHeaderProps) {
  const { refreshDashboard, isRefreshing } = useDashboard();

  return (
    <div className="w-full space-y-4">

      {/* ======================================================
          TABS SUPERIORES (Dashboards)
      ====================================================== */}
      <div className="flex items-center gap-2 flex-wrap">
        {dashboards.map((name) => {
          const isActive = name === activeDashboard;

          return (
            <button
              key={name}
              onClick={() => onChangeDashboard?.(name)}
              className={`
                flex items-center gap-2
                px-4 py-2 rounded-xl text-sm font-medium
                transition-all duration-200
                ${
                  isActive
                    ? "bg-[#1CBCE8] text-white shadow-sm"
                    : "bg-[#ECFAFD] text-[#0A4E61] hover:bg-[#D5F3FB]"
                }
              `}
            >
              <LayoutGrid size={16} />
              {name}
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
          flex flex-col md:flex-row md:items-center md:justify-between gap-4
          bg-[#ECFAFD]
          border border-[#A7E5F6]
          rounded-2xl px-6 py-5
        "
      >
        {/* IZQUIERDA */}
        <div className="flex items-center gap-4">
          <div className="bg-[#1CBCE8] text-white p-3 rounded-xl shadow-sm">
            🤖
          </div>

          <div>
            <h1 className="text-2xl font-bold text-[#0A4E61]">
              {activeDashboard}
            </h1>
            <p className="text-sm text-[#4A7F8D]">
              Vista creada automáticamente según tus consultas
            </p>
          </div>
        </div>

        {/* ==================================================
            ACCIONES
        ================================================== */}
        <div className="flex flex-wrap items-center gap-2">

          {/* ➕ Nueva sección (REPLICADO DEL GRID) */}
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

          {/* 🔍 Filtros */}
          <button
            onClick={onOpenFilters}
            className="
              flex items-center gap-2
              border border-[#1CBCE8]
              text-[#1CBCE8]
              px-4 py-2.5 rounded-xl text-sm
              hover:bg-[#ECFAFD]
              transition-all duration-200
            "
          >
            <Filter size={16} />
            Filtros
          </button>

          {/* 🔄 Actualizar dashboard */}
          <button
            onClick={refreshDashboard}
            disabled={isRefreshing}
            className={`
              flex items-center gap-2
              border border-[#A7E5F6]
              text-[#0A4E61]
              px-4 py-2.5 rounded-xl text-sm
              hover:bg-[#ECFAFD]
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
