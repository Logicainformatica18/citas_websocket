import { GraduationCap, Sparkles, Loader2 } from "lucide-react";
import { router, usePage } from "@inertiajs/react";
import { useState } from "react";
import axios from "axios";

interface Career {
  id: number;
  name: string;
}

export default function CareerFilter() {
  const { filters, availableCareers } = usePage().props as {
    filters: {
      career_id?: number | null;
      year: number;
      period: "s1" | "s2";
    };
    availableCareers: Career[];
  };

  const [loading, setLoading] = useState(false);

  const onChangeCareer = (careerId: number | null) => {
    router.get(
      "/dashboard/indicators/pe-alignment",
      {
        ...filters,
        career_id: careerId,
        page: 1,
      },
      {
        preserveState: true,
        replace: true,
      }
    );
  };

  const handleAnalyzeAI = async () => {
    if (!filters.career_id) {
      alert("Selecciona una carrera primero");
      return;
    }

    try {
      setLoading(true);

     await axios.post(
  "/dashboard/indicators/pe-alignment/analyze-career",
  {
    career_id: filters.career_id,
    year: filters.year,
  }
);


      alert("Análisis IA ejecutado correctamente");
    } catch (error) {
      console.error(error);
      alert("Error al ejecutar IA");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div
      className="
        rounded-2xl
        border
        bg-white
        p-6
        shadow-sm
        dark:bg-[#0F2A3A]
      "
    >
      {/* GRID 2 COLUMNAS */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">

        {/* ================= COL 1: FILTRO ================= */}
        <div>
          <div className="flex items-center gap-3 mb-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#E6F7FD]">
              <GraduationCap className="h-5 w-5 text-[#00B6E8]" />
            </div>

            <div>
              <p className="text-sm font-semibold text-[#0A2540] dark:text-slate-100">
                Carrera
              </p>
              <p className="text-xs text-slate-500 dark:text-slate-400">
                Selecciona una carrera
              </p>
            </div>
          </div>

          <select
            value={filters.career_id ?? ""}
            onChange={(e) =>
              onChangeCareer(
                e.target.value ? Number(e.target.value) : null
              )
            }
            className="
              w-full
              rounded-xl
              border
              px-4
              py-2
              text-sm
              font-semibold
              text-[#0A2540]
              shadow-sm
              focus:outline-none
              focus:ring-2
              focus:ring-[#00B6E8]
              dark:bg-[#102C3C]
              dark:text-slate-200
            "
          >
            <option value="">
              Todas las carreras
            </option>

            {availableCareers.map((career) => (
              <option key={career.id} value={career.id}>
                {career.name}
              </option>
            ))}
          </select>
        </div>

        {/* ================= COL 2: BOTÓN IA ================= */}
        <div className="flex justify-end">
          <button
            onClick={handleAnalyzeAI}
            disabled={!filters.career_id || loading}
            className="
              flex
              items-center
              gap-2
              bg-gradient-to-r
              from-[#00B6E8]
              to-[#1CBCE8]
              hover:opacity-90
              text-white
              font-semibold
              text-sm
              px-6
              py-3
              rounded-xl
              transition
              disabled:opacity-50
            "
          >
            {loading ? (
              <>
                <Loader2 size={16} className="animate-spin" />
                Ejecutando IA...
              </>
            ) : (
              <>
                <Sparkles size={16} />
                Obtener recomendación con IA
              </>
            )}
          </button>
        </div>

      </div>
    </div>
  );
}
