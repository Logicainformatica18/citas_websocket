import { Plus, Filter, RefreshCw, MessageSquare } from "lucide-react";

interface DashboardHeaderProps {
  isChatOpen?: boolean;
  onToggleChat?: () => void;

  /** Acciones globales del dashboard (vienen de DashboardAIWidgets) */
  onAddSection?: () => void;
  onRefreshDashboard?: () => void;
  onOpenFilters?: () => void; // opcional, por si luego conectas filtros
}

export default function DashboardHeader({
  isChatOpen = true,
  onToggleChat,
  onAddSection,
  onRefreshDashboard,
  onOpenFilters,
}: DashboardHeaderProps) {
  return (
    <div
      className="
        flex flex-col md:flex-row md:items-center md:justify-between gap-4
        bg-[#ECFAFD] dark:bg-gray-900
        border border-[#A7E5F6] dark:border-gray-700
        rounded-xl px-6 py-4
      "
    >
      {/* =====================
          IZQUIERDA
      ===================== */}
      <div className="flex items-center gap-3">
        <div className="bg-[#1CBCE8] text-white p-2 rounded-lg">
          🤖
        </div>

        <div>
          <h1 className="text-xl font-bold text-gray-900 dark:text-white">
            Dashboard generado por VERA
          </h1>
          <p className="text-sm text-gray-600 dark:text-gray-400">
            Vista creada automáticamente según tus consultas
          </p>
        </div>
      </div>

      {/* =====================
          ACCIONES
      ===================== */}
      <div className="flex flex-wrap gap-2 items-center">

        {/* ➕ Nueva sección */}
        <button
          onClick={onAddSection}
          disabled={!onAddSection}
          className="
            flex items-center gap-2
            bg-[#1CBCE8] hover:bg-[#1399BE]
            text-white text-sm font-medium
            px-4 py-2 rounded-lg
            transition
            disabled:opacity-50 disabled:cursor-not-allowed
          "
        >
          <Plus size={16} />
          Nueva sección
        </button>

        {/* 🔍 Filtros */}
        <button
          onClick={onOpenFilters}
          className="
            flex items-center gap-2
            border border-[#1CBCE8]
            text-[#1CBCE8]
            px-4 py-2 rounded-lg text-sm
            hover:bg-[#ECFAFD] dark:hover:bg-gray-800
            transition
          "
        >
          <Filter size={16} />
          Filtros
        </button>

        {/* 🔄 Actualizar */}
        <button
          onClick={onRefreshDashboard}
          disabled={!onRefreshDashboard}
          className="
            flex items-center gap-2
            border border-gray-300 dark:border-gray-600
            text-gray-600 dark:text-gray-300
            px-4 py-2 rounded-lg text-sm
            hover:bg-gray-100 dark:hover:bg-gray-800
            transition
            disabled:opacity-50 disabled:cursor-not-allowed
          "
        >
          <RefreshCw size={16} />
          Actualizar
        </button>

        {/* 🤖 VERA (abrir / cerrar chat) */}
        {/* {onToggleChat && (
          <button
            onClick={onToggleChat}
            className={`
              flex items-center gap-2
              px-4 py-2 rounded-lg text-sm font-medium
              transition
              ${
                isChatOpen
                  ? "bg-gray-900 text-white hover:bg-gray-800"
                  : "border border-gray-300 text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
              }
            `}
            title={isChatOpen ? "Cerrar VERA" : "Abrir VERA"}
          >
            <MessageSquare size={16} />
            {isChatOpen ? "Cerrar VERA" : "Abrir VERA"}
          </button>
        )} */}
      </div>
    </div>
  );
}
