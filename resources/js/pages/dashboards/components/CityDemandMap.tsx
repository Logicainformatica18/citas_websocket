import { Card, CardContent } from "@/components/ui/card";
import { MapContainer, TileLayer, CircleMarker, Tooltip, useMap } from "react-leaflet";
import "leaflet/dist/leaflet.css";
import { useEffect, useState } from "react";
import "leaflet.heat";
import axios from "axios";

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

export default function CityDemandMap() {
  // 📊 Data desde backend
  const [data, setData] = useState<
    { city: string; country: string; count: number; modality: string; coords: [number, number] }[]
  >([]);
  const [modalities, setModalities] = useState<Record<string, number>>({});

  const normalizeModality = (m: string | null) => {
    if (!m) return "presencial";
    const val = m.toLowerCase();

    if (["fully_remote", "full_remote"].includes(val)) return "fully_remote";
    if (["remote_local"].includes(val)) return "remoto";
    if (["hybrid", "hibrido"].includes(val)) return "hibrido";
    if (["no_remote", "presencial"].includes(val)) return "presencial";
    return "presencial";
  };

  useEffect(() => {
    const fetchData = async () => {
      try {
        const res = await axios.get("/ai/city-demand");
const payload = res.data.data || res.data;   // 👈 asegura leer el nivel correcto
const results = payload.results || [];
const mods = payload.modalities || {};

        const mapped = results
          .filter((r: any) => r.lat && r.lng) // solo con coordenadas
          .flatMap((r: any) =>
            Object.entries(r.modalidad || {}).map(([key, count]) => ({
              ...r,
              coords: [r.lat, r.lng],
              modality: normalizeModality(key),
              count,
            }))
          );

        setData(mapped);
        setModalities(mods);



      } catch (err: any) {
        console.error("❌ Error cargando city demand", err);
      }
    };

    fetchData();
  }, []);

  // Heatmap
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
      <CardContent className="p-6 flex flex-col items-center">
        <h2 className="text-center text-sm font-semibold text-white mb-4">
          DEMANDA POTENCIAL POR CIUDAD
        </h2>

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

            <HeatmapLayer points={heatmapPoints} />

            {data.map((d, i) => (
              <CircleMarker
                key={i}
                center={d.coords}
                radius={8 + d.count * 0.3}
                color={colorMap[d.modality.toLowerCase()] || "blue"}
                fillOpacity={0.7}
              >
                <Tooltip direction="top" offset={[0, -5]} opacity={1} permanent>
                  {d.city}, {d.country} — {d.count} ofertas ({d.modality})
                </Tooltip>
              </CircleMarker>
            ))}
          </MapContainer>

         {/* Leyenda con modalidades globales (tal cual backend) */}
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
