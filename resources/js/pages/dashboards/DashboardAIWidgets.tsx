import React, { useEffect, useState,useRef } from "react";
import GridLayout from "react-grid-layout";
import "react-grid-layout/css/styles.css";
import "react-resizable/css/styles.css";
import { fetchWidgets, reorderWidgets } from "./components/DashboardAI/useDashboardAPI";
import WidgetCard from "./components/DashboardAI/WidgetCard";
import axios from "axios";
import { Edit3, Trash2 } from "lucide-react";
import { updateSection, deleteSection } from "./components/DashboardAI/useDashboardAPI";
import Swal from "sweetalert2";
import withReactContent from "sweetalert2-react-content";

const MySwal = withReactContent(Swal);
/**
 * 🧠 DashboardAIWidgets
 * - Carga los widgets del backend.
 * - Permite moverlos con react-grid-layout.
 * - Protege contra datos faltantes o JSON sin parsear.
 */
export default function DashboardAIWidgets() {
  const [widgets, setWidgets] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
const [sections, setSections] = useState<any[]>([]);

const gridRef = useRef<HTMLDivElement>(null);
const [gridWidth, setGridWidth] = useState(1200);


// 🔁 Detectar automáticamente el ancho del contenedor
useEffect(() => {
  const handleResize = () => {
    if (gridRef.current) {
      setGridWidth(gridRef.current.offsetWidth - 40); // -40 por padding
    }
  };

  handleResize();
  window.addEventListener("resize", handleResize);
  return () => window.removeEventListener("resize", handleResize);
}, []);
const handleDeleteWidget = (id) => {
  setWidgets((prev) => prev.filter((w) => w.id !== id));
};

// ============================================================
// 📐 Calcula la siguiente posición Y libre en el grid
// ============================================================
const getNextAvailableY = (items: any[]) => {
  if (!items || items.length === 0) return 0;

  const maxY = Math.max(
    ...items.map((item) => {
      const y = item.y ?? item.position_y ?? 0;
      const h = item.h ?? item.height ?? 3;
      return y + h;
    })
  );
  return maxY + 1;
};


// ============================================================
// 🧱 Cargar secciones (bloques de título)
// ============================================================
useEffect(() => {
  axios
    .get("/api/ai/dashboard-sections/1") // ← tu dashboard_id aquí
    .then((res) => setSections(res.data.sections || []))
    .catch((err) => console.error("❌ Error cargando secciones:", err));
}, []);

  // ============================================================
  // 🚀 1️⃣ Cargar widgets al iniciar
  // ============================================================
  useEffect(() => {
    fetchWidgets()
      .then((res) => {
        const safe = Array.isArray(res)
          ? res
              .filter((w) => w && w.id) // evita nulos
              .map((w) => ({
                ...w,
                // ✅ Parsear JSON si viene como string
                data_source:
                  typeof w.data_source === "string"
                    ? JSON.parse(w.data_source)
                    : w.data_source ?? { rows: [] },
                colors:
                  typeof w.colors === "string"
                    ? JSON.parse(w.colors)
                    : w.colors ?? { primary: "#1E88E5", secondary: "#90CAF9" },
              }))
          : [];

        setWidgets(safe);
      })
      .catch((e) => console.error("❌ Error cargando widgets:", e))
      .finally(() => setLoading(false));
  }, []);

  // ============================================================
  // 🕐 2️⃣ Estado de carga
  // ============================================================
  if (loading)
    return (
      <div className="text-gray-400 text-sm animate-pulse">
        Cargando gráficos IA generados por VERA...
      </div>
    );

  if (!widgets.length)
    return (
      <div className="text-gray-400 text-sm">
        No hay widgets aún. Genera uno desde VERA 🤖
      </div>
    );

// ============================================================
// 📏 3️⃣ Layout combinado (secciones + widgets)
// ============================================================
const layout: any[] = [];

// 🧱 Secciones (bloques de título)
sections.forEach((s) => {
  layout.push({
    i: `section-${s.id}`,
    x: 0,
    y: s.position ?? getNextAvailableY(layout),
    w: s.width ?? 12,
    h: s.height ?? 1,
    static: false,
  });
});

// 📊 Widgets IA
widgets.forEach((w) => {
  layout.push({
    i: String(w.id),
    x: w.position_x ?? 0,
    y: w.position_y ?? getNextAvailableY(layout),
    w: w.width ?? 4,
    h: w.height ?? 3,
    static: false,
  });
});



  // ============================================================
  // 🔄 4️⃣ Guardar nuevo orden y tamaño
  // ============================================================
const handleLayoutChange = async (newLayout: any[]) => {
  // 🔹 Actualizar widgets
  const updatedWidgets = widgets.map((w) => {
    const l = newLayout.find((n) => n.i === String(w.id));
    return l
      ? { ...w, position_x: l.x, position_y: l.y, width: l.w, height: l.h }
      : w;
  });

  // 🔹 Actualizar secciones
  const updatedSections = sections.map((s) => {
    const l = newLayout.find((n) => n.i === `section-${s.id}`);
    return l
      ? { ...s, position: l.y, width: l.w, height: l.h }
      : s;
  });

  // 🔹 Guardar ambos en el estado local
  setWidgets(updatedWidgets);
  setSections(updatedSections);

  try {
    // 🔹 Enviar ambos tipos al backend
    const combinedLayout = newLayout.map((item) => ({
      i: item.i,
      x: item.x,
      y: item.y,
      w: item.w,
      h: item.h,
    }));

    await reorderWidgets(combinedLayout);
  } catch (e) {
    console.warn("⚠️ Error guardando reordenamiento:", e);
  }
};


const handleAddSection = async () => {
  const { value: title } = await MySwal.fire({
    title: "Nueva Sección",
    input: "text",
    inputLabel: "Título del bloque:",
    inputPlaceholder: "Ej. Tecnologías más demandadas",
    showCancelButton: true,
    confirmButtonText: "Crear",
    cancelButtonText: "Cancelar",
    inputValidator: (value) => {
      if (!value) return "Debes ingresar un título";
    },
  });

  if (!title) return;

  const nextY = getNextAvailableY([...sections, ...widgets]);

  try {
    await axios.post("/api/ai/dashboard-sections", {
      dashboard_id: 1,
      title,
      position: nextY,
      width: 12,
      height: 1,
    });

    const res = await axios.get("/api/ai/dashboard-sections/1");
    setSections(res.data.sections);

    MySwal.fire("✅ Sección creada", "Tu nueva sección fue agregada correctamente.", "success");
  } catch (e) {
    console.error("❌ Error creando sección:", e);
    MySwal.fire("Error", "No se pudo crear la sección.", "error");
  }
};



  // ============================================================
  // 🧩 5️⃣ Render de los widgets
  // ============================================================
  return (
    <div className="mt-6 pb-16">   {/* 👈 padding inferior agregado */}
    <div className="mb-4 flex justify-end">
  <button
    onClick={handleAddSection}
    className="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded shadow"
  >
    ➕ Nueva Sección
  </button>
</div>
<div id="dashboard-root" className="w-full px-6">
  <GridLayout
  className="layout w-full"
  layout={layout}
  cols={12}
  rowHeight={102}
  width={document.getElementById("dashboard-root")?.offsetWidth || window.innerWidth - 80} // 📏 dinámico
  onLayoutChange={handleLayoutChange}
  autoSize={true}
  compactType={null}
  verticalCompact={false}
  isBounded={true}   // evita que se salga del borde
  margin={[10, 15]} // margen entre ítems
  draggableHandle=".drag-handle"  // 👈 solo se arrastra desde este selector
>

    {[...sections, ...widgets].map((item) => {
      const isSection = item.title && item.position !== undefined;

     if (isSection) {
    return (
      <div
        key={`section-${item.id}`}
        data-grid={layout.find((l) => l.i === `section-${item.id}`)}
        className="bg-gray-900 border border-gray-700 rounded-lg p-6 flex flex-col justify-center items-center text-center w-full shadow-md hover:shadow-lg transition-all duration-200"
      >
        <h2 className="text-2xl font-bold text-blue-400 tracking-wide">
          {item.title}
        </h2>
        {item.description && (
          <p className="text-gray-400 text-sm mt-1">{item.description}</p>
        )}
<div className="absolute top-3 right-3 flex gap-2 z-10">
  {/* ✏️ Editar título */}
  <button
    onClick={async () => {
      const { value: newTitle } = await MySwal.fire({
        title: "Editar título de la sección",
        input: "text",
        inputValue: item.title,
        showCancelButton: true,
        confirmButtonText: "Guardar",
        cancelButtonText: "Cancelar",
        inputValidator: (value) => {
          if (!value) return "El título no puede estar vacío";
        },
      });

      if (!newTitle) return;
      try {
        await updateSection(item.id, { title: newTitle });
        setSections((prev) =>
          prev.map((s) => (s.id === item.id ? { ...s, title: newTitle } : s))
        );
        MySwal.fire("✅ Actualizado", "El título de la sección se cambió correctamente.", "success");
      } catch (e) {
        MySwal.fire("Error", "No se pudo actualizar la sección.", "error");
      }
    }}
    className="p-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-md"
    title="Editar título"
  >
    <Edit3 size={16} />
  </button>

  {/* 🗑️ Eliminar sección */}
  <button
    onClick={async () => {
      const result = await MySwal.fire({
        title: "¿Eliminar esta sección?",
        text: "Esta acción no se puede deshacer.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed) return;

      try {
        await deleteSection(item.id);
        setSections((prev) => prev.filter((s) => s.id !== item.id));
        MySwal.fire("Eliminada", "La sección fue eliminada correctamente.", "success");
      } catch (e) {
        MySwal.fire("Error", "No se pudo eliminar la sección.", "error");
      }
    }}
    className="p-1.5 bg-gray-700 hover:bg-gray-800 text-white rounded-md"
    title="Eliminar sección"
  >
    <Trash2 size={16} />
  </button>
</div>


        {/* 👇 Agregamos el área de arrastre */}
        <div
          className="absolute bottom-2 left-2 cursor-move opacity-50 hover:opacity-100 drag-handle"
          title="Mover sección"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="16"
            height="16"
            fill="currentColor"
            viewBox="0 0 16 16"
          >
            <circle cx="3" cy="3" r="1.5" />
            <circle cx="8" cy="3" r="1.5" />
            <circle cx="13" cy="3" r="1.5" />
            <circle cx="3" cy="8" r="1.5" />
            <circle cx="8" cy="8" r="1.5" />
            <circle cx="13" cy="8" r="1.5" />
            <circle cx="3" cy="13" r="1.5" />
            <circle cx="8" cy="13" r="1.5" />
            <circle cx="13" cy="13" r="1.5" />
          </svg>
        </div>

      </div>
    );
  }

      return (
        <div
          key={item.id}
          data-grid={layout.find((l) => l.i === String(item.id))}
          className="shadow-lg hover:shadow-xl transition-all duration-200"
        >
        <WidgetCard widget={item} onDelete={handleDeleteWidget} />

        </div>
      );
    })}
  </GridLayout>
</div>

    </div>
  );
}
