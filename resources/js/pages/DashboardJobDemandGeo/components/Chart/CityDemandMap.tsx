import { Card, CardContent } from "@/components/ui/card";
import { MapContainer, TileLayer, useMap } from "react-leaflet";
import { useEffect, useState, useCallback, useRef } from "react";
import { MapPin } from "lucide-react";
import { usePage } from "@inertiajs/react";
import axios from "axios";
import "leaflet/dist/leaflet.css";
import "leaflet.heat";
import L from "leaflet";

/* ================= HEATMAP LAYER ================= */

function GlobalHeatLayer({ data }: { data: any[] }) {

    const map = useMap();
    const layerRef = useRef<any>(null);

    const gradient = {
        0.1: "#1CBCE8",
        0.3: "#7DD3FC",
        0.5: "#FACC15",
        0.7: "#FB923C",
        1.0: "#EF4444",
    };

    useEffect(() => {

        if (!map) return;

        if (!layerRef.current) {

            // @ts-ignore
            layerRef.current = L.heatLayer([], {
                radius: 40,
                blur: 25,
                maxZoom: 8,
                gradient,
                minOpacity: 0.35,
            }).addTo(map);

        }

        if (data?.length) {

            const points = data.map((d) => [
                d.lat,
                d.lng,
                d.intensity ?? 0.3,
            ]);

            layerRef.current.setLatLngs(points);

        }

        return () => {
            if (layerRef.current) {
                map.removeLayer(layerRef.current);
                layerRef.current = null;
            }
        };

    }, [map, data]);

    return null;
}

/* ================= MAP EVENTS ================= */

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
            map.off("zoomend", handler);
            map.off("moveend", handler);
        };

    }, [map, onZoom]);

    return null;
}

/* ================= MAIN ================= */

export default function CityDemandHeatmap() {

    const pageProps = usePage().props as any;
    const filters = pageProps?.filters ?? {};

    const [data, setData] = useState<any[]>([]);
    const [loading, setLoading] = useState(false);
    const [zoom, setZoom] = useState(5);

    const mounted = useRef(false);
    const lastQuery = useRef("");

    /* ================= FETCH ================= */

    const fetchData = useCallback(async () => {

        const queryKey = JSON.stringify({
            year: filters.year,
            period: filters.period,
            region: filters.region,
            country: filters.country,
            zoom,
        });

        if (queryKey === lastQuery.current) return;

        lastQuery.current = queryKey;

        try {

            setLoading(true);

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
                }
            );

            setData(res.data?.results ?? []);

        } catch (e) {

            console.error("Error cargando heatmap", e);

        } finally {

            setLoading(false);

        }

    }, [filters.year, filters.period, filters.region, filters.country, zoom]);

    /* ================= LOAD AFTER PAGE ================= */

    useEffect(() => {

        if (!mounted.current) {

            mounted.current = true;

            setTimeout(() => {
                fetchData();
            }, 200);

            return;
        }

        fetchData();

    }, [fetchData]);

    return (

        <Card className="border border-[#00B6E8]/30 bg-white dark:bg-[#0A2540] shadow-xl">

            <CardContent className="p-6 flex flex-col gap-4">

                {/* HEADER */}

                <div className="flex items-center gap-3">

                    <div className="h-9 w-9 rounded-lg bg-[#00B6E8] flex items-center justify-center">
                        <MapPin className="h-5 w-5 text-white" />
                    </div>

                    <h3 className="text-sm font-bold text-[#0A2540] dark:text-white">
                        Mapa de calor – Demanda laboral
                    </h3>

                </div>

                {/* MAP */}

                <div className="relative w-full h-[460px] rounded-xl overflow-hidden">

                    <MapContainer
                        center={[-12.0464, -77.0428]}
                        zoom={zoom}
                        style={{ height: "100%", width: "100%" }}
                        scrollWheelZoom
                    >

                        <TileLayer
                            url="https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png"
                            attribution="&copy; OpenStreetMap & CARTO"
                        />

                        <MapEvents onZoom={(z) => setZoom(z)} />

                        <GlobalHeatLayer data={data} />

                    </MapContainer>

                    {loading && (
                        <div className="absolute inset-0 flex items-center justify-center bg-black/40 text-white text-sm">
                            Cargando mapa…
                        </div>
                    )}

                </div>

            </CardContent>

        </Card>

    );
}

