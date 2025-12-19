import { useEffect, useState, useRef, useMemo } from "react";
import GridLayout from "react-grid-layout";
import "react-grid-layout/css/styles.css";
import "react-resizable/css/styles.css";

import axios from "axios";
import { fetchWidgets } from "./DashboardAI/useDashboardAPI";
import WidgetCard from "./DashboardAI/WidgetCard";

/* =========================================================
   Types
========================================================= */
type Section = {
  id: number;
  title: string;
  position?: number;
  height?: number;
};

type Widget = {
  id: number;
  position_x?: number | null;
  position_y?: number | null;
  width?: number;
  height?: number;
  data_source?: any;
  colors?: any;
};

/* =========================================================
   Component
========================================================= */
export default function DashboardLovableWidgets() {
  const [sections, setSections] = useState<Section[]>([]);
  const [widgets, setWidgets] = useState<Widget[]>([]);
  const [loading, setLoading] = useState(true);

  const containerRef = useRef<HTMLDivElement>(null);
  const [gridWidth, setGridWidth] = useState(1200);

  /* ===============================
     Auto width (solo columna izquierda)
  =============================== */
  useEffect(() => {
    const resize = () => {
      if (containerRef.current) {
        setGridWidth(containerRef.current.clientWidth || 1200);
      }
    };
    resize();
    window.addEventListener("resize", resize);
    return () => window.removeEventListener("resize", resize);
  }, []);

  /* ===============================
     Load data
  =============================== */
  useEffect(() => {
    Promise.all([
      axios.get("/api/ai/dashboard-sections/1"),
      fetchWidgets(),
    ])
      .then(([sectionsRes, widgetsRes]) => {
        setSections(sectionsRes.data.sections || []);

        const safeWidgets = Array.isArray(widgetsRes)
          ? widgetsRes.map((w: any) => ({
              ...w,
              data_source:
                typeof w.data_source === "string"
                  ? JSON.parse(w.data_source)
                  : w.data_source,
              colors:
                typeof w.colors === "string"
                  ? JSON.parse(w.colors)
                  : w.colors,
            }))
          : [];

        setWidgets(safeWidgets);
      })
      .finally(() => setLoading(false));
  }, []);

  /* ===============================
     Items unificados (CLAVE)
  =============================== */
  const items = useMemo(() => {
    return [
      /* ===== SECCIONES ===== */
      ...sections.map((s) => ({
        key: `section-${s.id}`,
        type: "section" as const,
        layout: {
          i: `section-${s.id}`,
          x: 0,
          y: s.position ?? 0,
          w: 12,
          h: s.height ?? 1,

          static: false,
          isDraggable: false,   // ❌ no mover
          isResizable: true,    // ✅ estirar
          resizeHandles: ["s"], // solo vertical
        },
        data: s,
      })),

      /* ===== WIDGETS ===== */
      ...widgets.map((w, index) => ({
        key: String(w.id),
        type: "widget" as const,
        layout: {
          i: String(w.id),
          x: w.position_x ?? (index % 2) * 6,
          y: w.position_y ?? Infinity, // 👈 evita solapes
          w: w.width ?? 6,
          h: w.height ?? 4,
          static: false,
        },
        data: w,
      })),
    ];
  }, [sections, widgets]);

  const layout = useMemo(
    () => items.map((i) => i.layout),
    [items]
  );

  if (loading) {
    return <div className="text-sm animate-pulse">Cargando…</div>;
  }

  /* ===============================
     Render
  =============================== */
  return (
    <div ref={containerRef} className="w-full min-w-0 relative">
      <GridLayout
        layout={layout}
        cols={12}
        rowHeight={76}
        width={gridWidth}
        compactType="vertical"
        verticalCompact
        isBounded
        margin={[12, 16]}
        isDraggable
        isResizable
      >
        {items.map((item) =>
          item.type === "section" ? (
            <div key={item.key}>
              <SectionCard
                section={item.data}
                onEdit={(s) => console.log("editar", s)}
                onDelete={(s) => console.log("eliminar", s)}
              />
            </div>
          ) : (
            <div
              key={item.key}
              className="bg-white rounded-lg shadow overflow-hidden"
            >
              <WidgetCard widget={item.data} />
            </div>
          )
        )}
      </GridLayout>
    </div>
  );
}

/* =========================================================
   Section Card (con acciones)
========================================================= */
function SectionCard({
  section,
  onEdit,
  onDelete,
}: {
  section: Section;
  onEdit?: (s: Section) => void;
  onDelete?: (s: Section) => void;
}) {
  return (
    <div
      className="
        h-full flex flex-col rounded-xl
        bg-gradient-to-r from-[#ECFAFD] to-[#D5F3FB]
        border border-[#A7E5F6]
        dark:from-[#052933] dark:to-[#0A4E61]
        dark:border-[#1CBCE8]/40
        shadow-sm hover:shadow-lg transition
      "
    >
      {/* HEADER */}
      <div
        className="
          flex items-center justify-between
          px-4 py-3
          border-b border-[#A7E5F6]
          dark:border-[#1CBCE8]/30
        "
      >
        <h2
          className="
            text-lg font-bold uppercase tracking-wide
            text-[#0A4E61] dark:text-[#1CBCE8]
            select-text
          "
        >
          {section.title}
        </h2>

        <div className="flex gap-2">
          <button
            onClick={() => onEdit?.(section)}
            className="
              p-1.5 rounded-md
              text-[#0A4E61] dark:text-[#1CBCE8]
              hover:bg-[#A7E5F6]/60 dark:hover:bg-[#1CBCE8]/10
            "
            title="Editar sección"
          >
            ✏️
          </button>

          <button
            onClick={() => onDelete?.(section)}
            className="
              p-1.5 rounded-md
              text-red-500 hover:bg-red-500/10
            "
            title="Eliminar sección"
          >
            🗑️
          </button>
        </div>
      </div>

      {/* BODY */}
      <div className="flex-1 flex items-center justify-center text-sm text-gray-400">
        {/* contenido opcional */}
      </div>
    </div>
  );
}
