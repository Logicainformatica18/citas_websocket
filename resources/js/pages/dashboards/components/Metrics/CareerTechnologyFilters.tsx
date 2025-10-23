import {
  X,
  Calendar,
  BarChart3,
  LineChart,
  Clock,
  Layers,
  Cpu,
  GraduationCap,
} from "lucide-react";

interface Props {
  show: boolean;
  onClose: () => void;
  metadata: any;
  filters: any;
  setFilters: (f: any) => void;
}

export default function CareerTechnologyFilters({
  show,
  onClose,
  metadata,
  filters,
  setFilters,
}: Props) {
  if (!show) return null;

  const toggleCareer = (id: number) => {
    const current = filters.careers || [];
    const updated = current.includes(id)
      ? current.filter((c: number) => c !== id)
      : [...current, id];
    setFilters({ ...filters, careers: updated });
  };

  const temporalOptions = [
    {
      key: "week",
      label: "Semanal",
      icon: <BarChart3 className="w-4 h-4" />,
      desc: "Promedio por semana (recomendado)",
    },
    {
      key: "month",
      label: "Mensual",
      icon: <LineChart className="w-4 h-4" />,
      desc: "Tendencia mensual consolidada",
    },
  ];

  const dimensions = [
    { key: "presencia_laboral", label: "Presencia laboral" },
    { key: "demanda_relativa", label: "Demanda relativa" },
    { key: "alcance_geografico", label: "Alcance geográfico" },
    { key: "dinamica_temporal", label: "Dinámica temporal" },
    { key: "alineacion_tecnologias", label: "Alineación total" },
  ];

  return (
    <div className="absolute inset-0 bg-black/70 backdrop-blur-sm z-[2000] flex justify-end animate-fade-in">
      <div className="bg-[#0f0f0f] w-96 h-full p-6 border-l border-gray-800 text-sm text-gray-200 overflow-y-auto shadow-2xl">
        {/* 🔹 Header */}
        <div className="flex justify-between items-center mb-5">
          <div className="flex items-center gap-2">
            <Cpu className="w-4 h-4 text-[#00b5e2]" />
            <h3 className="font-semibold text-gray-100">
              Filtros de Alineación (Tecnologías)
            </h3>
          </div>
          <button
            onClick={onClose}
            className="hover:bg-gray-800 p-1 rounded transition"
            title="Cerrar"
          >
            <X className="w-4 h-4 text-gray-400 hover:text-white" />
          </button>
        </div>

        {/* 🔹 Fechas */}
        <div className="space-y-4 mb-6">
          <div>
            <label className="block text-xs text-gray-400 mb-1 flex items-center gap-1">
              <Calendar className="w-3 h-3 text-gray-400" />
              Fecha inicial
            </label>
            <input
              type="date"
              value={filters.start_date || ""}
              onChange={(e) =>
                setFilters({ ...filters, start_date: e.target.value })
              }
              className="w-full bg-gray-800 text-white p-2 rounded border border-gray-700 focus:ring-1 focus:ring-[#00b5e2]"
            />
          </div>

          <div>
            <label className="block text-xs text-gray-400 mb-1 flex items-center gap-1">
              <Calendar className="w-3 h-3 text-gray-400" />
              Fecha final
            </label>
            <input
              type="date"
              value={filters.end_date || ""}
              onChange={(e) =>
                setFilters({ ...filters, end_date: e.target.value })
              }
              className="w-full bg-gray-800 text-white p-2 rounded border border-gray-700 focus:ring-1 focus:ring-[#00b5e2]"
            />
          </div>
        </div>

        {/* 🔹 Agrupación temporal */}
        <div className="mb-6">
          <label className="block text-xs text-gray-400 mb-2 flex items-center gap-1">
            <Clock className="w-3 h-3 text-gray-400" />
            Agrupar resultados por
          </label>
          <div className="grid grid-cols-2 gap-2">
            {temporalOptions.map((opt) => (
              <button
                key={opt.key}
                onClick={() => setFilters({ ...filters, group_by: opt.key })}
                className={`flex flex-col items-center justify-center gap-1 px-2 py-2 rounded border text-xs transition-all ${
                  filters.group_by === opt.key
                    ? "bg-[#00b5e2]/20 text-[#00b5e2] border-[#00b5e2]/40 shadow"
                    : "bg-gray-800 text-gray-400 border-gray-700 hover:border-gray-600 hover:text-gray-200"
                }`}
                title={opt.desc}
              >
                {opt.icon}
                {opt.label}
              </button>
            ))}
          </div>
        </div>

        {/* 🔹 Año / Semestre */}
        {metadata.years?.length > 0 && (
          <div className="mb-6">
            <label className="block text-xs text-gray-400 mb-2 flex items-center gap-1">
              <GraduationCap className="w-3 h-3 text-gray-400" />
              Año y semestre
            </label>
            <div className="grid grid-cols-2 gap-2">
              <select
                value={filters.year || ""}
                onChange={(e) =>
                  setFilters({ ...filters, year: e.target.value })
                }
                className="w-full bg-gray-800 text-white p-2 rounded border border-gray-700 focus:ring-1 focus:ring-[#00b5e2]"
              >
                <option value="">Año</option>
                {metadata.years.map((y: number) => (
                  <option key={y} value={y}>
                    {y}
                  </option>
                ))}
              </select>

              <select
                value={filters.semester || ""}
                onChange={(e) =>
                  setFilters({ ...filters, semester: e.target.value })
                }
                className="w-full bg-gray-800 text-white p-2 rounded border border-gray-700 focus:ring-1 focus:ring-[#00b5e2]"
              >
                <option value="">Todos</option>
                <option value="I">Semestre I</option>
                <option value="II">Semestre II</option>
              </select>
            </div>
          </div>
        )}

        {/* 🔹 Carreras */}
        <div className="mb-6">
          <label className="block text-xs text-gray-400 mb-2 flex items-center gap-1">
            <Layers className="w-3 h-3 text-gray-400" />
            Carreras
          </label>
          <div className="space-y-1 max-h-64 overflow-y-auto pr-2 scrollbar-thin scrollbar-thumb-gray-700 scrollbar-track-gray-900">
            {metadata.careers?.length ? (
              metadata.careers.map((career: any) => (
                <label
                  key={career.id}
                  className={`flex items-center gap-2 cursor-pointer px-2 py-1 rounded transition-all ${
                    filters.careers.includes(career.id)
                      ? "bg-[#00b5e2]/20 border border-[#00b5e2]/40"
                      : "hover:bg-gray-800/40"
                  }`}
                >
                  <input
                    type="checkbox"
                    className="accent-[#00b5e2] cursor-pointer"
                    checked={filters.careers.includes(career.id)}
                    onChange={() => toggleCareer(career.id)}
                  />
                  <span className="text-gray-300 text-sm">{career.name}</span>
                </label>
              ))
            ) : (
              <p className="text-gray-500 text-xs">
                No hay carreras registradas.
              </p>
            )}
          </div>
        </div>

        {/* 🔹 Dimensiones del modelo 4D */}
        <div className="mb-6 border-t border-gray-800 pt-4">
          <label className="block text-xs text-gray-400 mb-2 flex items-center gap-1">
            <BarChart3 className="w-3 h-3 text-gray-400" />
            Dimensiones 4D (visualización)
          </label>
          {dimensions.map((d) => (
            <label
              key={d.key}
              className="flex items-center gap-2 cursor-pointer hover:bg-gray-800/40 px-2 py-1 rounded"
            >
              <input
                type="checkbox"
                className="accent-[#00b5e2] cursor-pointer"
                checked={filters.visibleFields?.includes(d.key)}
                onChange={() => {
                  const current = filters.visibleFields || [];
                  const updated = current.includes(d.key)
                    ? current.filter((f: string) => f !== d.key)
                    : [...current, d.key];
                  setFilters({ ...filters, visibleFields: updated });
                }}
              />
              <span className="capitalize text-gray-300 text-sm">
                {d.label}
              </span>
            </label>
          ))}
        </div>

        {/* 🔹 Botón de aplicar */}
        <button
          onClick={onClose}
          className="mt-6 w-full bg-[#00b5e2] hover:bg-[#009dc8] text-white py-2 rounded-lg transition flex items-center justify-center gap-2 font-semibold"
        >
          <BarChart3 className="w-4 h-4" />
          Aplicar filtros
        </button>
      </div>
    </div>
  );
}
