import { X, ChevronDown, ChevronUp, Search } from "lucide-react";
import { useState } from "react";

export default function CityDemandFilters({
  show,
  onClose,
  metadata,
  filters,
  setFilters,
}: any) {
  const [open, setOpen] = useState({
    sources: true,
    countries: false,
    modalities: true,
  });
  const [searchCountry, setSearchCountry] = useState("");

  const toggleValue = (type: "sources" | "modalities" | "countries", value: string) => {
    setFilters((f: any) => {
      const arr = f[type];
      const exists = arr.includes(value);
      return {
        ...f,
        [type]: exists ? arr.filter((v: string) => v !== value) : [...arr, value],
      };
    });
  };

  const toggleGroup = (key: keyof typeof open) =>
    setOpen((prev) => ({ ...prev, [key]: !prev[key] }));

  if (!show) return null;

  return (
    <div className="absolute right-6 top-12 bg-[#18181b] border border-gray-700 rounded-xl shadow-2xl p-4 z-[999] w-80 text-xs overflow-y-auto max-h-[80vh] backdrop-blur-sm animate-fadeIn">
      {/* Header */}
      <div className="flex justify-between items-center mb-3 border-b border-gray-700 pb-2">
        <h3 className="font-semibold text-sm text-white">🎛️ Filtros</h3>
        <button
          onClick={onClose}
          className="p-1 rounded hover:bg-gray-700 transition"
          title="Cerrar"
        >
          <X className="w-4 h-4 text-gray-300" />
        </button>
      </div>

      {/* Sección: Fuentes */}
      <div className="mb-3">
        <div
          className="flex justify-between items-center cursor-pointer select-none mb-1"
          onClick={() => toggleGroup("sources")}
        >
          <label className="text-gray-300 font-medium">Fuentes</label>
          {open.sources ? (
            <ChevronUp className="w-4 h-4 text-gray-400" />
          ) : (
            <ChevronDown className="w-4 h-4 text-gray-400" />
          )}
        </div>

        {open.sources && (
          <div className="grid grid-cols-2 gap-1 pl-1">
            {metadata.sources.map((s: string) => (
              <label
                key={s}
                className="flex items-center gap-1 hover:bg-gray-800 rounded px-1 py-[2px]"
              >
                <input
                  type="checkbox"
                  checked={filters.sources.includes(s)}
                  onChange={() => toggleValue("sources", s)}
                />
                <span className="truncate">{s}</span>
              </label>
            ))}
          </div>
        )}
      </div>

      {/* Sección: Países */}
      <div className="mb-3">
        <div
          className="flex justify-between items-center cursor-pointer select-none mb-1"
          onClick={() => toggleGroup("countries")}
        >
          <label className="text-gray-300 font-medium">Países</label>
          {open.countries ? (
            <ChevronUp className="w-4 h-4 text-gray-400" />
          ) : (
            <ChevronDown className="w-4 h-4 text-gray-400" />
          )}
        </div>

        {open.countries && (
          <>
            <div className="relative mb-2">
              <Search className="absolute left-2 top-1.5 w-3.5 h-3.5 text-gray-400" />
              <input
                type="text"
                placeholder="Buscar país..."
                value={searchCountry}
                onChange={(e) => setSearchCountry(e.target.value)}
                className="pl-7 w-full bg-gray-800 border border-gray-600 rounded p-1 text-gray-200"
              />
            </div>
            <div className="grid grid-cols-2 gap-1 pl-1 max-h-48 overflow-y-auto pr-1 scrollbar-thin scrollbar-thumb-gray-700 scrollbar-track-transparent">
              {metadata.countries
                .filter((c: string) =>
                  c.toLowerCase().includes(searchCountry.toLowerCase())
                )
                .map((c: string) => (
                  <label
                    key={c}
                    className="flex items-center gap-1 hover:bg-gray-800 rounded px-1 py-[2px]"
                  >
                    <input
                      type="checkbox"
                      checked={filters.countries.includes(c)}
                      onChange={() => toggleValue("countries", c)}
                    />
                    <span className="truncate">{c}</span>
                  </label>
                ))}
            </div>
          </>
        )}
      </div>

      {/* Sección: Modalidades */}
      <div className="mb-3">
        <div
          className="flex justify-between items-center cursor-pointer select-none mb-1"
          onClick={() => toggleGroup("modalities")}
        >
          <label className="text-gray-300 font-medium">Modalidades</label>
          {open.modalities ? (
            <ChevronUp className="w-4 h-4 text-gray-400" />
          ) : (
            <ChevronDown className="w-4 h-4 text-gray-400" />
          )}
        </div>

        {open.modalities && (
          <div className="grid grid-cols-2 gap-1 pl-1">
            {metadata.modalities.map((m: string) => (
              <label
                key={m}
                className="flex items-center gap-1 hover:bg-gray-800 rounded px-1 py-[2px]"
              >
                <input
                  type="checkbox"
                  checked={filters.modalities.includes(m)}
                  onChange={() => toggleValue("modalities", m)}
                />
                <span>{m}</span>
              </label>
            ))}
          </div>
        )}
      </div>

      {/* Fechas */}
      <div className="mb-2 border-t border-gray-700 pt-2">
        <label className="block mb-1 text-gray-300">Desde:</label>
        <input
          type="date"
          value={filters.start_date}
          onChange={(e) =>
            setFilters((f: any) => ({ ...f, start_date: e.target.value }))
          }
          className="w-full bg-gray-800 border border-gray-600 rounded p-1 mb-2 text-gray-200"
        />
        <label className="block mb-1 text-gray-300">Hasta:</label>
        <input
          type="date"
          value={filters.end_date}
          onChange={(e) =>
            setFilters((f: any) => ({ ...f, end_date: e.target.value }))
          }
          className="w-full bg-gray-800 border border-gray-600 rounded p-1 text-gray-200"
        />
      </div>

      {/* Botones */}
      <div className="flex justify-between mt-3 pt-2 border-t border-gray-700">
        <button
          onClick={() =>
            setFilters({
              sources: [],
              modalities: [],
              countries: [],
              year: new Date().getFullYear(),
              quarter: "",
              start_date: "",
              end_date: "",
            })
          }
          className="text-gray-400 hover:text-white text-xs transition"
        >
          Limpiar
        </button>
        <button
          onClick={onClose}
          className="bg-blue-600 hover:bg-blue-700 px-3 py-1.5 rounded text-white text-xs font-medium transition"
        >
          Aplicar
        </button>
      </div>
    </div>
  );
}
