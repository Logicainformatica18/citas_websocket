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
import { updateWidget } from "./useDashboardAPI"; // 👈 ya tienes este método
// 🧩 Librerías para exportación
import * as XLSX from "xlsx";
import { saveAs } from "file-saver";

import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import { deleteWidget } from "./useDashboardAPI";
import Swal from "sweetalert2";


export default function WidgetCard({ widget, onColorChange, onDelete }) {
    const defaultColors = {
        bg: "#1e293b",     // fondo por defecto
        text: "#e2e8f0",   // texto gris claro
        border: "#334155", // borde sutil
        primary: "#1E88E5" // color del gráfico
    };
    const [title, setTitle] = useState(widget.title || "Sin título");
    const [dataKey, setDataKey] = useState("total_jobs_found");

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
  console.log(normalizedData.map(d => d.name));

  return () => document.removeEventListener("mousedown", handleClickOutside);
}, []);
    // 🔹 Datos crudos
const rawData = widget.data_source?.rows || [];

// 🧠 Normalización flexible y autodetección de claves
const { normalizedData, categoryKey, numericKeys } = React.useMemo(() => {
  if (!Array.isArray(rawData) || rawData.length === 0)
    return { normalizedData: [], categoryKey: "name", numericKeys: [] };

  const allKeys = Object.keys(rawData[0]);

  // ✅ Detectar categoría probable (la que mejor sirve como eje X)
  const possibleNameKeys = [
    "name", "modality", "language", "technology", "methodology",
    "career_name", "category", "country", "region", "city", "company", "workload"
  ];

  const categoryKey =
    allKeys.find((k) => possibleNameKeys.includes(k)) ||
    allKeys.find((k) => typeof rawData[0][k] === "string") ||
    "name";

  // ✅ Detectar columnas numéricas válidas (salarios, conteos, etc.)
  const numericKeys = allKeys.filter(
    (k) =>
      typeof rawData[0][k] === "number" ||
      k.toLowerCase().includes("total") ||
      k.toLowerCase().includes("count") ||
      k.toLowerCase().includes("salary") ||
      k.toLowerCase().includes("min") ||
      k.toLowerCase().includes("max")
  );

  // ✅ Normalizar datos para que todos los gráficos puedan usar "value"
const normalizedData = rawData.map((row) => {
  const base = {};

  // ✅ Mantén el campo real detectado como categoría
  base[categoryKey] =
    row[categoryKey] && row[categoryKey].toString().trim() !== ""
      ? row[categoryKey]
      : "Sin nombre";

  // ✅ Agrega todas las métricas numéricas
  numericKeys.forEach((k) => {
    base[k] = Number(row[k]) || 0;
  });

  // ✅ Si no hay ninguna métrica, agrega un valor por defecto
  if (numericKeys.length === 0) base["value"] = 1;

  return base;
});


  return { normalizedData, categoryKey, numericKeys };
}, [rawData]);


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


    // ============================================================
    // 📤 Funciones de exportación
 const exportToExcel = () => {
  const rows = widget.data_source?.rows || [];
  if (!rows.length) return alert("No hay datos para exportar");

  const ws = XLSX.utils.json_to_sheet(rows);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Datos");

  const buffer = XLSX.write(wb, { bookType: "xlsx", type: "array" });
  const blob = new Blob([buffer], { type: "application/octet-stream" });
  saveAs(blob, `${widget.title || "widget"}.xlsx`);
};



    const exportToPDF = () => {
        const rows = widget.data_source?.rows || [];
        if (!rows.length) {
            alert("No hay datos para exportar.");
            return;
        }

        // Crear PDF
        const pdf = new jsPDF("p", "mm", "a4");
        const pageWidth = pdf.internal.pageSize.getWidth();

        // Título
        pdf.setFontSize(16);
        pdf.text(widget.title || "Datos del Widget", pageWidth / 2, 20, { align: "center" });

        // Preparar columnas y filas
        const firstRow = rows[0];
        const columns = Object.keys(firstRow);
        const data = rows.map((r) => columns.map((c) => r[c] ?? ""));

        // 👇 Usa la función importada directamente
        autoTable(pdf, {
            startY: 30,
            head: [columns],
            body: data,
            styles: { fontSize: 8, cellPadding: 2 },
            headStyles: {
                fillColor: [30, 136, 229],
                textColor: 255,
                fontStyle: "bold",
            },
            alternateRowStyles: { fillColor: [240, 240, 240] },
            margin: { left: 10, right: 10 },
        });

        // Guardar
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

            <div className="h-[260px]">
{widget.chart_type === "bar" && (
  <div
    style={{
      width: "100%",
      height: "100%",
      display: "flex",
      flexDirection: "column",
    }}
  >
    {(() => {
      // 🔍 DEPURACIÓN: imprime toda la info antes del render
      console.log("🟦 [BAR DEBUG] Datos normalizados:", normalizedData);
      console.log("🟩 [BAR DEBUG] categoryKey:", categoryKey);
      console.log("🟨 [BAR DEBUG] numericKeys:", numericKeys);
      console.log(
        "🟪 [BAR DEBUG] Nombres detectados:",
        normalizedData.map((d) => d[categoryKey])
      );
      return null;
    })()}

    <ResponsiveContainer width="100%" height={320}>
      <BarChart
        data={normalizedData}
        margin={{ top: 10, right: 10, left: 10, bottom: 100 }} // 👈 espacio extra para etiquetas
      >
        <CartesianGrid strokeDasharray="3 3" stroke={colors.border} />

        <XAxis
          dataKey={categoryKey}
          stroke={colors.text}
          tick={{
            fill: colors.text,
            fontSize: 11,
            dy: 10, // 👈 pequeño margen para separar el texto
          }}
          interval={0} // 👈 muestra todos los labels sin saltar
          angle={-45} // 👈 inclina etiquetas
          textAnchor="end"
          height={80}
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

        <Bar
          dataKey={numericKeys[0] || "value"}
          isAnimationActive={true}
          onClick={(data, index) => {
            console.log("🧩 Click en barra:", {
              index,
              label: data[categoryKey],
              value: data[numericKeys[0] || "value"],
            });
          }}
        >
          {normalizedData.map((entry, index) => (
            <Cell
              key={`cell-${index}`}
              fill={`hsl(${(index * 25) % 360}, 70%, 55%)`}
            />
          ))}
        </Bar>
      </BarChart>
    </ResponsiveContainer>

    {/* ✅ Leyenda manual igual que el pie chart */}
    <div
      style={{
        display: "flex",
        flexWrap: "wrap",
        justifyContent: "center",
        alignItems: "center",
        gap: "8px 12px",
        paddingTop: "8px",
        maxHeight: "80px",
        overflowY: "auto",
      }}
    >
      {normalizedData.length > 0 ? (
        normalizedData.slice(0, 20).map((entry, index) => (
          <div
            key={index}
            style={{
              display: "flex",
              alignItems: "center",
              fontSize: "12px",
              color: colors.text,
              whiteSpace: "nowrap",
            }}
          >
            <span
              style={{
                display: "inline-block",
                width: "10px",
                height: "10px",
                backgroundColor: `hsl(${(index * 25) % 360}, 70%, 55%)`,
                marginRight: "5px",
                borderRadius: "2px",
              }}
            />
            {entry[categoryKey]}
          </div>
        ))
      ) : (
        <span style={{ color: colors.text, opacity: 0.5 }}>Sin datos</span>
      )}
    </div>
  </div>
)}







                {widget.chart_type === "line" && (
                    <ResponsiveContainer width="100%" height="100%">
                     <LineChart data={normalizedData}>
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
    data={normalizedData}
    dataKey={numericKeys[0] || "value"}
    nameKey={categoryKey}
    cx="35%"
    cy="50%"
    outerRadius="80%"
    label={({ name, percent }) => `${name} ${(percent * 100).toFixed(1)}%`}
  >
    {normalizedData.map((entry, index) => (
      <Cell key={index} fill={`hsl(${(index * 45) % 360}, 70%, 55%)`} />
    ))}
  </Pie>
  <Tooltip />
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
    onClick={() => setMenuOpen(!menuOpen)}
    className="p-1.5 bg-gray-700 hover:bg-gray-800 text-white rounded-md transition"
    title="Opciones del widget"
  >
    ⚙️
  </button>

  {menuOpen && (
    <div className="absolute right-0 mt-2 bg-gray-800 border border-gray-700 rounded-md shadow-lg w-48 animate-fadeIn z-20">
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

        </div>
    );
}
