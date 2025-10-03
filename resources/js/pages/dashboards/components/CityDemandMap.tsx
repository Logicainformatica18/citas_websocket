import { Card, CardContent } from "@/components/ui/card";
import { MapContainer, TileLayer, CircleMarker, Tooltip, useMap, Popup } from "react-leaflet";
import { useEffect, useState, useCallback } from "react";
import "leaflet/dist/leaflet.css";
import "leaflet.heat";
import axios from "axios";

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
    const [period, setPeriod] = useState<"quarter" | "year" | "all">("quarter");

    const normalizeModality = (m: string | null) => {
        if (!m) return "presencial";
        const val = m.toLowerCase();

        if (["fully_remote", "full_remote"].includes(val)) return "fully_remote";
        if (["remote_local"].includes(val)) return "remoto";
        if (["hybrid", "híbrido", "hibrido"].includes(val)) return "hibrido";
        if (["no_remote", "presencial"].includes(val)) return "presencial";
        return "presencial";
    };

    const fetchData = useCallback(
        async (bounds: any, zoom: number) => {
            try {
                const res = await axios.get("/ai/city-demand", {
                    params: {
                        zoom,
                        period,
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
    const [countryFilter, setCountryFilter] = useState<string>("");

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

    // Filtrar datos por país (o global si vacío)
    const filteredData = countryFilter
        ? data.filter(d => d.country.toLowerCase() === countryFilter.toLowerCase())
        : data;

    // Totales por modalidad
    const totals = filteredData.reduce((acc, d) => {
        acc[d.modality] = (acc[d.modality] || 0) + d.count;
        return acc;
    }, {} as Record<string, number>);

    return (
        <Card className="bg-[#111] text-white rounded-xl border border-gray-700">
            <CardContent className="p-6 flex flex-col items-center gap-4">
                {/* Título */}
                <h2 className="text-center text-sm font-semibold text-white">
                    DEMANDA POTENCIAL POR CIUDAD
                </h2>

                {/* Controles arriba */}
                <div className="flex gap-4 items-center text-sm">
                    {/* Dropdown periodo */}
                    <div>
                        <label htmlFor="period" className="text-gray-300">Periodo:</label>
                        <select
                            id="period"
                            value={period}
                            onChange={(e) => setPeriod(e.target.value as any)}
                            className="ml-2 bg-gray-800 border border-gray-600 rounded px-2 py-1 text-white text-xs"
                        >
                            <option value="quarter">Trimestre actual</option>
                            <option value="year">Año actual</option>
                            <option value="all">Todos los datos</option>
                        </select>
                    </div>

                    {/* Input país */}
                    <div>
                        <label htmlFor="country" className="text-gray-300">País:</label>
                        <input
                            id="country"
                            type="text"
                            value={countryFilter}
                            onChange={(e) => setCountryFilter(e.target.value)}
                            placeholder="Ej: Peru"
                            className="ml-2 bg-gray-800 border border-gray-600 rounded px-2 py-1 text-white text-xs"
                        />
                    </div>
                </div>

                {/* Mapa */}
                <div className="relative w-full h-[440px] rounded-lg overflow-hidden">
                    <MapContainer
                        center={[-12.0464, -77.0428]} // 👈 Lima como centro
                        zoom={6}                      // 👈 Más cerca que 4
                        style={{ height: "100%", width: "100%" }}
                        className="rounded-lg"
                    >

                        <TileLayer
                            url="https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png"
                            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/">CARTO</a>'
                            subdomains={["a", "b", "c", "d"]}
                        />

                        <MapEvents onChange={fetchData} onZoom={setZoomLevel} />
                        <HeatmapLayer points={heatmapPoints} />

                        {zoomLevel >= 5 &&
                            filteredData.map((d, i) => (
                                <>
                                    <CircleMarker
                                        key={i}
                                        center={d.coords}
                                        radius={Math.min(80, 2 + Math.sqrt(d.count) * (zoomLevel / 2))}
                                        color={colorMap[d.modality.toLowerCase()] || "blue"}
                                        fillOpacity={0.6}
                                    >
                                        <Tooltip direction="top" offset={[0, -5]} opacity={1}>
                                            {d.city}, {d.country} — {d.count} ofertas ({d.modality})
                                        </Tooltip>

                                        <Popup>
                                            <div style={{ minWidth: "200px" }}>
                                                <h3 style={{ fontWeight: "bold", marginBottom: "4px" }}>
                                                    {d.city}, {d.country}
                                                </h3>
                                                <p>Total ofertas: {d.count}</p>
                                                <p>Modalidad: {d.modality}</p>
                                                <a href={`/jobs?city=${encodeURIComponent(d.city)}`} target="_blank" rel="noopener noreferrer">
                                                    Ver ofertas →
                                                </a>
                                            </div>
                                        </Popup>
                                    </CircleMarker>



                                </>
                            ))}
                    </MapContainer>

                    {/* Leyenda dinámica */}
                    <div className="absolute bottom-6 right-6 z-[1000] text-xs text-gray-200 bg-black/60 p-2 rounded">
                        <p>🔴 Presencial: {totals["presencial"] || 0}</p>
                        <p>🟢 Remoto local: {totals["remoto"] || 0}</p>
                        <p>🟠 Híbrido: {totals["hibrido"] || 0}</p>
                        <p>🔵 Full Remoto: {totals["fully_remote"] || 0}</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
