import { Card, CardContent } from "@/components/ui/card";
import { MapContainer, TileLayer, CircleMarker, Tooltip, Popup, useMap } from "react-leaflet";
import { useEffect, useState, useCallback, useRef } from "react";
import { Filter, X } from "lucide-react";
import "leaflet/dist/leaflet.css";
import "leaflet.heat";
import axios from "axios";

// =======================================================
// 📍 Dispara fetch al mover/zoom el mapa
// =======================================================
function MapEvents({ onChange, onZoom }: { onChange: (bounds: any, zoom: number) => void; onZoom: (zoom: number) => void }) {
  const map = useMap();
  const timeoutRef = useRef<NodeJS.Timeout | null>(null);

  useEffect(() => {
    const update = () => {
      if (timeoutRef.current) clearTimeout(timeoutRef.current);
      timeoutRef.current = setTimeout(() => {
        onChange(map.getBounds(), map.getZoom());
        onZoom(map.getZoom());
      }, 500);
    };

    map.on("moveend", update);
    map.on("zoomend", update);
    update();

    return () => {
      map.off("moveend", update);
      map.off("zoomend", update);
      if (timeoutRef.current) clearTimeout(timeoutRef.current);
    };
  }, [map, onChange, onZoom]);

  return null;
}

// =======================================================
// 🔥 Heatmap multicapa sin parpadeo
// =======================================================
function MultiHeatmapLayer({ data }: { data: any[] }) {
  const map = useMap();
  const layersRef = useRef<Record<string, any>>({});

  const modalityColors: Record<string, string> = {
    no_remote: "#ff3b30",
    remote_local: "#34c759",
    hibrido: "#ff9500",
    fully_remote: "#007aff",
  };

  const colorRGBA = (hex: string, alpha: number) => {
    const bigint = parseInt(hex.replace("#", ""), 16);
    const r = (bigint >> 16) & 255;
    const g = (bigint >> 8) & 255;
    const b = bigint & 255;
    return `rgba(${r},${g},${b},${alpha})`;
  };

  const normalize = (m: string | null) => (m ? m.toLowerCase() : "no_remote");

  useEffect(() => {
    if (!map || !data) return;
    const grouped: Record<string, [number, number, number][]> = {};

    data.forEach((d) => {
      const key = normalize(d.modality);
      if (!grouped[key]) grouped[key] = [];
      grouped[key].push([d.lat, d.lng, d.total || d.count || 1]);
    });

    Object.entries(grouped).forEach(([mod, pts]) => {
      const color = modalityColors[mod] || "#999999";
      const maxVal = Math.max(...pts.map((p) => p[2]), 1);
      const normPts = pts.map(([lat, lng, val]) => [lat, lng, val / maxVal]);

      if (!layersRef.current[mod]) {
        // @ts-ignore
        const heat = (window as any).L.heatLayer(normPts, {
          radius: 32,
          blur: 25,
          maxZoom: 8,
          minOpacity: 0.45,
          gradient: {
            0.2: colorRGBA(color, 0.3),
            0.4: colorRGBA(color, 0.5),
            0.6: colorRGBA(color, 0.7),
            0.8: colorRGBA(color, 0.9),
            1.0: colorRGBA(color, 1),
          },
        });
        heat.addTo(map);
        layersRef.current[mod] = heat;
      } else {
        layersRef.current[mod].setLatLngs(normPts);
      }
    });

    Object.keys(layersRef.current).forEach((mod) => {
      if (!grouped[mod]) {
        const layer = layersRef.current[mod];
        if (layer && map.hasLayer(layer)) {
          try {
            map.removeLayer(layer);
          } catch {
            console.warn("⚠️ Error al remover capa:", mod);
          }
        }
        delete layersRef.current[mod];
      }
    });
  }, [data]);

  return null;
}

