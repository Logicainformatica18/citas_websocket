import { useState } from "react";
import axios from "axios";

export default function ChartSelector({ trainingId, chartTypes }) {
  const [selected, setSelected] = useState("");
  const [saving, setSaving] = useState(false);
  const [successMsg, setSuccessMsg] = useState("");

  const handleCreateChart = async () => {
    if (!selected) return alert("Selecciona un tipo de gráfico.");
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
    <div className="mt-3 p-4 rounded-lg bg-[#2e2e3a] text-white border border-gray-600 shadow-md">
      {successMsg ? (
        <p className="text-green-400 font-medium">{successMsg}</p>
      ) : (
        <>
          <p className="mb-2">📈 Elige el tipo de gráfico para visualizar los datos:</p>
          <select
            className="w-full p-2 text-black rounded"
            value={selected}
            onChange={(e) => setSelected(e.target.value)}
          >
            <option value="">-- Selecciona tipo de gráfico --</option>
            {chartTypes.map((ct) => (
              <option key={ct.slug} value={ct.slug}>
                {ct.name}
              </option>
            ))}
          </select>

          <button
            onClick={handleCreateChart}
            disabled={!selected || saving}
            className="mt-3 w-full px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 disabled:opacity-50"
          >
            {saving ? "Creando gráfico..." : "Crear gráfico"}
          </button>
        </>
      )}
    </div>
  );
}
