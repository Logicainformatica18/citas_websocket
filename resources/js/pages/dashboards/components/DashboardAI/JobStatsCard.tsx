import React, { useEffect, useState } from "react";
import axios from "axios";
import {
  Briefcase,
  PlusCircle,
  CalendarClock,
  Globe,
  ChevronDown,
  ChevronUp,
} from "lucide-react";

export default function JobStatsCard() {
  const [stats, setStats] = useState<any | null>(null);
  const [loading, setLoading] = useState(true);
  const [expanded, setExpanded] = useState(false); // 👈 Acordeón

  useEffect(() => {
    axios
      .get("/api/job-stats")
      .then((res) => setStats(res.data))
      .catch((err) => console.error("❌ Error cargando estadísticas:", err))
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div className="text-gray-400 text-sm animate-pulse">
        Cargando estadísticas generales...
      </div>
    );
  }

  if (!stats) return null;

  const totals = stats.totals || {};
  const sourceTotals = stats.sources_totals || {};

  return (
    <div className="">

      {/* ======== ★ PRIMERA FILA (Siempre visible) ======== */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

        {/* Total Ofertas */}
        <div className="bg-slate-800 p-4 rounded-xl shadow-md flex items-center gap-4">
          <Briefcase size={32} className="text-blue-400" />
          <div>
            <p className="text-sm text-gray-400">Total ofertas</p>
            <h2 className="text-2xl font-bold">{totals.total_offers ?? 0}</h2>
          </div>
        </div>

        {/* Registradas hoy */}
        <div className="bg-slate-800 p-4 rounded-xl shadow-md flex items-center gap-4">
          <PlusCircle size={32} className="text-green-400" />
          <div>
            <p className="text-sm text-gray-400">Registradas hoy</p>
            <h2 className="text-2xl font-bold">{totals.total_today ?? 0}</h2>
          </div>
        </div>

        {/* Publicadas hoy */}
        <div className="bg-slate-800 p-4 rounded-xl shadow-md flex items-center gap-4">
          <CalendarClock size={32} className="text-yellow-400" />
          <div>
            <p className="text-sm text-gray-400">Publicadas hoy</p>
            <h2 className="text-2xl font-bold">{totals.published_today ?? 0}</h2>
          </div>
        </div>


      </div>

      {/* ======== ★ BOTÓN DE EXPANDIR / COLAPSAR ======== */}
      <div className="flex justify-center mt-4">
        <button
          onClick={() => setExpanded(!expanded)}
          className="text-sm text-blue-400 hover:text-blue-300 flex items-center gap-1 transition"
        >
          {expanded ? (
            <>
              Ver menos <ChevronUp size={18} />
            </>
          ) : (
            <>
              Ver más <ChevronDown size={18} />
            </>
          )}
        </button>
      </div>

      {/* ======== ★ CONTENIDO EXPANDIDO ======== */}
      <div
        className={`grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4 overflow-hidden transition-all duration-300 ${
          expanded ? "max-h-[2000px] opacity-100" : "max-h-0 opacity-0"
        }`}
      >
        {Object.entries(sourceTotals).map(([source, total]: any) => (
          <div
            key={source}
            className="bg-slate-900 p-4 rounded-xl shadow-md border border-slate-700 flex items-center gap-4"
          >
            <Globe size={28} className="text-cyan-400" />
            <div>
              <p className="text-sm text-gray-400">{source}</p>
              <h2 className="text-2xl font-bold">{total}</h2>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
