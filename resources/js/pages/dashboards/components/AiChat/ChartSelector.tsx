import { useState } from "react";
import axios from "axios";

export default function ChartSelector({ trainingId, chartTypes }) {
  const [selected, setSelected] = useState("");
  const [saving, setSaving] = useState(false);
  const [successMsg, setSuccessMsg] = useState("");

  const handleCreateChart = async () => {
    if (!selected) return;
    setSaving(true);
    try {
      const res = await axios.post("/api/ai/dashboard-widgets/from-training", {
        training_id: trainingId,
        chart_type: selected,
      });
      setSuccessMsg(res.data.message || "✅ Gráfico creado correctamente.");
    } catch (err) {
      alert("⚠️ Error creando el gráfico. Revisa la consola.");
      console.error(err);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div
      className="
        mt-3
        rounded-xl
        border border-[#D9EEF5]
        bg-white dark:bg-[#202123]
        p-4
        space-y-3
      "
    >
      {successMsg ? (
        <div className="text-sm text-green-600 dark:text-green-400 font-medium">
          {successMsg}
        </div>
      ) : (
        <>
          {/* Título */}
          <div className="flex items-center gap-2 text-sm font-semibold text-gray-800 dark:text-gray-200">
            📊 Elige el tipo de gráfico
          </div>

          {/* Subtítulo */}
          <div className="text-xs text-gray-500 dark:text-gray-400">
            Selecciona cómo deseas visualizar los datos obtenidos
          </div>

          {/* Select */}
          <select
            value={selected}
            onChange={(e) => setSelected(e.target.value)}
            className="
              w-full
              rounded-lg
              px-3 py-2
              text-sm
              bg-white dark:bg-[#202123]
              text-gray-900 dark:text-gray-200
              border border-[#A7E5F6] dark:border-[#3f4144]
              focus:outline-none
              focus:ring-2 focus:ring-[#1CBCE8]
            "
          >
            <option value="">— Selecciona tipo de gráfico —</option>
            {chartTypes.map((ct) => (
              <option key={ct.slug} value={ct.slug}>
                {ct.name}
              </option>
            ))}
          </select>

          {/* Botón */}
          <button
            onClick={handleCreateChart}
            disabled={!selected || saving}
            className="
              w-full
              mt-2
              rounded-lg
              py-2
              text-sm font-medium
              text-white
              bg-[#1CBCE8]
              hover:bg-[#17a7cf]
              disabled:opacity-50
              disabled:cursor-not-allowed
              transition
            "
          >
            {saving ? "Creando gráfico..." : "Crear gráfico"}
          </button>
        </>
      )}
    </div>
  );
}
