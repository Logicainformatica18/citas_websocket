import {
    useEffect,
    useState,
    useRef,
    useMemo,
    forwardRef,
    useImperativeHandle,
} from "react";
import GridLayout from "react-grid-layout";
import "react-grid-layout/css/styles.css";
import "react-resizable/css/styles.css";

import axios from "axios";
import Swal from "sweetalert2";
import withReactContent from "sweetalert2-react-content";

import { fetchWidgets } from "./DashboardAI/useDashboardAPI";
import WidgetCard from "./DashboardAI/WidgetCard";

// 🔄 CONTEXTO
import { useDashboard } from "@/pages/dashboards/DashboardContext";

const MySwal = withReactContent(Swal);

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
Ref API
========================================================= */
export type DashboardLovableWidgetsRef = {
    addSection: () => void;
};


/* =========================================================
Component
========================================================= */
const DashboardLovableWidgets = forwardRef<
    DashboardLovableWidgetsRef,
    {}
>((_, ref) => {
    const [sections, setSections] = useState<Section[]>([]);
    const [widgets, setWidgets] = useState<Widget[]>([]);
    const [loading, setLoading] = useState(true);

    // 🔄 REFRESH GLOBAL
    const { refreshKey, stopRefreshing } = useDashboard();

    const containerRef = useRef<HTMLDivElement>(null);
    const [gridWidth, setGridWidth] = useState<number | null>(null);

    /* ======================================================
       ➕ CREAR SECCIÓN (LLAMADO DESDE HEADER)
    ====================================================== */
    const handleAddSection = async () => {
        console.log("🟢 addSection ejecutado");

        const { value: title } = await MySwal.fire({
            title: "Nueva Sección",
            input: "text",
            inputLabel: "Título del bloque",
            inputPlaceholder: "Ej. Tecnologías más demandadas",
            showCancelButton: true,
            confirmButtonText: "Crear",
            cancelButtonText: "Cancelar",
            inputValidator: (value) => {
                if (!value) return "Debes ingresar un título";
            },
        });

        if (!title) return;

        const nextY =
            sections.length === 0
                ? 0
                : Math.max(
                    ...sections.map((s) => (s.position ?? 0) + (s.height ?? 1))
                ) + 1;

        try {
            await axios.post("/api/ai/dashboard-sections", {
                dashboard_id: 1,
                title,
                position: nextY,
                height: 1,
            });

            const res = await axios.get("/api/ai/dashboard-sections/1");
            setSections(res.data.sections || []);

            MySwal.fire("✅ Sección creada", "", "success");
        } catch (e) {
            console.error(e);
            MySwal.fire("Error", "No se pudo crear la sección", "error");
        }
    };

    /* ======================================================
       🔑 EXPONER API IMPERATIVA
    ====================================================== */
    useImperativeHandle(ref, () => ({
        addSection: handleAddSection,
    }));

    /* ===============================
        Items unificados
    =============================== */
    const items = useMemo(() => {
        return [
            ...sections.map((s) => ({
                key: `section-${s.id}`,
                type: "section" as const,
                layout: {
                    i: `section-${s.id}`,
                    x: 0,
                    y: s.position ?? 0,

                    w: 12,                    // ✅ PERMITE ANCHO
                    h: s.height ?? 1,

                    static: false,
                    isResizable: true,
                    resizeHandles: ["e", "w", "s"], // ✅ horizontal + vertical
                },
                data: s,
            })),
            ...widgets.map((w, index) => ({
                key: String(w.id),
                type: "widget" as const,
                layout: {
                    i: String(w.id),
                    x: w.position_x ?? (index % 2) * 6,
                    y: w.position_y ?? index * 4,
                    w: w.width ?? 6,
                    h: w.height ?? 4,
                    static: false,
                },
                data: w,
            })),
        ];
    }, [sections, widgets]);


    const layout = useMemo(() => items.map((i) => i.layout), [items]);

    /* ===============================
        Auto width
    =============================== */
    useEffect(() => {
        const resize = () => {
            if (!containerRef.current) return;
            requestAnimationFrame(() => {
                setGridWidth(containerRef.current!.clientWidth);
            });
        };

        resize();
        window.addEventListener("resize", resize);
        return () => window.removeEventListener("resize", resize);
    }, []);

    /* ===============================
        Load data (refresh)
    =============================== */
    useEffect(() => {
        setLoading(true);

        Promise.all([
            axios.get("/api/ai/dashboard-sections/1"),
            fetchWidgets(),
        ]).then(([sectionsRes, widgetsRes]) => {
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
            setLoading(false);
            stopRefreshing();
        });
    }, [refreshKey]);

    if (loading) return <div className="animate-pulse">Cargando…</div>;

    /* ===============================
        Render
    =============================== */
    return (
        <div ref={containerRef} className="w-full min-w-0 relative">
            <GridLayout
                layout={layout}
                cols={12}
                rowHeight={76}
                width={gridWidth || 1200}
                margin={[12, 18]}          // 👈 más aire vertical
                isDraggable
                isResizable
                draggableHandle=".drag-handle"
 
 
                /* 🔥 ESTE ES EL BLOQUE CLAVE 🔥 */
                onLayoutChange={(newLayout) => {
                    newLayout.forEach((l) => {
                        // =========================
                        // 🧱 SECCIONES
                        // =========================
                        if (l.i.startsWith("section-")) {
                            const sectionId = Number(l.i.replace("section-", ""));

                            axios.post(`/api/ai/dashboard-sections/${sectionId}/update`, {
                                position: l.y,
                                height: l.h,
                                width: l.w,   // 👈 GUARDA ANCHO
                            });
                        }

                        // =========================
                        // 📊 WIDGETS
                        // =========================
                        else {
                            const widget = widgets.find((w) => String(w.id) === l.i);
                            if (!widget) return;

                            axios.put(`/api/ai/dashboard-widgets/${widget.id}`, {
                                position_x: l.x,
                                position_y: l.y,
                                width: l.w,
                                height: l.h,
                            });
                        }
                    });
                }}
            >

                {items.map((item) => (
                    <div key={item.key}>
                        {item.type === "section" ? (
                            <SectionCard
                                section={item.data}

                                /* ✏️ EDITAR */
                                onEdit={async (section) => {
                                    const { value: title } = await MySwal.fire({
                                        title: "Editar sección",
                                        input: "text",
                                        inputValue: section.title,
                                        showCancelButton: true,
                                        confirmButtonText: "Guardar",
                                        cancelButtonText: "Cancelar",
                                        inputValidator: (value) => {
                                            if (!value) return "El título no puede estar vacío";
                                        },
                                    });

                                    if (!title) return;

                                    try {
                                        await axios.post(
                                            `/api/ai/dashboard-sections/${section.id}/update`,
                                            { title }
                                        );

                                        // 🔄 actualizar estado local
                                        setSections((prev) =>
                                            prev.map((s) =>
                                                s.id === section.id ? { ...s, title } : s
                                            )
                                        );

                                        MySwal.fire("✅ Actualizado", "", "success");
                                    } catch (e) {
                                        MySwal.fire("Error", "No se pudo editar la sección", "error");
                                    }
                                }}

                                /* 🗑️ ELIMINAR */
                                onDelete={async (section) => {
                                    const result = await MySwal.fire({
                                        title: "¿Eliminar sección?",
                                        text: "Esta acción no se puede deshacer",
                                        icon: "warning",
                                        showCancelButton: true,
                                        confirmButtonText: "Eliminar",
                                        cancelButtonText: "Cancelar",
                                    });

                                    if (!result.isConfirmed) return;

                                    try {
                                        await axios.delete(
                                            `/api/ai/dashboard-sections/${section.id}`
                                        );

                                        // 🔄 eliminar del estado local
                                        setSections((prev) =>
                                            prev.filter((s) => s.id !== section.id)
                                        );

                                        MySwal.fire("🗑️ Eliminada", "", "success");
                                    } catch (e) {
                                        MySwal.fire("Error", "No se pudo eliminar la sección", "error");
                                    }
                                }}
                            />
                        ) : (
                            <WidgetCard widget={item.data} />
                        )}

                    </div>
                ))}
            </GridLayout>
        </div>
    );
});

DashboardLovableWidgets.displayName = "DashboardLovableWidgets";

export default DashboardLovableWidgets;

/* =========================================================
Section Card
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
        h-full
        rounded-xl
        bg-[#ECFAFD]
        border border-[#A7E5F6]
        flex items-center justify-center
        relative
      "
        >
            {/* ⠿ DRAG HANDLE (izquierda) */}
            <div
                className="
          drag-handle
          absolute left-3
          cursor-move
          text-gray-400
          select-none
        "
                title="Mover sección"
            >
                ⠿
            </div>

            {/* ✏️ 🗑️ acciones (derecha) */}
            <div className="absolute right-3 flex gap-2">
                <button
                    onClick={(e) => {
                        e.stopPropagation();
                        onEdit?.(section);
                    }}
                >
                    ✏️
                </button>

                <button
                    onClick={(e) => {
                        e.stopPropagation();
                        onDelete?.(section);
                    }}
                >
                    🗑️
                </button>

            </div>

            {/* 🟦 TÍTULO CENTRADO */}
            <h2
                className="
          text-lg font-semibold
          text-[#0A4E61]
          text-center
          select-none
        "
            >
                {section.title}
            </h2>
        </div>
    );
}
