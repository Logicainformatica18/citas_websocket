import React, { useState, useEffect } from "react";
import {
  BarChart, Bar, LineChart, Line, PieChart, Pie,
  CartesianGrid, XAxis, YAxis, Tooltip, Legend, ResponsiveContainer,
} from "recharts";
import ColorControl from "./ColorControl";

export default function WidgetCard({ widget, onColorChange }) {
  // 🧠 Estado local del color (inicial desde el widget)
  const [color, setColor] = useState(widget.colors?.primary || "#1E88E5");

  // 🔄 Sincronizar color si cambia en el servidor o por otro componente
  useEffect(() => {
    if (widget.colors?.primary && widget.colors.primary !== color) {
      setColor(widget.colors.primary);
    }
  }, [widget.colors]);

  // 🧩 Si no hay datos, evitar errores
  const data = widget.data_source?.rows || [];

  const handleColorChange = (newColor) => {
    setColor(newColor); // 🔥 actualiza el gráfico inmediatamente

    // 🔁 también actualizar en el estado superior (Dashboard)
    if (onColorChange) {
      onColorChange(widget.id, newColor);
    }
  };

  return (
    <div className="bg-gray-800 p-4 rounded-lg shadow border border-gray-700 relative">
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

      {/* 🎨 Selector de color (siempre visible, o con hover si prefieres) */}
      <div className="absolute bottom-3 right-3">
        <ColorControl widget={widget} onChangeColor={handleColorChange} />
      </div>
    </div>
  );
}
