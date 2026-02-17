import { router } from "@inertiajs/react";
import { GraduationCap } from "lucide-react";

interface Props {
  careers: any[];
  filters: any;
}

export default function CourseAlignmentFilter({ careers, filters }: Props) {
  const handleChange = (careerId: number) => {
    router.get(
      "/dashboard/indicators/course-alignment",
      {
        career_id: careerId || null,
        year: filters.year,
        period: filters.period,
      },
      { preserveState: true, replace: true }
    );
  };

  return (
    <div className="rounded-2xl border bg-white shadow-sm p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-6">

      {/* Label */}
      <div className="flex items-center gap-3">
        <div className="bg-[#E6F7FD] text-[#1CBCE8] p-2 rounded-xl">
          <GraduationCap size={18} />
        </div>
        <div>
          <p className="text-xs uppercase tracking-wide text-muted-foreground">
            Filtro estratégico
          </p>
          <h3 className="text-sm font-semibold text-[#0A2540]">
            Seleccionar carrera
          </h3>
        </div>
      </div>

      {/* Select */}
      <div className="w-full md:w-[340px] relative">
        <select
          value={filters?.career_id ?? ""}
          onChange={(e) => handleChange(Number(e.target.value))}
          className="
            w-full
            appearance-none
            bg-muted/40
            border
            border-muted
            rounded-xl
            px-4
            py-3
            text-sm
            font-medium
            text-foreground
            outline-none
            transition
            focus:ring-2
            focus:ring-[#1CBCE8]
            focus:border-[#1CBCE8]
            hover:bg-muted/60
            cursor-pointer
          "
        >
          <option value="">Seleccionar carrera</option>
          {careers.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </select>

        {/* Flecha personalizada */}
        <div className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground text-xs">
          ▼
        </div>
      </div>
    </div>
  );
}
