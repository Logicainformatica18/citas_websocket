import { Card, CardContent } from "@/components/ui/card";
import { MapContainer, TileLayer, useMap } from "react-leaflet";
import { useEffect, useState, useCallback, useRef } from "react";
import { Filter, FileSpreadsheet, FileText } from "lucide-react";
import "leaflet/dist/leaflet.css";
import "leaflet.heat";
import axios from "axios";
import CityDemandFilters from "./CityDemandFilters";

function MapEvents({ onChange, onZoom }: any) {
  const map = useMap();
  const timeoutRef = useRef<NodeJS.Timeout | null>(null);
  const lastPosition = useRef<string>("");

  useEffect(() => {
    const update = () => {
      // Serializa el estado actual del mapa (centro + zoom)
      const current = JSON.stringify({
        center: map.getCenter(),
        zoom: map.getZoom(),
      });

      // Si no ha cambiado realmente, no vuelvas a disparar
      if (current === lastPosition.current) return;
      lastPosition.current = current;

      // Aplica debounce real de 3 s para evitar spam
      if (timeoutRef.current) clearTimeout(timeoutRef.current);
      timeoutRef.current = setTimeout(() => {
        onChange(map.getBounds(), map.getZoom());
        onZoom(map.getZoom());
      }, 3000);
    };

    map.on("moveend", update);
    map.on("zoomend", update);

    // Ejecuta solo una vez al montar
    update();

    return () => {
      map.off("moveend", update);
      map.off("zoomend", update);
      if (timeoutRef.current) clearTimeout(timeoutRef.current);
    };
  }, [map, onChange, onZoom]);

  return null;
}


function GlobalHeatLayer({ data }: { data: any[] }) {
  const map = useMap();
  const layerRef = useRef<any>(null);

  // 🔥 Gradiente clásico tipo "heatmap de calor" (azul → verde → amarillo → rojo)
  const gradient = {
    0.1: "rgba(0, 0, 255, 0.6)",      // azul
    0.3: "rgba(0, 255, 255, 0.7)",    // cian
    0.5: "rgba(0, 255, 0, 0.8)",      // verde
    0.7: "rgba(255, 255, 0, 0.9)",    // amarillo
    0.9: "rgba(255, 165, 0, 0.95)",   // naranja
    1.0: "rgba(255, 0, 0, 1.0)",      // rojo intenso
  };

useEffect(() => {
  if (!map) return;

  // Si aún no existe la capa, créala UNA SOLA VEZ
  if (!layerRef.current) {
    // @ts-ignore
    layerRef.current = (window as any).L.heatLayer([], {
      radius: 45,
      blur: 30,
      maxZoom: 8,
      gradient,
      minOpacity: 0.3,
    }).addTo(map);
  }

  if (data?.length) {
    const points = data.map((d) => [d.lat, d.lng, d.intensity || d.total || 1]);
    const maxVal = Math.max(...points.map((p) => p[2]), 1);
    const norm = points.map(([lat, lng, v]) => [lat, lng, v / maxVal]);

    // ✅ solo actualiza los puntos, sin recrear la capa
    layerRef.current.setLatLngs(norm);
  }

  // ❌ ya no elimines la capa en cada cambio de data
  // solo al desmontar el componente completo
  return () => {
    if (layerRef.current) {
      try {
        map.removeLayer(layerRef.current);
      } catch {}
      layerRef.current = null;
    }
  };
}, [map, data]);

  return null;
}


