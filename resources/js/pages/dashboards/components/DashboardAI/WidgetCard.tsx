import React, { useState, useEffect, useRef } from "react";
import {
    BarChart,
    Bar,
    LineChart,
    Line,
    PieChart,
    Pie,
    CartesianGrid,
    XAxis,
    YAxis,
    Tooltip,
    Legend,
    ResponsiveContainer,
    Cell, // 👈 importante para los colores aleatorios
} from "recharts";

import ColorControl from "./ColorControl";
import { FileSpreadsheet, FileDown, Trash2 } from "lucide-react";
import { updateWidget,segmentWidget } from "./useDashboardAPI"; // 👈 ya tienes este método


// 🧩 Librerías para exportación
import * as XLSX from "xlsx";
import { saveAs } from "file-saver";

import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import { deleteWidget } from "./useDashboardAPI";
import Swal from "sweetalert2";
import ChartFilter from "./ChartFilter";


export default function WidgetCard({ widget, onColorChange, onDelete }) {
    const defaultColors = {
        bg: "#1e293b",     // fondo por defecto
        text: "#e2e8f0",   // texto gris claro
        border: "#334155", // borde sutil
        primary: "#1E88E5" // color del gráfico
    };
    const [title, setTitle] = useState(widget.title || "Sin título");
    const [dataKey, setDataKey] = useState("total_jobs_found");
const [rows, setRows] = useState(widget.data_source?.rows || []);
const [loadingSegment, setLoadingSegment] = useState(false);


    const [colors, setColors] = useState({
        ...defaultColors,
        ...(typeof widget.colors === "string"
            ? JSON.parse(widget.colors)
            : widget.colors || {})
    });

    const handleDelete = async () => {
        const result = await Swal.fire({
            title: "¿Eliminar tarjeta?",
            text: "Esta acción no se puede deshacer.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Sí, eliminar",
            cancelButtonText: "Cancelar",
            confirmButtonColor: "#e53935",
        });

        if (!result.isConfirmed) return;

        try {
            await deleteWidget(widget.id);
            Swal.fire("Eliminada", "La tarjeta fue eliminada correctamente", "success");
            if (onDelete) onDelete(widget.id);
        } catch (err) {
            Swal.fire("Error", "No se pudo eliminar la tarjeta", "error");
        }
    };

    useEffect(() => {
        if (widget.colors) {
            const newColors =
                typeof widget.colors === "string"
                    ? JSON.parse(widget.colors)
                    : widget.colors;

            setColors((prev) => ({
                ...prev,
                ...newColors,
            }));
        }
    }, [widget.colors]);

const [menuOpen, setMenuOpen] = useState(false);
const menuRef = useRef(null);
useEffect(() => {
  const handleClickOutside = (e) => {
    if (menuRef.current && !menuRef.current.contains(e.target)) {
      setMenuOpen(false);
    }
  };
  document.addEventListener("mousedown", handleClickOutside);
//   console.log(normalizedData.map(d => d.name));

  return () => document.removeEventListener("mousedown", handleClickOutside);
}, []);
// 🔹 Aseguramos que siempre haya un array nuevo
const rawData = Array.isArray(rows) ? [...rows] : [];


const { normalizedData, categoryKey, numericKeys } = React.useMemo(() => {
  // 🧩 1️⃣ Validación de datos reales
  if (!Array.isArray(rawData) || rawData.length === 0 || !rawData[0])
    return { normalizedData: [], categoryKey: "name", numericKeys: [] };

  const allKeys = Object.keys(rawData[0] || {});
  if (allKeys.length === 0)
    return { normalizedData: [], categoryKey: "name", numericKeys: [] };

  // 🧩 2️⃣ Detección de categoría
  const possibleNameKeys = [
    "name", "modality", "language", "technology", "methodology",
    "career_name", "category", "country", "region", "city", "company", "workload"
  ];

  const categoryKey =
    allKeys.find((k) => possibleNameKeys.includes(k)) ||
    allKeys.find((k) => typeof rawData[0][k] === "string") ||
    allKeys[0];

  // 🧩 3️⃣ Detección numérica (tolerante)
const numericKeys = allKeys.filter((k) => {
  // ❌ No incluir la categoría detectada
  if (k === categoryKey) return false;

  const val = rawData[0][k];
  const num = parseFloat(val);

  return (
    (typeof val === "number" && !isNaN(val)) ||
    (!isNaN(num) && isFinite(num)) ||
    k.toLowerCase().includes("total") ||
    k.toLowerCase().includes("count") ||
    k.toLowerCase().includes("salary") ||
    k.toLowerCase().includes("min") ||
    k.toLowerCase().includes("max")
  );
});


  // 🧩 4️⃣ Normalización robusta
  const normalizedData = rawData.map((row) => {
    const obj = {};
    obj[categoryKey] = row[categoryKey]?.toString().trim() || "Sin nombre";

    numericKeys.forEach((k) => {
      const val = row[k];
      const num = parseFloat(val);
      obj[k] = !isNaN(num) && isFinite(num) ? num : 0;
    });

    return obj;
  });

//   console.log("🧩 Normalización:", {
//     categoryKey,
//     numericKeys,
//     muestra: normalizedData[0],
//   });

  return { normalizedData, categoryKey, numericKeys };
}, [JSON.stringify(rawData)]);

// 🎛️ Filtro dinámico de categorías
const categoryLabels = normalizedData.map(d => d[categoryKey]);

const [activeLabels, setActiveLabels] = useState(categoryLabels);

useEffect(() => {
  setActiveLabels(categoryLabels);
}, [JSON.stringify(categoryLabels)]);

const toggleLabel = (label) => {
  setActiveLabels(prev =>
    prev.includes(label)
      ? prev.filter((l) => l !== label)
      : [...prev, label]
  );
};

const filteredData = normalizedData.filter(d =>
  activeLabels.includes(d[categoryKey])
);

// // 🧩 Log seguro (ya después del cálculo)
// useEffect(() => {
//   console.table(rawData.slice(0, 3));
//   console.log("🧩 Normalización:", {
//     categoryKey,
//     numericKeys,
//     muestra: normalizedData[0],
//   });
// }, [categoryKey, numericKeys, normalizedData]);



    const handleColorChange = async (newColor) => {
        const newColors = { ...colors, primary: newColor };
        setColors(newColors);

        if (onColorChange) onColorChange(widget.id, newColor);

        try {
          await updateWidget(widget.id, { colors: JSON.stringify(newColors) });

            console.log("✅ Colores actualizados en backend:", newColors);
        } catch (err) {
            console.warn("⚠️ Error guardando color:", err);
        }
    };
const handleSegment = async () => {
  setLoadingSegment(true);

  // 👇 Por ahora puedes probar con filtros fijos (luego serán dinámicos)
  const filters = {
    modality: ["Remote", "Hybrid"],
    experience_level: ["Junior", "Mid"],
    currency: ["USD"],
    country: "Peru",
  };

  try {
    const res = await segmentWidget(widget.id, filters);
    if (res.rows && res.rows.length > 0) {
      setRows(res.rows);
      Swal.fire("✅ Datos actualizados", "La segmentación se aplicó correctamente.", "success");
    } else {
      Swal.fire("⚠️ Sin resultados", "No se encontraron datos con esos filtros.", "warning");
    }
    console.log("🧩 SQL Final:", res.sql_final);
  } catch (err) {
    console.error("💥 Error segmentando widget:", err);
    Swal.fire("❌ Error", "No se pudo aplicar la segmentación.", "error");
  } finally {
    setLoadingSegment(false);
  }
};


    // ============================================================
    // 📤 Funciones de exportación
const exportToExcel = () => {
  if (!rows.length) return alert("No hay datos para exportar");

  const ws = XLSX.utils.json_to_sheet(rows);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Datos");

  const buffer = XLSX.write(wb, { bookType: "xlsx", type: "array" });
  const blob = new Blob([buffer], { type: "application/octet-stream" });
  saveAs(blob, `${widget.title || "widget"}.xlsx`);
};

const exportToPDF = () => {
  if (!rows.length) {
    alert("No hay datos para exportar.");
    return;
  }

  const pdf = new jsPDF("p", "mm", "a4");
  const pageWidth = pdf.internal.pageSize.getWidth();
  pdf.setFontSize(16);
  pdf.text(widget.title || "Datos del Widget", pageWidth / 2, 20, { align: "center" });

  const firstRow = rows[0];
  const columns = Object.keys(firstRow);
  const data = rows.map((r) => columns.map((c) => r[c] ?? ""));

  autoTable(pdf, {
    startY: 30,
    head: [columns],
    body: data,
    styles: { fontSize: 8, cellPadding: 2 },
    headStyles: { fillColor: [30, 136, 229], textColor: 255, fontStyle: "bold" },
    alternateRowStyles: { fillColor: [240, 240, 240] },
    margin: { left: 10, right: 10 },
  });

  pdf.save(`${widget.title || "widget"}.pdf`);
};






    return (
        <div
            id={`widget-${widget.id}`}
            className="p-4 rounded-lg shadow relative transition-all duration-200"
            style={{
                backgroundColor: colors.bg,
                color: colors.text,
                border: `2px solid ${colors.border}`
            }}
        >

            <h3
                className="font-semibold text-lg mb-2"
                style={{ color: colors.text }}
            >

                {widget.title || "Sin título"}
            </h3>

            <div className="h-[360px]">
{widget.chart_type === "bar" && (
  <div
    style={{
      width: "100%",
      height: "100%",
      display: "flex",
      flexDirection: "column",
      justifyContent: "space-between",
    }}
  >


    {/* 🧩 Gráfico principal */}
    <div style={{ flex: 1, minHeight: "240px" }}>
      <ResponsiveContainer width="100%" height="100%">
        <BarChart
         data={filteredData}

          margin={{ top: 10, right: 10, left: 10, bottom: 10 }}
        >
          <CartesianGrid strokeDasharray="3 3" stroke={colors.border} />

          <XAxis
            dataKey={categoryKey}
            stroke={colors.text}
            tick={false}     // sin texto en eje X
            axisLine={false} // sin línea base
          />
          <YAxis
            stroke={colors.text}
            tick={{ fill: colors.text, fontSize: 11 }}
          />
          <Tooltip
            contentStyle={{
              backgroundColor: colors.bg,
              border: `1px solid ${colors.border}`,
            }}
            labelStyle={{ color: colors.text }}
            itemStyle={{ color: colors.text }}
          />
          <Bar dataKey={numericKeys[0] || "value"} isAnimationActive={true}>
            {normalizedData.map((entry, index) => (
              <Cell
                key={`cell-${index}`}
                fill={`hsl(${(index * 25) % 360}, 70%, 55%)`}
              />
            ))}
          </Bar>
        </BarChart>
      </ResponsiveContainer>
    </div>

    {/* 🎨 Leyenda debajo del gráfico */}
   <div
  style={{
    display: "flex",
    flexWrap: "wrap",
    justifyContent: "center",
    alignItems: "center",
    gap: "10px 18px",
    paddingTop: "10px",
    maxHeight: "110px",
    overflowY: "auto",
  }}
>
  {normalizedData.map((entry, index) => {
    const label = entry[categoryKey];
    const color = `hsl(${(index * 25) % 360}, 70%, 55%)`;

    return (
      <label
        key={label}
        style={{
          display: "flex",
          alignItems: "center",
          fontSize: "13px",
          color: colors.text,
          cursor: "pointer",
          whiteSpace: "nowrap",
        }}
      >
        {/* Checkbox */}
        <input
          type="checkbox"
          checked={activeLabels.includes(label)}
          onChange={() => toggleLabel(label)}
          style={{ marginRight: "6px" }}
        />

        {/* Color cuadrado */}
        <span
          style={{
            display: "inline-block",
            width: "12px",
            height: "12px",
            backgroundColor: color,
            marginRight: "6px",
            borderRadius: "3px",
          }}
        />

        {label}
      </label>
    );
  })}
</div>

  </div>
)}







                {widget.chart_type === "line" && (
                    <ResponsiveContainer width="100%" height="100%">
                     <LineChart data={filteredData}>
  <CartesianGrid strokeDasharray="3 3" stroke={colors.border} />
  <XAxis dataKey={categoryKey} stroke={colors.text} tick={{ fill: colors.text }} />
  <YAxis stroke={colors.text} tick={{ fill: colors.text }} />
  <Tooltip contentStyle={{ backgroundColor: colors.bg }} />
  <Legend wrapperStyle={{ color: colors.text }} />
  {numericKeys.map((key, i) => (
    <Line
      key={key}
      type="monotone"
      dataKey={key}
      stroke={`hsl(${(i * 50) % 360}, 70%, 55%)`}
      strokeWidth={2}
    />
  ))}
</LineChart>


                    </ResponsiveContainer>
                )}

{widget.chart_type === "pie" && (
  <ResponsiveContainer width="100%" height="100%">
    <PieChart>
      <Pie
       data={filteredData}

        dataKey={numericKeys[0] || "value"}
        nameKey={categoryKey}
        cx="35%"
        cy="50%"
        outerRadius="80%"
        // 👇 Label solo con porcentaje
        label={({ percent }) => `${(percent * 100).toFixed(1)}%`}
      >
        {filteredData.map((entry, index) => (
          <Cell key={index} fill={`hsl(${(index * 45) % 360}, 70%, 55%)`} />
        ))}
      </Pie>

      <Tooltip
        formatter={(value: number, name: string, props: any) => {
          const total = normalizedData.reduce(
            (sum, item) => sum + item[numericKeys[0] || "value"],
            0
          );
          const percent = ((value / total) * 100).toFixed(1) + "%";
          return [`${value} (${percent})`, name];
        }}
      />

      <Legend layout="vertical" align="right" verticalAlign="middle" />
    </PieChart>
  </ResponsiveContainer>
)}






            </div>

            {/* 🎨 Control de color */}
            {/* <div className="absolute bottom-3 right-3">
                <ColorControl widget={widget} onChangeColor={handleColorChange} />
            </div> */}

          {/* ⚙️ Menú compacto con clic */}
{/* ⚙️ Menú con icono (arriba a la derecha) */}
<div className="absolute top-3 right-3" ref={menuRef}>
<button
  className="
    no-drag
    relative z-50
    p-1.5
    bg-gray-700 hover:bg-gray-800
    text-white rounded-md transition
  "
  onClick={(e) => {
    e.stopPropagation();   // ⛔ evita que el click suba al widget
    e.preventDefault();    // ⛔ evita drag del grid
    setMenuOpen((v) => !v); // ✅ abre menú
  }}
  title="Opciones del widget"
>
  ⚙️
</button>



  {menuOpen && (
  <div className="no-drag absolute right-0 mt-2 bg-gray-800 border border-gray-700 rounded-md shadow-lg w-48 animate-fadeIn z-50">

      <button
        onClick={() => {
          exportToExcel();
          setMenuOpen(false);
        }}
        className="block w-full text-left px-3 py-2 hover:bg-gray-700 text-green-400 text-sm"
      >
        📊 Exportar Excel
      </button>

      <button
        onClick={() => {
          exportToPDF();
          setMenuOpen(false);
        }}
        className="block w-full text-left px-3 py-2 hover:bg-gray-700 text-red-400 text-sm"
      >
        📄 Exportar PDF
      </button>
{/* <button
  onClick={async () => {
    setMenuOpen(false);
    await handleSegment();
  }}
  className="block w-full text-left px-3 py-2 hover:bg-gray-700 text-cyan-400 text-sm"
>
  🧠 Segmentar datos
</button> */}

      <button
        onClick={() => {
          handleDelete();
          setMenuOpen(false);
        }}
        className="block w-full text-left px-3 py-2 hover:bg-gray-700 text-gray-300 text-sm"
      >
        🗑️ Eliminar
      </button>

      <button
        onClick={async () => {
          setMenuOpen(false);
          Swal.fire({
            title: "🎨 Personalizar colores",
            html: `
              <label>Fondo: <input type="color" id="bgColor" value="${colors.bg}" /></label><br/>
              <label>Texto: <input type="color" id="textColor" value="${colors.text}" /></label><br/>
              <label>Borde: <input type="color" id="borderColor" value="${colors.border}" /></label><br/>
              <label>Gráfico: <input type="color" id="primaryColor" value="${colors.primary}" /></label>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: "Guardar",
            preConfirm: () => ({
              bg: document.getElementById("bgColor").value,
              text: document.getElementById("textColor").value,
              border: document.getElementById("borderColor").value,
              primary: document.getElementById("primaryColor").value,
            }),
          }).then(async (result) => {
            if (result.isConfirmed) {
              const newColors = result.value;
              setColors(newColors);
              await updateWidget(widget.id, { colors: newColors });
              Swal.fire("✅ Guardado", "Colores actualizados correctamente.", "success");
            }
          });
        }}
        className="block w-full text-left px-3 py-2 hover:bg-gray-700 text-blue-400 text-sm"
      >
        🎨 Editar colores
      </button>

      <button
        onClick={() => {
          setMenuOpen(false);
          Swal.fire({
            title: "📊 Cambiar tipo de gráfico",
            html: `
              <select id="chartType" class="swal2-select" style="width:100%;background-color:#0f172a;color:#f1f5f9;border-radius:8px;border:1px solid #475569;padding:10px;">
                <option value="bar" ${widget.chart_type === "bar" ? "selected" : ""}>Barras</option>
                <option value="line" ${widget.chart_type === "line" ? "selected" : ""}>Líneas</option>
                <option value="pie" ${widget.chart_type === "pie" ? "selected" : ""}>Circular</option>
              </select>
            `,
            background: "#0f172a",
            color: "#f1f5f9",
            showCancelButton: true,
            confirmButtonText: "Guardar",
            cancelButtonText: "Cancelar",
            confirmButtonColor: "#2563eb",
            cancelButtonColor: "#475569",
            preConfirm: () => document.getElementById("chartType").value,
          }).then(async (result) => {
            if (result.isConfirmed) {
              const newType = result.value;
              try {
                await updateWidget(widget.id, { chart_type: newType });
                widget.chart_type = newType;
                Swal.fire("✅ Actualizado", "El tipo de gráfico ha sido cambiado.", "success");
              } catch (err) {
                Swal.fire("❌ Error", "No se pudo actualizar el tipo de gráfico.", "error");
              }
            }
          });
        }}
        className="block w-full text-left px-3 py-2 hover:bg-gray-700 text-purple-400 text-sm"
      >
        🔁 Cambiar tipo
      </button>
    </div>
  )}
</div>


            <div
                className="absolute bottom-2 left-2 cursor-move opacity-50 hover:opacity-100 drag-handle"
                title="Mover widget"
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
{/* 🖱️ Esquina inferior derecha para redimensionar */}
{/* 🖱️ Decorativo, sin bloquear el handle del grid */}
<div
  style={{
    position: "absolute",
    bottom: "6px",
    right: "6px",
    width: "18px",
    height: "18px",
    background: "rgba(255,255,255,0.15)",
    borderRadius: "4px",
    pointerEvents: "none", // 👈 clave: no bloquea el handle real
  }}
/>


        </div>
    );
}
