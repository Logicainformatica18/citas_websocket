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
import { router } from "@inertiajs/react";

import ColorControl from "./ColorControl";
import { FileSpreadsheet, FileDown, Trash2 } from "lucide-react";
import {
    updateWidget,
    segmentWidget,
    saveWidgetFilters,
} from "./useDashboardAPI";


// 🧩 Librerías para exportación
import * as XLSX from "xlsx";
import { saveAs } from "file-saver";

import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import { deleteWidget } from "./useDashboardAPI";
import Swal from "sweetalert2";
import ChartFilter from "./ChartFilter";
const LOVABLE_ISIL_SCALE = [
    "#1CBCE8",
    "#3EC7EE",
    "#5DD6F4",
    "#7FE3F8",
    "#A7EDF9",
    "#C7F5FC",
];
const BALANCED_PIE_BASE = [
  "#38BDF8", // azul
  "#4ADE80", // verde
  "#FACC15", // amarillo
  "#A78BFA", // violeta
  "#FCA5A5", // rojo suave
  "#22D3EE", // cian
  "#FDBA74", // naranja suave
  "#60A5FA", // azul profundo
  "#34D399", // verde agua
];

const generateSoftPieColors = (
  palette: string[],
  count: number
) => {
  return Array.from(
    { length: count },
    (_, i) => palette[i % palette.length]
  );
};



const SOFT_PIE_PALETTE = [
    "#7DD3FC", // azul claro
    "#38BDF8", // azul
    "#A5F3FC", // celeste
    "#4ADE80", // verde
    "#FACC15", // amarillo
    "#C084FC", // violeta
    "#FCA5A5", // rojo suave
    "#FDBA74", // naranja suave
    "#94A3B8", // gris azulado
];

const LOVABLE_DEFAULT_PALETTE = [
    "#1CBCE8",
    "#38BDF8",
    "#0EA5E9",
    "#7DD3FC",
    "#0284C7",
    "#A5F3FC",
    "#4ADE80",
    "#FACC15",
    "#C084FC",
    "#0F766E",
    "#22B8E8",
    "#60A5FA",
    "#0D9488",
    "#93C5FD",
    "#0F766E",
    "#BAE6FD",
    "#22C55E",
    "#FDE047",
    "#D8B4FE",
    "#083344",
];

import { useLovableChartTheme } from "@/hooks/useLovableChartTheme";
const humanizeMetric = (key?: string) => {
    if (!key) return "";

    return key
        .replace(/_/g, " ")
        .replace(/([a-z])([A-Z])/g, "$1 $2")
        .replace(/\b\w/g, (l) => l.toUpperCase());
};
const PieTooltip = ({ active, payload, theme, metricKey, total }) => {
    if (!active || !payload?.length || total <= 0) return null;

    const item = payload[0];
    const percent = ((item.value / total) * 100).toFixed(1);

    return (
        <div
            style={{
                background: theme.tooltipBg,
                border: `1px solid ${theme.border}`,
                borderRadius: 12,
                padding: "10px 14px",
                color: theme.text,
                boxShadow: "0 10px 25px rgba(0,0,0,0.35)",
                fontSize: 13,
            }}
        >
            <div style={{ fontWeight: 600, marginBottom: 4 }}>
                {item.name}
            </div>

            <div style={{ color: item.color }}>
                {humanizeMetric(metricKey)}: <strong>{item.value}</strong>
            </div>

            <div style={{ opacity: 0.75 }}>
                {percent}%
            </div>
        </div>
    );
};


const UniversalTooltip = ({ active, payload, label, metricKey, theme }) => {
    if (!active || !payload?.length) return null;

    return (
        <div
            style={{
                background: theme.tooltipBg,
                border: `1px solid ${theme.border}`,
                borderRadius: 12,
                padding: "10px 14px",
                color: theme.text,
                boxShadow: "0 10px 25px rgba(0,0,0,0.35)",
                fontSize: 13,
            }}
        >
            <div style={{ fontWeight: 600, marginBottom: 4 }}>
                {label}
            </div>

            {payload.map((p, i) => (
                <div key={i} style={{ color: p.color || "#38BDF8" }}>
                    {humanizeMetric(metricKey)}:{" "}
                    <strong>{p.value}</strong>
                </div>
            ))}
        </div>
    );
};