export default function CityDemandMap() {
  const [showFilters, setShowFilters] = useState(false);
  const [metadata, setMetadata] = useState({ countries: [], sources: [], modalities: [], years: [] });
  const [filters, setFilters] = useState({
    sources: [] as string[],
    modalities: [] as string[],
    countries: [] as string[],
    year: new Date().getFullYear(),
    quarter: "",
    start_date: "",
    end_date: "",
  });

  const [data, setData] = useState<any[]>([]);
  const [topCountries, setTopCountries] = useState<any[]>([]);
  const [summary, setSummary] = useState<any>(null);
  const [zoomLevel, setZoomLevel] = useState(5);
  const [lastBounds, setLastBounds] = useState<any>(null);
  const [loading, setLoading] = useState(false);

 const prevFilters = useRef<any>(null);

  const fetchData = useCallback(async (filters: any, bounds: any, zoom: number) => {
    try {
      setLoading(true);
      const res = await axios.get("/ai/city-demand/get-data", {
        params: { zoom, ...filters },
      });
      setData(res.data.results || []);
      setTopCountries(res.data.top_countries || []);
      setSummary(res.data.summary);
    } catch (err) {
      console.error("❌ Error al obtener datos", err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    axios.get("api/ai/city-demand/metadata").then((res) => setMetadata(res.data));
  }, []);
useEffect(() => {
  // 🚀 Carga inicial
  fetchData(filters, null, zoomLevel);
}, []);


useEffect(() => {
  // ✅ Evitar llamadas innecesarias si los filtros no cambiaron realmente
  if (JSON.stringify(prevFilters.current) === JSON.stringify(filters)) return;
  prevFilters.current = filters;

  if (lastBounds) fetchData(filters, lastBounds, zoomLevel);
}, [filters, lastBounds, zoomLevel]);


  const handleExport = (format: "pdf" | "excel") => {
    const query = new URLSearchParams({ ...filters, format });
    window.open(`/ai/city-demand/export?${query.toString()}`, "_blank");
  };

  return (
    <Card className="bg-[#111] text-white rounded-xl border border-gray-700 relative">
      <CardContent className="p-6 flex flex-col gap-4 relative">
        <div className="flex justify-between items-center">
          <h2 className="text-sm font-semibold">🌍 Demanda laboral por ciudad</h2>
   <div className="flex gap-2">
  {/* Filtro */}
  <button
    onClick={() => setShowFilters(!showFilters)}
    title="Filtros"
    className="bg-gray-800 hover:bg-gray-700 rounded-full p-2 transition"
  >
    <Filter className="w-4 h-4 text-gray-200" />
  </button>

  {/* Exportar PDF — restringido a 1 país */}
  <button
    onClick={() => {
      if (filters.countries.length !== 1) {
        alert("⚠️ Para generar el PDF, selecciona exactamente un país.");
        return;
      }
      handleExport("pdf");
    }}
    title="Exportar PDF (solo 1 país)"
    className={`rounded-full p-2 transition ${
      filters.countries.length === 1
        ? "bg-gray-800 hover:bg-gray-700"
        : "bg-gray-900 opacity-50 cursor-not-allowed"
    }`}
    disabled={filters.countries.length !== 1}
  >
    <FileText className="w-4 h-4 text-red-400" /> {/* 📄 Ícono PDF */}
  </button>

  {/* Exportar Excel */}
  <button
    onClick={() => handleExport("excel")}
    title="Exportar Excel"
    className="bg-gray-800 hover:bg-gray-700 rounded-full p-2 transition"
  >
    <FileSpreadsheet className="w-4 h-4 text-green-400" /> {/* 🟢 Ícono Excel */}
  </button>
</div>


        </div>

        {/* KPIs */}
        {summary && (
          <div className="grid grid-cols-3 gap-3 text-xs text-gray-300">
            <div className="bg-gray-900/60 rounded-lg p-2">
              <p>Total ofertas</p>
              <p className="text-white font-semibold text-lg">{summary.total_offers}</p>
            </div>
            <div className="bg-gray-900/60 rounded-lg p-2">
              <p>Modalidad Top</p>
              <p className="text-white font-semibold">{summary.top_modality?.modality || "-"}</p>
            </div>
            <div className="bg-gray-900/60 rounded-lg p-2">
              <p>Fuente Top</p>
              <p className="text-white font-semibold">{summary.top_source?.source || "-"}</p>
            </div>
          </div>
        )}

        {/* Mapa */}
        <div className="relative w-full h-[460px] rounded-lg overflow-hidden mt-2">
          <MapContainer center={[-12.0464, -77.0428]} zoom={5} style={{ height: "100%", width: "100%" }}>
            <TileLayer
              url="https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png"
              attribution='&copy; <a href="https://carto.com/">CARTO</a> &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a>'
            />
           <MapEvents
  onChange={(bounds: any) => setLastBounds(bounds)}
  onZoom={(z: number) => setZoomLevel(z)}
/>

            <GlobalHeatLayer data={data} />
          </MapContainer>

          {/* Top países */}
          <div className="absolute bottom-6 right-6 z-[1000] text-xs text-gray-200 bg-black/60 p-2 rounded w-56">
            <p className="font-semibold mb-1">🌎 Top países</p>
            {topCountries.map((c, i) => (
              <p key={i}>
                #{i + 1} {c.country} — <span className="text-white">{c.total}</span>
              </p>
            ))}
          </div>

          {loading && (
            <div className="absolute inset-0 flex items-center justify-center bg-black/40 text-white text-sm">
              Cargando datos...
            </div>
          )}
        </div>

        {/* Panel lateral */}
        <CityDemandFilters
          show={showFilters}
          onClose={() => setShowFilters(false)}
          metadata={metadata}
          filters={filters}
          setFilters={setFilters}
        />
      </CardContent>
    </Card>
  );
}
