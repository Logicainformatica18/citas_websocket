import { Card, CardContent } from "@/components/ui/card";
import { MapContainer, TileLayer, useMap } from "react-leaflet";
import "leaflet/dist/leaflet.css";
import { useEffect } from "react";
import "leaflet.heat";

// 🔥 Capa de Heatmap
function HeatmapLayer({ points }: { points: [number, number, number][] }) {
  const map = useMap();

  useEffect(() => {
    // @ts-ignore - leaflet.heat no tiene tipado oficial
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
  // 📊 Ejemplo de puntos [lat, lng, intensidad]
  const heatmapPoints: [number, number, number][] = [
    [40.7128, -74.006, 0.9], // New York
    [51.5074, -0.1278, 0.8], // London
    [35.6895, 139.6917, 1.0], // Tokyo
    [-12.0464, -77.0428, 0.6], // Lima
    [48.8566, 2.3522, 0.7], // Paris
  ];

  return (
    <Card className="bg-[#111] text-white rounded-xl border border-gray-700">
      <CardContent className="p-6 flex flex-col items-center">
        {/* Título */}
        <h2 className="text-center text-sm font-semibold text-white mb-4">
          DEMANDA POTENCIAL POR CIUDAD
        </h2>

        {/* Contenedor del mapa */}
        <div className="relative w-full h-[440px] rounded-lg overflow-hidden">
          <MapContainer
            center={[20, 0]} // 🌍 vista global
            zoom={2}
            style={{ height: "100%", width: "100%" }}
            className="rounded-lg"
          >
            <TileLayer
              url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
              attribution="&copy; OpenStreetMap contributors"
            />
            <HeatmapLayer points={heatmapPoints} />
          </MapContainer>

          {/* Leyenda */}
          <div className="absolute bottom-6 right-6 text-xs text-gray-200">
            <p className="mb-1 tracking-wide">LOW DEMAND → HIGH</p>
            <div className="w-28 h-2 bg-gradient-to-r from-yellow-200 via-orange-500 to-red-700 rounded"></div>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
