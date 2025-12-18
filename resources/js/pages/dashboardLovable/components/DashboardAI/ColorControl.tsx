import React from "react";
import axios from "axios";

export default function ColorControl({ widget, onChangeColor }) {
  const handleColorChange = async (e) => {
    const newColor = e.target.value;

    // 🔄 actualiza visualmente
    if (onChangeColor) onChangeColor(newColor);

    // 💾 guarda en backend
    try {
      await axios.post(`/api/ai/dashboard-widgets/${widget.id}/color`, {
        color: newColor,
      });
      console.log("✅ Color guardado:", newColor);
    } catch (err) {
      console.warn("⚠️ Error guardando color:", err);
    }
  };

  return (
    <input
      type="color"
      defaultValue={widget.colors?.primary || "#1E88E5"}
      onChange={handleColorChange}
      className="w-8 h-8 rounded cursor-pointer border-none outline-none"
      title="Cambiar color del gráfico"
    />
  );
}
