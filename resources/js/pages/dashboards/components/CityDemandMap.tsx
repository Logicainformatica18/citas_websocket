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

  useEffect(() => {
    const fetchData = async () => {
      try {
        const res = await axios.get("/ai/city-demand"); // 👈 Ruta que conecte con CityDemandAIController@index
        const results = res.data.results || [];

        // 🔹 Geocodificación simple: puedes expandir este diccionario
        const geoMap: Record<string, [number, number]> = {
          "Lima, Perú": [-12.0464, -77.0428],
          "Arequipa, Perú": [-16.409, -71.537],
          "Trujillo, Perú": [-8.11599, -79.02998],
          "Cusco, Perú": [-13.53195, -71.96746],
          "Santiago, Chile": [-33.4489, -70.6693],
          "Valparaíso, Chile": [-33.0472, -71.6127],
        };

        const mapped = results.map((r: any) => {
          const key = `${r.city}, ${r.country}`;
          return {
            ...r,
            coords: geoMap[key] || [-9.19, -75.015], // fallback: centro de Perú
            modality: Object.keys(r.modalidad || {})[0] || "presencial", // primera modalidad encontrada
          };
        });

        setData(mapped);
      } catch (err) {
        console.error("Error cargando city demand", err);
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
    "no_remote": "red",
    remoto: "green",
    "fully_remote": "green",
    hibrido: "orange",
    hybrid: "orange",
  };

  return (
    <Card className="bg-[#111] text-white rounded-xl border border-gray-700">
      <CardContent className="p-6 flex flex-col items-center">
        <h2 className="text-center text-sm font-semibold text-white mb-4">
          DEMANDA POTENCIAL POR CIUDAD (Perú, Chile - Get on Board)
        </h2>

        <div className="relative w-full h-[440px] rounded-lg overflow-hidden">
          <MapContainer
            center={[-15, -72]} // centro Perú/Chile
            zoom={4}
            style={{ height: "100%", width: "100%" }}
            className="rounded-lg"
          >
            <TileLayer
              url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
              attribution="&copy; OpenStreetMap contributors"
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
