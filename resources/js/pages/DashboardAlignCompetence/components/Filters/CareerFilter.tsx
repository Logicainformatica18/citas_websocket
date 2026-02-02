import { GraduationCap } from "lucide-react";
import { router, usePage } from "@inertiajs/react";

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

  return (
    <div
      className="
        rounded-2xl
        border
        bg-white
        p-4
        shadow-sm
        dark:bg-[#0F2A3A]
      "
    >
      <div className="flex items-center gap-3 mb-3">
        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#E6F7FD]">
          <GraduationCap className="h-5 w-5 text-[#00B6E8]" />
        </div>

        <div>
          <p className="text-sm font-semibold text-[#0A2540] dark:text-slate-100">
            Carrera
          </p>
          <p className="text-xs text-slate-500 dark:text-slate-400">
            Selecciona una carrera para ver el detalle por competencia
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
  );
}
