import { Card, CardContent } from "@/components/ui/card";
import { MapContainer, TileLayer, useMap } from "react-leaflet";
import { useEffect, useState, useCallback, useRef } from "react";
import { MapPin } from "lucide-react";
import { usePage } from "@inertiajs/react";
import axios from "axios";
import "leaflet/dist/leaflet.css";
import "leaflet.heat";
import L from "leaflet";
import { logRequest } from "@/lib/perf";

/* ================= CONFIGURACIÓN DEL MAPA BASE ================= */

// Mapa base oscuro de Esri (no requiere clave). Reemplaza a CARTO, que ahora exige API key.
const TILE_URL =
    "https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}";
const TILE_ATTRIBUTION = "Tiles &copy; Esri &mdash; Esri, DeLorme, NAVTEQ";
const TILE_MAX_ZOOM = 16;

const GRADIENT = {
    0.1: "#1CBCE8",
    0.3: "#7DD3FC",
    0.5: "#FACC15",
    0.7: "#FB923C",
    1.0: "#EF4444",
};

/* ================= CAPA DE CALOR ================= */

function GlobalHeatLayer({ data }: { data: any[] }) {

    const map = useMap();
    const layerRef = useRef<any>(null);

    // Crea la capa una sola vez y la elimina solo al desmontar el mapa
    useEffect(() => {

        if (!map) return;

        // @ts-ignore
        layerRef.current = L.heatLayer([], {
            radius: 40,
            blur: 25,
            maxZoom: 8,
            gradient: GRADIENT,
            minOpacity: 0.35,
        }).addTo(map);

        return () => {
            if (layerRef.current) {
                map.removeLayer(layerRef.current);
                layerRef.current = null;
            }
        };

    }, [map]);

    // Actualiza los puntos sin recrear la capa
    useEffect(() => {

        if (!layerRef.current) return;

        const points = (data ?? []).map((d) => [
            d.lat,
            d.lng,
            d.intensity ?? 0.3,
        ]);

        layerRef.current.setLatLngs(points);

    }, [data]);

    return null;
}

/* ================= EVENTOS DEL MAPA ================= */

function MapEvents({ onZoom }: { onZoom: (z: number) => void }) {

    const map = useMap();
    const debounceRef = useRef<any>(null);

    useEffect(() => {

        const handler = () => {

            clearTimeout(debounceRef.current);

            debounceRef.current = setTimeout(() => {
                onZoom(map.getZoom());
            }, 500);

        };

        map.on("zoomend", handler);
        map.on("moveend", handler);

        return () => {
            clearTimeout(debounceRef.current);
            map.off("zoomend", handler);
            map.off("moveend", handler);
        };

    }, [map, onZoom]);

    return null;
}

/* ================= PRINCIPAL ================= */

export default function CityDemandHeatmap() {

    const pageProps = usePage().props as any;
    const filters = pageProps?.filters ?? {};

    const [data, setData] = useState<any[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [zoom, setZoom] = useState(5);

    const mounted = useRef(false);
    const lastQuery = useRef("");

    // Función estable para no volver a registrar los eventos del mapa en cada render
    const handleZoom = useCallback((z: number) => setZoom(z), []);

    /* ================= CARGA DE DATOS ================= */

    const fetchData = useCallback(async (force = false) => {

        const queryKey = JSON.stringify({
            year: filters.year,
            period: filters.period,
            region: filters.region,
            country: filters.country,
            zoom,
        });

        if (!force && queryKey === lastQuery.current) return;

        lastQuery.current = queryKey;

        // Medición (solo en desarrollo): duración de la petición del mapa.
        const startedAt = performance.now();

        try {

            setLoading(true);
            setError(null);

            const res = await axios.get(
                "/dashboard/indicators/job-demand-geo/heatmap",
                {
                    params: {
                        year: filters.year,
                        period: filters.period,
                        region: filters.region,
                        country: filters.country,
                        zoom,
                    },
                    timeout: 15000,
                }
            );

            setData(res.data?.results ?? []);

            logRequest("Petición del mapa", startedAt, {
                zoom,
                puntos: res.data?.results?.length ?? 0,
            });

        } catch (e) {

            logRequest("Petición del mapa (error)", startedAt, { zoom });

            console.error("Error cargando heatmap", e);

            // Permite reintentar la misma consulta
            lastQuery.current = "";
            setError("No se pudo cargar el mapa de calor.");

        } finally {

            setLoading(false);

        }

    }, [filters.year, filters.period, filters.region, filters.country, zoom]);

    /* ================= CARGA DESPUÉS DE LA PÁGINA ================= */

    useEffect(() => {

        if (!mounted.current) {

            mounted.current = true;

            const timer = setTimeout(() => {
                fetchData();
            }, 200);

            return () => clearTimeout(timer);
        }

        fetchData();

    }, [fetchData]);

    return (

        <Card className="border border-[#00B6E8]/30 bg-white dark:bg-[#0A2540] shadow-xl">

            <CardContent className="p-6 flex flex-col gap-4">

                {/* ENCABEZADO */}

                <div className="flex items-center gap-3">

                    <div className="h-9 w-9 rounded-lg bg-[#00B6E8] flex items-center justify-center">
                        <MapPin className="h-5 w-5 text-white" />
                    </div>

                    <h3 className="text-sm font-bold text-[#0A2540] dark:text-white">
                        Mapa de calor – Demanda laboral
                    </h3>

                </div>

                {/* MAPA */}

                <div className="relative w-full h-[460px] rounded-xl overflow-hidden">

                    <MapContainer
                        center={[-12.0464, -77.0428]}
                        zoom={zoom}
                        maxZoom={TILE_MAX_ZOOM}
                        style={{ height: "100%", width: "100%" }}
                        scrollWheelZoom
                    >

                        <TileLayer
                            url={TILE_URL}
                            attribution={TILE_ATTRIBUTION}
                            maxZoom={TILE_MAX_ZOOM}
                        />

                        <MapEvents onZoom={handleZoom} />

                        <GlobalHeatLayer data={data} />

                    </MapContainer>

                    {/* Indicador de carga pequeño que no bloquea la interacción con el mapa */}
                    {loading && (
                        <div className="pointer-events-none absolute top-3 right-3 z-[1000] rounded-md bg-black/60 px-3 py-1.5 text-xs text-white">
                            Cargando mapa…
                        </div>
                    )}

                    {/* Mensaje de error con opción de reintentar */}
                    {error && !loading && (
                        <div className="absolute top-3 right-3 z-[1000] flex items-center gap-2 rounded-md bg-red-600/90 px-3 py-1.5 text-xs text-white">
                            <span>{error}</span>
                            <button
                                type="button"
                                className="rounded bg-white/20 px-2 py-0.5 font-semibold hover:bg-white/30"
                                onClick={() => fetchData(true)}
                            >
                                Reintentar
                            </button>
                        </div>
                    )}

                </div>

            </CardContent>

        </Card>

    );
}