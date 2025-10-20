import { X } from "lucide-react";

interface Props {
  show: boolean;
  onClose: () => void;
  metadata: any;
  filters: any;
  setFilters: (f: any) => void;
}

export default function CareerLanguageFilters({
  show,
  onClose,
  metadata,
  filters,
  setFilters,
}: Props) {
  if (!show) return null;

  // 🔹 Alterna selección de carrera
  const toggleCareer = (id: number) => {
    const current = filters.careers || [];
    const updated = current.includes(id)
      ? current.filter((c: number) => c !== id)
      : [...current, id];
    setFilters({ ...filters, careers: updated });
  };

  // 🔹 Cambia el tipo de periodo
  const handlePeriodChange = (type: "quarter" | "semester", value: string) => {
    setFilters({
      ...filters,
      period_type: type,
      period_value: value,
    });
  };

  return (
    <div className="absolute inset-0 bg-black/70 backdrop-blur-sm z-[2000] flex justify-end">
      <div className="bg-gray-900 w-80 h-full p-4 border-l border-gray-700 text-sm text-gray-200 overflow-y-auto">
        {/* 🔹 Encabezado */}
        <div className="flex justify-between items-center mb-4">
          <h3 className="font-semibold text-gray-100">
            Filtros de Alineación de Carreras
          </h3>
          <button
            onClick={onClose}
            className="hover:bg-gray-800 p-1 rounded transition"
            title="Cerrar"
          >
            <X className="w-4 h-4 text-gray-400 hover:text-white" />
          </button>
        </div>

        {/* 🔹 Año */}
        <div className="mb-4">
          <label className="block text-xs text-gray-400 mb-1">Año</label>
          <select
            value={filters.year}
            onChange={(e) =>
              setFilters({ ...filters, year: Number(e.target.value) })
            }
            className="w-full bg-gray-800 text-white p-2 rounded border border-gray-700 focus:ring-1 focus:ring-blue-500"
          >
            {metadata.years?.length ? (
              metadata.years.map((y: number) => (
                <option key={y} value={y}>
                  {y}
                </option>
              ))
            ) : (
              <option value="">Sin datos</option>
            )}
          </select>
        </div>

        {/* 🔹 Tipo de período */}
        <div className="mb-4">
          <label className="block text-xs text-gray-400 mb-2">
            Período de análisis
          </label>
          <div className="flex gap-2 mb-3">
            <button
              onClick={() =>
                setFilters({ ...filters, period_type: "quarter", period_value: "" })
              }
              className={`flex-1 py-1.5 rounded text-xs ${
                filters.period_type === "quarter"
                  ? "bg-blue-600 text-white"
                  : "bg-gray-800 text-gray-400"
              }`}
            >
              Trimestres
            </button>
            <button
              onClick={() =>
                setFilters({ ...filters, period_type: "semester", period_value: "" })
              }
              className={`flex-1 py-1.5 rounded text-xs ${
                filters.period_type === "semester"
                  ? "bg-blue-600 text-white"
                  : "bg-gray-800 text-gray-400"
              }`}
            >
              Semestres
            </button>
          </div>

          {/* 🔹 Opciones de trimestre o semestre */}
          {filters.period_type === "quarter" && (
            <div className="space-y-2">
              {["Q1 (Ene - Mar)", "Q2 (Abr - Jun)", "Q3 (Jul - Sep)", "Q4 (Oct - Dic)"].map(
                (label, i) => {
                  const val = `Q${i + 1}`;
                  return (
                    <label
                      key={val}
                      className="flex items-center gap-2 cursor-pointer hover:bg-gray-800/50 px-2 py-1 rounded"
                    >
                      <input
                        type="radio"
                        name="quarter"
                        checked={filters.period_value === val}
                        onChange={() => handlePeriodChange("quarter", val)}
                        className="accent-blue-500"
                      />
                      <span>{label}</span>
                    </label>
                  );
                }
              )}
            </div>
          )}

          {filters.period_type === "semester" && (
            <div className="space-y-2">
              {["S1 (Ene - Jun)", "S2 (Jul - Dic)"].map((label, i) => {
                const val = `S${i + 1}`;
                return (
                  <label
                    key={val}
                    className="flex items-center gap-2 cursor-pointer hover:bg-gray-800/50 px-2 py-1 rounded"
                  >
                    <input
                      type="radio"
                      name="semester"
                      checked={filters.period_value === val}
                      onChange={() => handlePeriodChange("semester", val)}
                      className="accent-blue-500"
                    />
                    <span>{label}</span>
                  </label>
                );
              })}
            </div>
          )}
        </div>

        {/* 🔹 Carreras (checkboxes) */}
        <div className="mb-4">
          <label className="block text-xs text-gray-400 mb-2">Carreras</label>
          <div className="space-y-2 max-h-64 overflow-y-auto pr-1 scrollbar-thin scrollbar-thumb-gray-700 scrollbar-track-gray-900">
            {metadata.careers?.length ? (
              metadata.careers.map((career: any) => (
                <label
                  key={career.id}
                  className="flex items-center gap-2 cursor-pointer hover:bg-gray-800/50 px-2 py-1 rounded"
                >
                  <input
                    type="checkbox"
                    className="accent-blue-500 cursor-pointer"
                    checked={filters.careers.includes(career.id)}
                    onChange={() => toggleCareer(career.id)}
                  />
                  <span className="text-gray-300 text-sm">{career.name}</span>
                </label>
              ))
            ) : (
              <p className="text-gray-500 text-xs">Sin datos disponibles</p>
            )}
          </div>
        </div>

        {/* 🔹 Botón Aplicar */}
        <button
          onClick={onClose}
          className="mt-6 w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded transition"
        >
          Aplicar Filtros
        </button>
      </div>
    </div>
  );
}
