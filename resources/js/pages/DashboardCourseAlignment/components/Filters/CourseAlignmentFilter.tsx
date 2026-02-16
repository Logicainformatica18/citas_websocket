import { router } from "@inertiajs/react";

interface Props {
  careers: any[];
  filters: any;
}

export default function CourseAlignmentFilter({ careers, filters }: Props) {
  const handleChange = (careerId: number) => {
    router.get(
      "/dashboard/indicators/course-alignment",
      {
        career_id: careerId,
        year: filters.year,
        period: filters.period,
      },
      { preserveState: true, replace: true }
    );
  };

  return (
    <div className="flex gap-4 items-center">
      <select
        className="border rounded px-3 py-2"
        value={filters?.career_id ?? ""}
        onChange={(e) => handleChange(Number(e.target.value))}
      >
        <option value="">Seleccionar carrera</option>
        {careers.map((c) => (
          <option key={c.id} value={c.id}>
            {c.name}
          </option>
        ))}
      </select>
    </div>
  );
}
