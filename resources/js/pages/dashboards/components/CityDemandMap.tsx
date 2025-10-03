import { Card, CardContent } from "@/components/ui/card";
import { MapContainer, TileLayer, CircleMarker, Tooltip, useMap } from "react-leaflet";
import { useEffect, useState, useCallback } from "react";
import "leaflet/dist/leaflet.css";
import "leaflet.heat";
import axios from "axios";
//import { Math as MathGlobal } from "globalthis/implementation";



// 👇 componente que dispara fetch cada vez que cambia el mapa
function MapEvents({ onChange, onZoom }: { onChange: (bounds: any, zoom: number) => void, onZoom: (zoom: number) => void }) {
  const map = useMap();

  useEffect(() => {
    const update = () => {
      onChange(map.getBounds(), map.getZoom());
      onZoom(map.getZoom());
    };
    update(); // primera carga al montar
    map.on("moveend", update);
    return () => {
      map.off("moveend", update);
    };
  }, [map, onChange, onZoom]);

  return null;
}

// Heatmap
function HeatmapLayer({ points }: { points: [number, number, number][] }) {
  const map = useMap();

  useEffect(() => {
    // @ts-ignore
    const heat = (window as any).L.heatLayer(points, {
      radius: 25,
      blur: 20,
      maxZoom: 5,
      gradient: {
        0.2: "#ffffb2",
        0.4: "#fecc5c",
        0.6: "#fd8d3c",
        0.8: "#f03b20",
        1.0: "#bd0026",
      },
    }).addTo(map);

    return () => {
      map.removeLayer(heat);
    };
  }, [map, points]);

  return null;
}

// =========================
// 🔧 Estado + fetch de datos
// =========================
type JobOfferPoint = {
  city: string;
  country: string;
  count: number;
  modality: string;
  coords: [number, number];
};

function useCityDemandData() {
  const [data, setData] = useState<JobOfferPoint[]>([]);
  const [modalities, setModalities] = useState<Record<string, number>>({});
  const [period, setPeriod] = useState<"quarter" | "year" | "all">("quarter"); // 👈 input periodo

  const normalizeModality = (m: string | null) => {
    if (!m) return "presencial";
    const val = m.toLowerCase();

    if (["fully_remote", "full_remote"].includes(val)) return "fully_remote";
    if (["remote_local"].includes(val)) return "remoto";
    if (["hybrid", "hibrido"].includes(val)) return "hibrido";
    if (["no_remote", "presencial"].includes(val)) return "presencial";
    return "presencial";
  };

  // 👇 función para pedir datos según bounds, zoom y periodo
  const fetchData = useCallback(
    async (bounds: any, zoom: number) => {
      try {
        const res = await axios.get("/ai/city-demand", {
          params: {
            zoom,
            period, // 👈 pasa periodo como query param
            "bounds[lat_min]": bounds.getSouth(),
            "bounds[lat_max]": bounds.getNorth(),
            "bounds[lng_min]": bounds.getWest(),
            "bounds[lng_max]": bounds.getEast(),
          },
        });

        const payload = res.data.data || res.data;
        const results = payload.results || [];
        const mods = payload.modalities || {};

        const mapped = results
          .filter((r: any) => r.lat && r.lng)
          .flatMap((r: any) =>
            Object.entries(r.modalidad || { [r.modality || "presencial"]: r.total || r.count }).map(
              ([key, count]) => ({
                ...r,
                coords: [r.lat, r.lng] as [number, number],
                modality: normalizeModality(key),
                count,
              })
            )
          );

        setData(mapped);
        setModalities(mods);
      } catch (err) {
        console.error("❌ Error cargando city demand", err);
      }
    },
    [period]
  );

  return { data, modalities, period, setPeriod, fetchData };
}

export default function CityDemandMap() {
  const { data, modalities, period, setPeriod, fetchData } = useCityDemandData();
  const [zoomLevel, setZoomLevel] = useState(4);

  // Normalización para heatmap
  const maxCount = Math.max(...data.map((d) => d.count), 1);
  const heatmapPoints: [number, number, number][] = data.map((d) => [
    d.coords[0],
    d.coords[1],
    d.count / maxCount,
  ]);

  const colorMap: Record<string, string> = {
    presencial: "red",
    remoto: "green",
    hibrido: "orange",
    fully_remote: "blue",
  };

  return (
    <Card className="bg-[#111] text-white rounded-xl border border-gray-700">
      <CardContent className="p-6 flex flex-col items-center gap-4">
        {/* Título */}
        <h2 className="text-center text-sm font-semibold text-white">
          DEMANDA POTENCIAL POR CIUDAD
        </h2>

        {/* Dropdown periodo */}
        <div className="flex gap-2 items-center text-sm">
          <label htmlFor="period" className="text-gray-300">Periodo:</label>
          <select
            id="period"
            value={period}
            onChange={(e) => setPeriod(e.target.value as any)}
            className="bg-gray-800 border border-gray-600 rounded px-2 py-1 text-white text-xs"
          >
            <option value="quarter">Trimestre actual</option>
            <option value="year">Año actual</option>
            <option value="all">Todos los datos</option>
          </select>
        </div>

        {/* Mapa */}
        <div className="relative w-full h-[440px] rounded-lg overflow-hidden">
          <MapContainer
            center={[-15, -72]}
            zoom={4}
            style={{ height: "100%", width: "100%" }}
            className="rounded-lg"
          >
            <TileLayer
              url="https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png"
              attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/">CARTO</a>'
              subdomains={["a", "b", "c", "d"]}
            />

            {/* Eventos de mapa (fetchData + guarda zoomLevel) */}
            <MapEvents onChange={fetchData} onZoom={setZoomLevel} />

            {/* Heatmap siempre activo */}
            <HeatmapLayer points={heatmapPoints} />

            {/* Solo mostrar markers detallados si zoom >= 5 */}
            {zoomLevel >= 5 &&
              data.map((d, i) => (
             <CircleMarker
  key={i}
  center={d.coords}
  radius={Math.min(40, 4 + Math.log(d.count + 1) * 4)} // 👈 escala más amplia
  color={colorMap[d.modality.toLowerCase()] || "blue"}
  fillOpacity={0.6}
>
  <Tooltip direction="top" offset={[0, -5]} opacity={1} permanent>
    {d.city}, {d.country} — {d.count} ofertas ({d.modality})
  </Tooltip>
</CircleMarker>

              ))}
          </MapContainer>

          {/* Leyenda con modalidades globales */}
          <div className="absolute bottom-6 right-6 z-[1000] text-xs text-gray-200 bg-black/60 p-2 rounded">
            <p>🔴 Presencial: {modalities["no_remote"] || 0}</p>
            <p>🟢 Remoto local: {modalities["remote_local"] || 0}</p>
            <p>🟠 Híbrido: {modalities["hybrid"] || 0}</p>
            <p>🔵 Full Remoto: {modalities["fully_remote"] || 0}</p>
          </div>

          {/* Contador full_remote */}
          <div className="absolute bottom-6 left-6 text-xs text-gray-200 bg-black/70 px-3 py-2 rounded-lg shadow">
            {modalities["fully_remote"] || 0} ofertas 100% remotas
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
