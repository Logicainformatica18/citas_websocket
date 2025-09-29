import { Card, CardContent } from "@/components/ui/card";
import { MapContainer, TileLayer, CircleMarker, Tooltip, useMap } from "react-leaflet";
import "leaflet/dist/leaflet.css";
import { useEffect, useState } from "react";
import "leaflet.heat";

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
  // 📊 Datos simulados (city, count, modality)
  const [data] = useState([
    { city: "Lima", coords: [-12.0464, -77.0428], count: 20, modality: "presencial" },
    { city: "Arequipa", coords: [-16.409, -71.537], count: 12, modality: "remoto" },
    { city: "Trujillo", coords: [-8.11599, -79.02998], count: 5, modality: "hibrido" },
    { city: "Cusco", coords: [-13.53195, -71.96746], count: 8, modality: "remoto" },
  ]);

  // Normalizar para heatmap
  const maxCount = Math.max(...data.map((d) => d.count), 1);
  const heatmapPoints: [number, number, number][] = data.map((d) => [
    d.coords[0],
    d.coords[1],
    d.count / maxCount,
  ]);

  // Diccionario de colores
  const colorMap: Record<string, string> = {
    presencial: "red",
    remoto: "green",
    hibrido: "orange",
  };

  return (
    <Card className="bg-[#111] text-white rounded-xl border border-gray-700">
      <CardContent className="p-6 flex flex-col items-center">
        <h2 className="text-center text-sm font-semibold text-white mb-4">
          DEMANDA POTENCIAL POR CIUDAD (Perú - Get on Board)
        </h2>

        <div className="relative w-full h-[440px] rounded-lg overflow-hidden">
          <MapContainer
            center={[-9.19, -75.015]} // centro de Perú
            zoom={5}
            style={{ height: "100%", width: "100%" }}
            className="rounded-lg"
          >
            <TileLayer
              url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
              attribution="&copy; OpenStreetMap contributors"
            />
            <HeatmapLayer points={heatmapPoints} />

            {/* Círculos con colores */}
            {data.map((d) => (
              <CircleMarker
                key={d.city}
                center={d.coords}
                radius={8 + d.count * 0.3}
                color={colorMap[d.modality] || "blue"}
                fillOpacity={0.7}
              >
                <Tooltip direction="top" offset={[0, -5]} opacity={1} permanent>
                  {d.city} — {d.count} ofertas ({d.modality})
                </Tooltip>
              </CircleMarker>
            ))}
          </MapContainer>

          {/* Leyenda */}
          <div className="absolute bottom-6 right-6 text-xs text-gray-200 bg-black/50 p-2 rounded">
            <p>🔴 Presencial</p>
            <p>🟢 Remoto</p>
            <p>🟠 Híbrido</p>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