const DynamicBarTooltip = ({ active, payload, label, metricKey }) => {
    if (!active || !payload?.length) return null;

    return (
        <div
            style={{
                background: "#020617",
                border: "1px solid #334155",
                borderRadius: 12,
                padding: "10px 14px",
                color: "#E5F3F9",
                boxShadow: "0 10px 25px rgba(0,0,0,0.45)",
                fontSize: 13,
            }}
        >
            <div style={{ fontWeight: 600, marginBottom: 4 }}>
                {label}
            </div>

            <div style={{ color: "#38BDF8" }}>
                {humanizeMetric(metricKey)}:{" "}
                <strong>{payload[0].value}</strong>
            </div>
        </div>
    );
};



export default function WidgetCard({
    widget,
    dashboardId,
    onColorChange,
    onDelete,
}) {



    const theme = useLovableChartTheme();
    const isDark = theme.mode === "dark";

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


    // 🔒 Leer filtros guardados del widget (persistencia)
    // 🔒 Leer filtros guardados del widget (persistencia)
    const savedOptions =
        typeof widget.options === "string"
            ? JSON.parse(widget.options)
            : widget.options;

    const savedActiveLabels: string[] =
        savedOptions?.filters?.activeLabels || [];

    // 🧠 Summary normalizado (backend-agnostic)
    const resolvedSummary =
        widget.summary ||
        widget.data_source?.summary ||
        "";

    const summary = widget.data_source?.summary;


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
            await deleteWidget(
                Number(widget.dashboard_id),
                Number(widget.id)
            );

            await Swal.fire(
                "Eliminada",
                "La tarjeta fue eliminada correctamente",
                "success"
            );

            // 🔄 RECARGA TOTAL (la forma más segura)
            window.location.reload();

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

    const colorMap = React.useMemo(() => {
        const map = {};
        normalizedData.forEach((entry, i) => {
            const key = entry[categoryKey];
            if (!map[key]) {
                map[key] =
                    LOVABLE_DEFAULT_PALETTE[i % LOVABLE_DEFAULT_PALETTE.length];
            }
        });
        return map;
    }, [normalizedData, categoryKey]);


    // 🎛️ Filtro dinámico de categorías
    const categoryLabels = normalizedData.map(d => d[categoryKey]);

    const [activeLabels, setActiveLabels] = useState<string[]>(
        savedActiveLabels.length ? savedActiveLabels : categoryLabels
    );


    useEffect(() => {
        if (savedActiveLabels.length) {
            setActiveLabels(savedActiveLabels);
        } else {
            setActiveLabels(categoryLabels);
        }
    }, [JSON.stringify(categoryLabels)]);



    const filteredData = normalizedData.filter(d =>
        activeLabels.includes(d[categoryKey])
    );
    // 🧮 TOTAL REAL para porcentajes del PIE
    const pieTotal = React.useMemo(() => {
        if (!numericKeys.length) return 0;

        return filteredData.reduce(
            (sum, item) => sum + (Number(item[numericKeys[0]]) || 0),
            0
        );
    }, [filteredData, numericKeys]);
    // 🎨 Paleta dinámica HSL (colores infinitos, sin repetir)
    const pieColors = React.useMemo(() => {
  return generateSoftPieColors(
    BALANCED_PIE_BASE,
    filteredData.length,
    isDark
  );
}, [filteredData.length, isDark]);



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
          await updateWidget(
    Number(widget.dashboard_id),
    Number(widget.id),
    { colors: JSON.stringify(newColors) }
);



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
            className="p-5 pb-12 rounded-xl relative"
            style={{
  backgroundColor: theme.cardBg,
  border: `1px solid ${theme.border}`,
}}

        >


            <h3
                className="text-sm font-semibold tracking-wide mb-1"
                style={{
                    color: theme.text,
                    textTransform: "uppercase",
                }}
            >
                {widget.title}
            </h3>

            {resolvedSummary && (
                <p
                    className="text-xs mb-3 leading-snug"
                    style={{
                        color: "#4FB3D9",
                        maxWidth: "95%",
                    }}
                >
                    {resolvedSummary}
                </p>

            )}




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
                                    barCategoryGap={18}
                                    barGap={4}
                                >

                                    <CartesianGrid
                                        stroke={theme.grid}          // 🔥
                                        strokeDasharray="4 6"
                                        vertical={false}
                                    />




                                    <XAxis
                                        dataKey={categoryKey}
                                        tick={{ fill: theme.text, fontSize: 11 }} // 🔥
                                        axisLine={false}
                                        tickLine={false}
                                        angle={-30}
                                        textAnchor="end"
                                        height={60}
                                    />



                                    <YAxis
                                        tick={{ fill: theme.text, fontSize: 11 }}
                                        axisLine={false}
                                        tickLine={false}
                                    />


                                    <Tooltip
                                        content={(props) => (
                                            <UniversalTooltip
                                                {...props}
                                                metricKey={numericKeys[0]}
                                                theme={theme}
                                            />
                                        )}
                                        cursor={{ fill: "rgba(255,255,255,0.04)" }}
                                    />





                                    <Bar dataKey={numericKeys[0]} radius={[6, 6, 0, 0]}>
                                        {filteredData.map((_, index) => (
                                            <Cell
                                                key={index}
                                                fill={LOVABLE_DEFAULT_PALETTE[index % LOVABLE_DEFAULT_PALETTE.length]}
                                            />
                                        ))}
                                    </Bar>







                                </BarChart>
                            </ResponsiveContainer>
                        </div>

                        {/* 🎨 Leyenda (solo visual, refleja lo seleccionado) */}
                        <div
                            style={{
                                display: "flex",
                                flexWrap: "wrap",
                                justifyContent: "center",
                                gap: "10px 18px",
                                paddingTop: "10px",
                            }}
                        >
                            {filteredData.map((entry, index) => {
                                const label = entry[categoryKey];
                                const color =
                                    LOVABLE_DEFAULT_PALETTE[index % LOVABLE_DEFAULT_PALETTE.length];

                                return (
                                    <div
                                        key={label}
                                        style={{
                                            display: "flex",
                                            alignItems: "center",
                                            fontSize: "13px",
                                            color: theme.text,
                                            whiteSpace: "nowrap",
                                        }}
                                    >
                                        <span
                                            style={{
                                                width: 12,
                                                height: 12,
                                                backgroundColor: color,
                                                borderRadius: 4,
                                                marginRight: 6,
                                            }}
                                        />
                                        {label}
                                    </div>
                                );
                            })}
                        </div>


                    </div>
                )}







                {widget.chart_type === "line" && (
                    <ResponsiveContainer width="100%" height="100%">
                        <LineChart data={filteredData}>
                            <CartesianGrid stroke={theme.grid} />
                            <XAxis
                                dataKey={categoryKey}
                                tick={{ fill: "#0A4E61", fontSize: 11 }}
                                axisLine={false}
                                tickLine={false}
                            />

                            <YAxis
                                tick={{ fill: "#0A4E61", fontSize: 11 }}
                                axisLine={false}
                                tickLine={false}
                            />

                            <Tooltip
                                content={(props) => (
                                    <UniversalTooltip
                                        {...props}
                                        metricKey={numericKeys[0]}
                                        theme={theme}
                                    />
                                )}
                            />

                            <Legend wrapperStyle={{ color: theme.text }} />
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
  <div
    style={{
      width: "100%",
      height: "100%",
      display: "flex",
      flexDirection: "column",
    }}
  >
    {/* 📊 Gráfico */}
    <div style={{ flex: 1, minHeight: 220 }}>
      <ResponsiveContainer width="100%" height="100%">
        <PieChart>
          <Pie
            data={filteredData}
            dataKey={numericKeys[0]}
            nameKey={categoryKey}
            cx="50%"
            cy="50%"
            innerRadius={55}
            outerRadius={95}
            paddingAngle={3}
          >
            {filteredData.map((entry, index) => (
              <Cell
                key={`pie-${entry[categoryKey]}-${index}`}
                fill={pieColors[index]}
                stroke={theme.cardBg}
                strokeWidth={2}
              />
            ))}
          </Pie>

          <Tooltip
            content={(props) => (
              <PieTooltip
                {...props}
                metricKey={numericKeys[0]}
                theme={theme}
                total={pieTotal}
              />
            )}
          />
        </PieChart>
      </ResponsiveContainer>
    </div>

    {/* 🎨 Leyenda custom */}
   <div
  style={{
    display: "flex",
    flexWrap: "wrap",
    justifyContent: "center",
    gap: "10px 18px",
    paddingTop: 12,
  }}
>
 {filteredData.map((entry, index) => {
  const label = entry[categoryKey];
  const color = pieColors[index];

  return (
    <div
      key={`pie-legend-${label}`}
      style={{
        display: "flex",
        alignItems: "center",
        gap: 8,
        fontSize: 13,
        color: theme.text,
        fontWeight: 500,
      }}
    >
      <span
        style={{
          width: 10,
          height: 10,
          borderRadius: "50%",
          backgroundColor: color,
        }}
      />
      {label}
    </div>
  );
})}

</div>


  </div>
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
                                setMenuOpen(false);

                                const isDark =
                                    document.documentElement.classList.contains("dark") ||
                                    document.body.classList.contains("dark");

                                let selectedType = widget.chart_type; // 🔥 estado local del modal

                                Swal.fire({
                                    title: "Cambiar tipo de gráfico",
                                    background: isDark ? "#0B1220" : "#F8FCFE",
                                    color: isDark ? "#E5F3F9" : "#0A4E61",
                                    showCancelButton: true,
                                    confirmButtonText: "Guardar",
                                    cancelButtonText: "Cancelar",
                                    confirmButtonColor: "#1CBCE8",
                                    cancelButtonColor: isDark ? "#334155" : "#CBD5E1",

                                    html: `
        <div id="isil-chart-selector"
          style="
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:16px;
            margin-top:18px;
          "
        >
          ${[
                                            { id: "bar", label: "Barras", icon: "📊" },
                                            { id: "line", label: "Líneas", icon: "📈" },
                                            { id: "pie", label: "Circular", icon: "🥧" },
                                        ]
                                            .map(
                                                (t) => `
              <div
                class="isil-chart-option ${selectedType === t.id ? "active" : ""}"
                data-value="${t.id}"
                style="
                  cursor:pointer;
                  padding:18px 12px;
                  border-radius:16px;
                  text-align:center;
                  border:2px solid ${selectedType === t.id
                                                        ? "#1CBCE8"
                                                        : isDark ? "#1E293B" : "#E2E8F0"
                                                    };
                  background:${selectedType === t.id
                                                        ? isDark ? "#0E7490" : "#ECFAFD"
                                                        : isDark ? "#020617" : "#FFFFFF"
                                                    };
                  color:${isDark ? "#E5F3F9" : "#0A4E61"};
                  transition:all .2s ease;
                "
              >
                <div style="font-size:28px">${t.icon}</div>
                <strong>${t.label}</strong>
              </div>
            `
                                            )
                                            .join("")}
        </div>
      `,

                                    didOpen: () => {
                                        const options = document.querySelectorAll(".isil-chart-option");

                                        options.forEach((el) => {
                                            el.addEventListener("click", () => {
                                                selectedType = el.getAttribute("data-value");

                                                options.forEach((o) => {
                                                    o.classList.remove("active");
                                                    o.style.borderColor = isDark ? "#1E293B" : "#E2E8F0";
                                                    o.style.background = isDark ? "#020617" : "#FFFFFF";
                                                });

                                                el.classList.add("active");
                                                el.style.borderColor = "#1CBCE8";
                                                el.style.background = isDark ? "#0E7490" : "#ECFAFD";
                                            });
                                        });
                                    },

                                    preConfirm: () => selectedType,
                           }).then(async (res) => {
    if (!res.isConfirmed || !res.value) return;

    try {
        await updateWidget(
            Number(widget.dashboard_id),
            Number(widget.id),
            { chart_type: res.value }
        );

        widget.chart_type = res.value;

        Swal.fire({
            icon: "success",
            title: "Gráfico actualizado",
            text: "Tipo de gráfico cambiado correctamente.",
            timer: 1200,
            showConfirmButton: false,
        });

        // 🔥 AGREGA ESTA LÍNEA
        setTimeout(() => {
          router.reload({ preserveScroll: true });

        }, 1300);

    } catch {
        Swal.fire("Error", "No se pudo actualizar el gráfico", "error");
    }
});


                            }}
                            className="block w-full text-left px-3 py-2 text-purple-400 text-sm hover:bg-gray-700 rounded-md"
                        >
                            🔁 Cambiar tipo
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

                        {/* <button
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
                        </button> */}
                        <button
                            onClick={() => {
                                setMenuOpen(false);

                                Swal.fire({
                                    padding: "22px 26px",
                                    background: theme.mode === "dark" ? "#0B1220" : "#F8FCFE",
                                    color: theme.mode === "dark" ? "#E5F3F9" : "#0A4E61",

                                    html: `
        <div style="text-align:center;font-size:13px;margin-bottom:10px;">
          Seleccionados:
          <b>${activeLabels.length}</b> / ${categoryLabels.length}
        </div>

        <div style="display:flex;gap:8px;justify-content:center;margin-bottom:12px;">
          <button id="selectAll"
            style="background:#ECFAFD;color:#0A4E61;border:1px solid #A7E5F6;
                   border-radius:999px;padding:5px 12px;cursor:pointer;">
            ✔ Todos
          </button>

          <button id="selectNone"
            style="background:#F8FAFC;color:#64748B;border:1px solid #E2E8F0;
                   border-radius:999px;padding:5px 12px;cursor:pointer;">
            ✖ Ninguno
          </button>
        </div>

        <div style="
          max-height:240px;
          overflow:auto;
          display:grid;
          grid-template-columns:repeat(auto-fill,minmax(160px,1fr));
          gap:8px 14px;
        ">
          ${categoryLabels.map(label => {
                                        const color = colorMap[label] || "#CBD5E1";
                                        return `
              <label style="
                display:flex;
                align-items:center;
                gap:8px;
                padding:6px 8px;
                border-radius:10px;
                border:1px solid #E2E8F0;
                cursor:pointer;
              ">
                <input type="checkbox"
                  value="${label}"
                  ${activeLabels.includes(label) ? "checked" : ""}
                />
                <span style="width:10px;height:10px;
                             background:${color};
                             border-radius:3px;"></span>
                <span style="font-size:13px;">${label}</span>
              </label>
            `;
                                    }).join("")}
        </div>
      `,

                                    showCancelButton: true,
                                    confirmButtonText: "Aplicar",
                                    cancelButtonText: "Cancelar",
                                    confirmButtonColor: "#1CBCE8",

                                    didOpen: () => {
                                        document.getElementById("selectAll")?.addEventListener("click", () => {
                                            document
                                                .querySelectorAll<HTMLInputElement>("input[type=checkbox]")
                                                .forEach(cb => cb.checked = true);
                                        });

                                        document.getElementById("selectNone")?.addEventListener("click", () => {
                                            document
                                                .querySelectorAll<HTMLInputElement>("input[type=checkbox]")
                                                .forEach(cb => cb.checked = false);
                                        });
                                    },

                                    preConfirm: () =>
                                        Array.from(
                                            document.querySelectorAll<HTMLInputElement>(
                                                "input[type=checkbox]:checked"
                                            )
                                        ).map(el => el.value),
                                }).then(async res => {
                                    if (!Array.isArray(res.value)) return;

                                    // 🟢 Aplicar visual
                                    setActiveLabels(res.value);

                                    const save = await Swal.fire({
                                        icon: "info",
                                        title: "Filtro aplicado",
                                        text: "¿Deseas guardar el filtro para este widget?",
                                        showCancelButton: true,
                                        confirmButtonText: "Guardar",
                                        cancelButtonText: "Solo aplicar",
                                        confirmButtonColor: "#1CBCE8",
                                    });

                                   if (save.isConfirmed) {
 await saveWidgetFilters(
  Number(widget.dashboard_id), // 🔥 AQUÍ ESTÁ EL ID REAL
  Number(widget.id),
  res.value
);


    Swal.fire("Guardado", "Filtro persistente aplicado.", "success");
}

                                });
                            }}
                            className="block w-full text-left px-3 py-2
             text-sm rounded-md
             text-[#1CBCE8]
             hover:bg-[#ECFAFD]"
                        >
                            🎛️ Filtrar
                        </button>

                        {/* <button
  onClick={() => {
    setMenuOpen(false);

    const isDark =
      document.documentElement.classList.contains("dark") ||
      document.body.classList.contains("dark");

    let selectedType = widget.chart_type; // 🔥 estado local del modal

    Swal.fire({
      title: "Cambiar tipo de gráfico",
      background: isDark ? "#0B1220" : "#F8FCFE",
      color: isDark ? "#E5F3F9" : "#0A4E61",
      showCancelButton: true,
      confirmButtonText: "Guardar",
      cancelButtonText: "Cancelar",
      confirmButtonColor: "#1CBCE8",
      cancelButtonColor: isDark ? "#334155" : "#CBD5E1",

      html: `
        <div id="isil-chart-selector"
          style="
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:16px;
            margin-top:18px;
          "
        >
          ${[
            { id: "bar", label: "Barras", icon: "📊" },
            { id: "line", label: "Líneas", icon: "📈" },
            { id: "pie", label: "Circular", icon: "🥧" },
          ]
            .map(
              (t) => `
              <div
                class="isil-chart-option ${selectedType === t.id ? "active" : ""}"
                data-value="${t.id}"
                style="
                  cursor:pointer;
                  padding:18px 12px;
                  border-radius:16px;
                  text-align:center;
                  border:2px solid ${
                    selectedType === t.id
                      ? "#1CBCE8"
                      : isDark ? "#1E293B" : "#E2E8F0"
                  };
                  background:${
                    selectedType === t.id
                      ? isDark ? "#0E7490" : "#ECFAFD"
                      : isDark ? "#020617" : "#FFFFFF"
                  };
                  color:${isDark ? "#E5F3F9" : "#0A4E61"};
                  transition:all .2s ease;
                "
              >
                <div style="font-size:28px">${t.icon}</div>
                <strong>${t.label}</strong>
              </div>
            `
            )
            .join("")}
        </div>
      `,

      didOpen: () => {
        const options = document.querySelectorAll(".isil-chart-option");

        options.forEach((el) => {
          el.addEventListener("click", () => {
            selectedType = el.getAttribute("data-value");

            options.forEach((o) => {
              o.classList.remove("active");
              o.style.borderColor = isDark ? "#1E293B" : "#E2E8F0";
              o.style.background = isDark ? "#020617" : "#FFFFFF";
            });

            el.classList.add("active");
            el.style.borderColor = "#1CBCE8";
            el.style.background = isDark ? "#0E7490" : "#ECFAFD";
          });
        });
      },

      preConfirm: () => selectedType,
    }).then(async (res) => {
      if (!res.isConfirmed || !res.value) return;

      try {
        await updateWidget(widget.id, { chart_type: res.value });
        widget.chart_type = res.value;

        Swal.fire({
          icon: "success",
          title: "Gráfico actualizado",
          text: "Tipo de gráfico cambiado correctamente.",
          timer: 1600,
          showConfirmButton: false,
          background: isDark ? "#0B1220" : "#F8FCFE",
          color: isDark ? "#E5F3F9" : "#0A4E61",
        });
      } catch {
        Swal.fire("Error", "No se pudo actualizar el gráfico", "error");
      }
    });
  }}
  className="block w-full text-left px-3 py-2 text-purple-400 text-sm hover:bg-gray-700 rounded-md"
>
  🔁 Cambiar tipo
</button> */}

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
