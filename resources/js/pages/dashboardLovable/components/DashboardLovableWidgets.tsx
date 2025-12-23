    import { useEffect, useState, useRef, useMemo } from "react";
    import GridLayout from "react-grid-layout";
    import "react-grid-layout/css/styles.css";
    import "react-resizable/css/styles.css";

    import axios from "axios";
    import { fetchWidgets } from "./DashboardAI/useDashboardAPI";
    import WidgetCard from "./DashboardAI/WidgetCard";

    // 🔄 CONTEXTO
    import { useDashboard } from "@/pages/dashboards/DashboardContext";

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

    // 🔄 REFRESH GLOBAL
    const { refreshKey, stopRefreshing } = useDashboard();

    const containerRef = useRef<HTMLDivElement>(null);
    const [gridWidth, setGridWidth] = useState<number | null>(null);

    
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
            isDraggable: false,
            isResizable: true,
            resizeHandles: ["s"],
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
            y: w.position_y ?? Infinity,
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


    /* ===============================
        Auto width (solo columna izquierda)
    =============================== */
    useEffect(() => {
    const resize = () => {
        if (!containerRef.current) return;

        requestAnimationFrame(() => {
        const width = containerRef.current?.clientWidth;
        if (width && width > 0) {
            setGridWidth(width);
        }
        });
    };

    resize();
    window.addEventListener("resize", resize);
    return () => window.removeEventListener("resize", resize);
    }, []);


    /* ===============================
        Load data (con refresh)
    =============================== */
    useEffect(() => {
        setLoading(true);

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
        .finally(() => {
            setLoading(false);
            stopRefreshing(); // 🔓 libera el botón "Actualizar"
        });
    }, [refreshKey]);

    /* ===============================
        Items unificados
    =============================== */

    
    

    
    
    if (loading) {
        return <div className="text-sm animate-pulse">Cargando…</div>;
    }
    if (!gridWidth) {
    return (
        <div
        ref={containerRef}
        className="w-full min-w-0"
        style={{ minHeight: 1 }}
        />
    );
    }

    

    /* ===============================
        Render
    =============================== */
    return (
        <div
    ref={containerRef}
    className="w-full min-w-0 relative"
    style={{ minHeight: "600px" }} // 🔥 ESTO ES LO QUE FALTABA
  >
    <GridLayout
      layout={layout}
      cols={12}
      rowHeight={76}
      width={gridWidth}
      compactType="vertical"
      verticalCompact
      margin={[12, 16]}
      isDraggable
      isResizable
      draggableCancel=".no-drag"
      draggableHandle=".drag-handle"
      onLayoutChange={(newLayout) => {
        newLayout.forEach((l) => {
          if (l.i.startsWith("section-")) {
            const sectionId = Number(l.i.replace("section-", ""));
            axios.put(`/api/ai/dashboard-sections/${sectionId}`, {
              position: l.y,
              height: l.h,
            });
          } else {
            const widget = items.find(
              (it) => it.type === "widget" && it.layout.i === l.i
            )?.data;

            if (!widget) return;

            axios.put(`/api/widgets/${widget.id}`, {
              position_x: l.x,
              position_y: l.y,
              width: l.w,
              height: l.h,
            });
          }
        });
      }}
    >
      {items.map((item) =>
        item.type === "section" ? (
          <div key={item.key} className="h-full w-full">
            <SectionCard section={item.data} />
          </div>
        ) : (
          <div key={item.key} className="h-full w-full">
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
        text-xs font-semibold uppercase tracking-widest
        text-[#0A4E61] dark:text-[#1CBCE8]
        truncate
        max-w-[70%]
    "
    title={section.title}
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