// =======================================================
// 🧠 Hook de carga de datos
// =======================================================
function useCityDemandData() {
  const [data, setData] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const lastParamsRef = useRef<string>("");

  const fetchData = useCallback(async (filters: any, bounds: any, zoom: number) => {
    const paramsKey = JSON.stringify({ ...filters, zoom });
    if (paramsKey === lastParamsRef.current) return;
    lastParamsRef.current = paramsKey;

    try {
      setLoading(true);
      const res = await axios.get("/ai/city-demand/get-data", {
        params: {
          zoom,
          ...filters,
          "bounds[lat_min]": bounds?.getSouth(),
          "bounds[lat_max]": bounds?.getNorth(),
          "bounds[lng_min]": bounds?.getWest(),
          "bounds[lng_max]": bounds?.getEast(),
        },
      });
      setData(res.data.results || []);
    } catch (err) {
      console.error("❌ Error al obtener datos", err);
    } finally {
      setLoading(false);
    }
  }, []);

  return { data, fetchData, loading };
}

// =======================================================
// 🌍 Componente principal
// =======================================================
export default function CityDemandMap() {
  const { data, fetchData, loading } = useCityDemandData();
  const [zoomLevel, setZoomLevel] = useState(5);
  const [showFilters, setShowFilters] = useState(false);

  const [filters, setFilters] = useState({
    source: "",
    year: new Date().getFullYear(),
    quarter: "",
    modality: "",
    countries: [] as string[],
    start_date: "",
    end_date: "",
  });

  const toggleCountry = (country: string) => {
    setFilters((f) => {
      const exists = f.countries.includes(country);
      return {
        ...f,
        countries: exists ? f.countries.filter((c) => c !== country) : [...f.countries, country],
      };
    });
  };

  const totals = data.reduce((acc: Record<string, number>, d: any) => {
    const m = (d.modality || "no_remote").toLowerCase();
    const mapKey =
      m === "no_remote"
        ? "Presencial"
        : m === "remote_local"
        ? "Remoto local"
        : m === "hibrido"
        ? "Híbrido"
        : m === "fully_remote"
        ? "Full remoto"
        : "Otros";
    acc[mapKey] = (acc[mapKey] || 0) + (d.total || d.count || 0);
    return acc;
  }, {});

  const colorMap: Record<string, string> = {
    no_remote: "#ff3b30",
    remote_local: "#34c759",
    hibrido: "#ff9500",
    fully_remote: "#007aff",
  };

  return (
    <Card className="bg-[#111] text-white rounded-xl border border-gray-700 relative">
      <CardContent className="p-6 flex flex-col items-center gap-3 relative">
        <div className="flex justify-between items-center w-full">
          <h2 className="text-sm font-semibold">DEMANDA POTENCIAL POR CIUDAD</h2>
          <button
            onClick={() => setShowFilters(!showFilters)}
            className="bg-gray-800 hover:bg-gray-700 rounded-full p-2 transition"
          >
            <Filter className="w-4 h-4 text-gray-200" />
          </button>
        </div>

        {/* PANEL DE FILTROS */}
        {showFilters && (
          <div className="absolute right-6 top-12 bg-[#1a1a1a] border border-gray-700 rounded-lg shadow-xl p-4 z-[999] w-72 text-xs">
            <div className="flex justify-between items-center mb-2">
              <h3 className="font-semibold text-sm text-white">Filtros</h3>
              <button onClick={() => setShowFilters(false)}>
                <X className="w-4 h-4 text-gray-400 hover:text-white" />
              </button>
            </div>

            {/* Fuente */}
            <label className="block mb-1 text-gray-300">Fuente:</label>
            <select
              value={filters.source}
              onChange={(e) => setFilters((f) => ({ ...f, source: e.target.value }))}
              className="w-full bg-gray-800 border border-gray-600 rounded p-1 mb-2"
            >
              <option value="">Todas</option>
              <option value="Computrabajo">Computrabajo</option>
              <option value="LinkedIn">LinkedIn</option>
              <option value="Adzuna">Adzuna</option>
              <option value="GetOnBoard">GetOnBoard</option>
              <option value="Seek">Seek</option>
            </select>

            {/* Países */}
            <label className="block mb-1 text-gray-300">Países:</label>
            <div className="grid grid-cols-2 gap-1 mb-2">
              {["Peru", "Bolivia", "Argentina", "Chile", "Mexico", "Colombia", "Australia", "Estados Unidos"].map((c) => (
                <label key={c} className="flex items-center gap-1">
                  <input
                    type="checkbox"
                    checked={filters.countries.includes(c)}
                    onChange={() => toggleCountry(c)}
                  />
                  <span>{c}</span>
                </label>
              ))}
            </div>

            {/* Intervalo de fechas */}
            <label className="block mb-1 text-gray-300">Desde:</label>
            <input
              type="date"
              value={filters.start_date}
              onChange={(e) => setFilters((f) => ({ ...f, start_date: e.target.value }))}
              className="w-full bg-gray-800 border border-gray-600 rounded p-1 mb-2"
            />
            <label className="block mb-1 text-gray-300">Hasta:</label>
            <input
              type="date"
              value={filters.end_date}
              onChange={(e) => setFilters((f) => ({ ...f, end_date: e.target.value }))}
              className="w-full bg-gray-800 border border-gray-600 rounded p-1 mb-2"
            />

            {/* Modalidad */}
            <label className="block mb-1 text-gray-300">Modalidad:</label>
            <select
              value={filters.modality}
              onChange={(e) => setFilters((f) => ({ ...f, modality: e.target.value }))}
              className="w-full bg-gray-800 border border-gray-600 rounded p-1 mb-2"
            >
              <option value="">Todas</option>
              <option value="no_remote">Presencial</option>
              <option value="remote_local">Remoto local</option>
              <option value="hibrido">Híbrido</option>
              <option value="fully_remote">Full remoto</option>
            </select>

            <div className="flex justify-between mt-2">
              <button
                onClick={() =>
                  setFilters({
                    source: "",
                    year: new Date().getFullYear(),
                    quarter: "",
                    modality: "",
                    countries: [],
                    start_date: "",
                    end_date: "",
                  })
                }
                className="text-gray-300 hover:text-white text-xs"
              >
                Limpiar
              </button>
              <button
                onClick={() => setShowFilters(false)}
                className="bg-blue-600 hover:bg-blue-700 px-3 py-1 rounded text-white text-xs"
              >
                Aplicar
              </button>
            </div>
          </div>
        )}

        {/* MAPA */}
        <div className="relative w-full h-[440px] rounded-lg overflow-hidden mt-2">
          <MapContainer center={[-12.0464, -77.0428]} zoom={5} style={{ height: "100%", width: "100%" }}>
            <TileLayer
              url="https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png"
              attribution='&copy; OpenStreetMap contributors &copy; CARTO'
            />
            <MapEvents onChange={(bounds) => fetchData(filters, bounds, zoomLevel)} onZoom={setZoomLevel} />
            <MultiHeatmapLayer data={data} />

            {zoomLevel >= 5 &&
              data.map((d, i) => (
                <CircleMarker
                  key={i}
                  center={[d.lat, d.lng]}
                  radius={Math.min(80, 2 + Math.sqrt(d.total || 1) * (zoomLevel / 2))}
                  color={colorMap[d.modality?.toLowerCase() || "no_remote"]}
                  fillOpacity={0.6}
                >
                  <Tooltip direction="top" offset={[0, -5]} opacity={1}>
                    {d.city || "—"}, {d.country || "—"} — {d.total} ofertas ({d.modality})
                  </Tooltip>
                  <Popup>
                    <div style={{ minWidth: "200px" }}>
                      <h3 className="font-bold mb-1">{d.city}, {d.country}</h3>
                      <p>Total ofertas: {d.total}</p>
                      <p>Modalidad: {d.modality}</p>
                    </div>
                  </Popup>
                </CircleMarker>
              ))}
          </MapContainer>

          {/* Leyenda */}
          <div className="absolute bottom-6 right-6 z-[1000] text-xs text-gray-200 bg-black/60 p-2 rounded">
            <p>🔴 Presencial: {totals["Presencial"] || 0}</p>
            <p>🟢 Remoto local: {totals["Remoto local"] || 0}</p>
            <p>🟠 Híbrido: {totals["Híbrido"] || 0}</p>
            <p>🔵 Full remoto: {totals["Full remoto"] || 0}</p>
          </div>

          {loading && (
            <div className="absolute inset-0 flex items-center justify-center bg-black/40 text-white text-sm">
              Cargando datos...
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  );
}
