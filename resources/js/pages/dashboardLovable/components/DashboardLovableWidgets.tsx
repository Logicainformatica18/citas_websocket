import { useEffect, useState, useRef } from "react";
import GridLayout from "react-grid-layout";
import "react-grid-layout/css/styles.css";
import "react-resizable/css/styles.css";

import { fetchWidgets } from "./DashboardAI/useDashboardAPI";
import WidgetCard from "./DashboardAI/WidgetCard";
import axios from "axios";

/* =========================================================
   Helpers
========================================================= */
const getTopY = (items: any[]) => {
  if (!items.length) return 0;
  return Math.min(
    ...items.map((i) => (i.y ?? i.position_y ?? 0))
  );
};

const isNewWidget = (w: any) =>
  w.position_x === null || w.position_y === null;

/* =========================================================
   Component
========================================================= */
export default function DashboardLovableWidgets() {
  const [widgets, setWidgets] = useState<any[]>([]);
  const [sections, setSections] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  const containerRef = useRef<HTMLDivElement>(null);
  const [gridWidth, setGridWidth] = useState(0);

  /* ===============================
     Auto width
  =============================== */
  useEffect(() => {
    const resize = () => {
      if (containerRef.current) {
        setGridWidth(containerRef.current.clientWidth);
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
    axios
      .get("/api/ai/dashboard-sections/1")
      .then((res) => setSections(res.data.sections || []))
      .catch(console.error);

    fetchWidgets()
      .then((res) => {
        const safe = Array.isArray(res)
          ? res.map((w) => ({
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
        setWidgets(safe);
      })
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return <div className="text-sm animate-pulse">Cargando…</div>;
  }

  /* ===============================
     Layout CORRECTO
  =============================== */
  const baseItems = [
    ...sections.map((s) => ({
      i: `section-${s.id}`,
      x: 0,
      y: s.position ?? 0,
      w: 12,
      h: s.height ?? 1,
      static: true,
    })),
    ...widgets
      .filter((w) => !isNewWidget(w))
      .map((w) => ({
        i: String(w.id),
        x: 0,
        y: w.position_y,
        w: 12,
        h: w.height ?? 4,
        static: false,
      })),
  ];

  const topY = getTopY(baseItems);

  const newWidgets = widgets
    .filter(isNewWidget)
    .map((w, idx) => ({
      i: String(w.id),
      x: 0,
      y: topY - (idx + 1) * ((w.height ?? 4) + 1), // 👈 ARRIBA DE TODO
      w: 12,
      h: w.height ?? 4,
      static: false,
    }));

const layout = [
  // 🔹 Secciones
  ...sections.map((s) => ({
    i: `section-${s.id}`,
    x: 0,
    y: s.position ?? 0,
    w: s.width ?? 12,
    h: s.height ?? 1,
    static: false, // 👈 CLAVE
  })),

  // 🔹 Widgets
  ...widgets.map((w, index) => ({
    i: String(w.id),
    x: w.position_x ?? (index % 2) * 6, // 👈 2 por fila
    y: w.position_y ?? 0,               // 👈 ARRIBA
    w: w.width ?? 6,                    // 👈 NO 12
    h: w.height ?? 4,
    static: false,
  })),
];

  /* ===============================
     Render
  =============================== */
  return (
    <div ref={containerRef} className="w-full">
     <GridLayout
  className="layout"
  layout={layout}
  cols={12}
  rowHeight={76}
  width={gridWidth}
  compactType="vertical"      // 👈 EMPUJA
  verticalCompact={true}      // 👈 CLAVE
  isBounded
  margin={[12, 16]}
  isDraggable={true}
  isResizable={true}
  resizeHandles={["s", "e", "se"]} // 👈 ancho + alto
>

        {sections.map((s) => (
    <div
  key={`section-${s.id}`}
  className="
    relative
    h-full
    flex items-center justify-center
    rounded-xl
    px-6

    /* ===== LIGHT MODE ===== */
    bg-gradient-to-r from-[#ECFAFD] to-[#D5F3FB]
    border border-[#A7E5F6]
    shadow-sm

    /* ===== DARK MODE ===== */
    dark:bg-gradient-to-r dark:from-[#052933] dark:to-[#0A4E61]
    dark:border-[#1CBCE8]/40
    dark:shadow-md

    hover:shadow-lg dark:hover:shadow-xl
    transition-all duration-300
  "
>
  {/* 🟦 TÍTULO */}
  <h2
    className="
      text-xl md:text-2xl
      font-bold
      tracking-wide
      uppercase
      text-[#0A4E61]
      dark:text-[#1CBCE8]
      drop-shadow-sm
      select-none
      text-center
    "
  >
    {s.title}
  </h2>

  {/* ⠿ Indicador drag */}
  <div
    className="
      absolute bottom-2 left-2
      text-[#79D7F1]
      dark:text-[#4ACAED]
      opacity-50
      text-xs
      select-none
      pointer-events-none
    "
  >
    ⠿
  </div>
</div>

        ))}

        {widgets.map((w) => (
          <div key={w.id} className="bg-white rounded-lg shadow overflow-hidden">
            <WidgetCard widget={w} />
          </div>
        ))}
      </GridLayout>
    </div>
  );
}
