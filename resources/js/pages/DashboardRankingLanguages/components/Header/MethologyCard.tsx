import { useState } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import { Sparkles } from "lucide-react";

interface MethodologyCardProps {
  laborWeight?: number;
  trendWeight?: number;
}

export default function MethodologyCard({
  laborWeight = 70,
  trendWeight = 30,
}: MethodologyCardProps) {

  const labor = Number(laborWeight) || 70;
  const trend = Number(trendWeight) || 100 - labor;

  const laborFactor = (labor / 100).toFixed(1);
  const trendFactor = (trend / 100).toFixed(1);

  const [loading, setLoading] = useState(false);

  const runDiscovery = async () => {
    setLoading(true);

    try {
      await axios.post(
        "/dashboard/ranking/languages/discover-gaps",
        {
          limit: 10,
          sleep: 2,
        }
      );

      Swal.fire({
        icon: "success",
        title: "IA ejecutada",
        text: "Brechas de lenguajes detectadas correctamente.",
      });

      // opcional: recargar datos
      window.location.reload();

    } catch (error: any) {
      Swal.fire({
        icon: "error",
        title: "Error",
        text:
          error.response?.data?.message ??
          "No se pudo ejecutar el descubrimiento.",
      });
    }

    setLoading(false);
  };

  return (
    <div className="border rounded-xl p-4 text-sm bg-[#F5FCFE] dark:bg-[#0F2A3A] dark:border-[#1E3A4A]">

      <p className="font-semibold mb-2 text-slate-900 dark:text-slate-100">
        Metodología de Cálculo
      </p>

      <ul className="space-y-1 text-slate-700 dark:text-slate-300">
        <li>
          • <strong>{labor}%</strong> Demanda laboral de lenguajes
        </li>
        <li>
          • <strong>{trend}%</strong> Presencia en reportes de tendencias tecnológicas
        </li>
      </ul>

      <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">
        El score final se calcula ponderando la demanda laboral y la
        evidencia en reportes especializados.
      </p>

      <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">
        Fórmula general: <br />
        <span className="font-mono">
          Score = ({laborFactor} × Laboral) + ({trendFactor} × Tendencias)
        </span>
      </p>

      {/* 🔥 BOTÓN IA */}
      <div className="mt-4">
        <button
          onClick={runDiscovery}
          disabled={loading}
          className="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-lg 
                     bg-[#00B6E8] hover:bg-[#0095c4] text-white 
                     transition disabled:opacity-60"
        >
          <Sparkles size={18} />
          {loading ? "Ejecutando IA..." : "Detectar Brechas con IA"}
        </button>
      </div>

    </div>
  );
}