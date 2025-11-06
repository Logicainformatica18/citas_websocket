import React, { useState, useEffect } from "react";
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
} from "recharts";
import ColorControl from "./ColorControl";
import { FileSpreadsheet, FileDown } from "lucide-react";

// 🧩 Librerías para exportación
import * as XLSX from "xlsx";
import { saveAs } from "file-saver";

import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";


export default function WidgetCard({ widget, onColorChange }) {
    const [color, setColor] = useState(widget.colors?.primary || "#1E88E5");

    useEffect(() => {
        if (widget.colors?.primary && widget.colors.primary !== color) {
            setColor(widget.colors.primary);
        }
    }, [widget.colors]);

    const data = widget.data_source?.rows || [];

    const handleColorChange = (newColor) => {
        setColor(newColor);
        if (onColorChange) onColorChange(widget.id, newColor);
    };

    // ============================================================
    // 📤 Funciones de exportación
    // ============================================================
    const exportToExcel = () => {
        if (!data.length) return alert("No hay datos para exportar");
        const ws = XLSX.utils.json_to_sheet(data);
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
            className="bg-gray-800 p-4 rounded-lg shadow border border-gray-700 relative"
        >
            <h3 className="text-gray-100 font-semibold text-lg mb-2">
                {widget.title || "Sin título"}
            </h3>

            <div className="h-[260px]">
                {widget.chart_type === "bar" && (
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={data}>
                            <CartesianGrid strokeDasharray="3 3" stroke="#444" />
                            <XAxis dataKey="name" stroke="#ccc" />
                            <YAxis stroke="#ccc" />
                            <Tooltip />
                            <Legend />
                            <Bar dataKey="total_jobs_found" fill={color} />
                        </BarChart>
                    </ResponsiveContainer>
                )}

                {widget.chart_type === "line" && (
                    <ResponsiveContainer width="100%" height="100%">
                        <LineChart data={data}>
                            <CartesianGrid strokeDasharray="3 3" stroke="#444" />
                            <XAxis dataKey="name" stroke="#ccc" />
                            <YAxis stroke="#ccc" />
                            <Tooltip />
                            <Legend />
                            <Line
                                type="monotone"
                                dataKey="total_jobs_found"
                                stroke={color}
                                strokeWidth={2}
                            />
                        </LineChart>
                    </ResponsiveContainer>
                )}

                {widget.chart_type === "pie" && (
                    <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                            <Pie
                                data={data}
                                dataKey="total_jobs_found"
                                nameKey="name"
                                fill={color}
                                label
                            />
                            <Tooltip />
                        </PieChart>
                    </ResponsiveContainer>
                )}
            </div>

            {/* 🎨 Control de color */}
            <div className="absolute bottom-3 right-3">
                <ColorControl widget={widget} onChangeColor={handleColorChange} />
            </div>

            {/* 📦 Botones de exportación */}
            <div className="absolute top-3 right-3 flex gap-2 z-10">
                <button
                    onClick={exportToExcel}
                    className="p-1.5 bg-green-600 hover:bg-green-700 text-white rounded-md"
                    title="Exportar a Excel"
                >
                    <FileSpreadsheet size={16} />
                </button>

                <button
                    onClick={exportToPDF}
                    className="p-1.5 bg-red-600 hover:bg-red-700 text-white rounded-md"
                    title="Exportar a PDF"
                >
                    <FileDown size={16} />
                </button>
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
