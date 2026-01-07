import { useState } from "react";
import axios from "axios";

interface ChartType {
  slug: string;
  name: string;
}

interface ChartSelectorProps {
  trainingId: number;
  chartTypes: ChartType[];
}

export default function ChartSelector({
  trainingId,
  chartTypes,
}: ChartSelectorProps) {
  const [selected, setSelected] = useState("");
  const [saving, setSaving] = useState(false);
  const [successMsg, setSuccessMsg] = useState("");

  const handleCreateChart = async () => {
    if (!selected || saving) return;

    setSaving(true);
    setSuccessMsg("");

    try {
      const res = await axios.post(
        "/dashboard/widgets/from-training",
        {
          training_id: trainingId,
          chart_type: selected,
        }
      );

      setSuccessMsg(
        res.data?.message ?? "✅ Gráfico creado correctamente."
      );

      // 🔄 refrescar widgets del dashboard
      window.dispatchEvent(
        new CustomEvent("dashboard:refresh-widgets")
      );
    } catch (error) {
      console.error("❌ Error creando gráfico:", error);
      alert("Error creando el gráfico. Revisa la consola.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="mt-3 rounded-xl border border-[#D9EEF5] dark:border-[#2a2c2f] bg-white dark:bg-[#202123] p-4 space-y-3">
      {successMsg ? (
        <div className="text-sm text-green-600 dark:text-green-400 font-medium">
          {successMsg}
        </div>
      ) : (
        <>
          <div className="text-sm font-semibold text-gray-800 dark:text-gray-200">
            📊 Elige el tipo de gráfico
          </div>

          <div className="text-xs text-gray-500 dark:text-gray-400">
            Selecciona cómo deseas visualizar los datos obtenidos
          </div>

          <select
            value={selected}
            onChange={(e) => setSelected(e.target.value)}
            className="w-full rounded-lg px-3 py-2 text-sm bg-white dark:bg-[#202123] text-gray-900 dark:text-gray-200 border border-[#A7E5F6] dark:border-[#3f4144] focus:outline-none focus:ring-2 focus:ring-[#1CBCE8]"
          >
            <option value="">— Selecciona tipo de gráfico —</option>
            {chartTypes.map((ct) => (
              <option key={ct.slug} value={ct.slug}>
                {ct.name}
              </option>
            ))}
          </select>

          <button
            onClick={handleCreateChart}
            disabled={!selected || saving}
            className="w-full mt-2 rounded-lg py-2 text-sm font-medium text-white bg-[#1CBCE8] hover:bg-[#17a7cf] disabled:opacity-50 disabled:cursor-not-allowed transition"
          >
            {saving ? "Creando gráfico..." : "Crear gráfico"}
          </button>
        </>
      )}
    </div>
  );
}
