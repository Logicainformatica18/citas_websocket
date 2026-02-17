import { useState, useMemo } from "react";
import { Search, SlidersHorizontal } from "lucide-react";
import CourseAlignmentTableGrid from "./CourseAlignmentTableGrid";

type Course = {
  id: number;
  name: string;
  estado: string;
  empleo: string;
  tendencias: string;
  gap_label?: string;
  gap_count?: number;
  competencias: number | string;
};

interface Props {
  courses: Course[];
  onSelectCourse?: (course: Course) => void;
}

export default function CourseAlignmentTable({
  courses,
  onSelectCourse,
}: Props) {
  const [search, setSearch] = useState("");
  const [estadoFilter, setEstadoFilter] = useState("all");
  const [empleoFilter, setEmpleoFilter] = useState("all");
  const [tendenciaFilter, setTendenciaFilter] = useState("all");

  const filteredCourses = useMemo(() => {
    return courses
      .filter((c) =>
        c.name.toLowerCase().includes(search.toLowerCase())
      )
      .filter((c) =>
        estadoFilter === "all" ? true : c.estado === estadoFilter
      )
      .filter((c) =>
        empleoFilter === "all" ? true : c.empleo === empleoFilter
      )
      .filter((c) =>
        tendenciaFilter === "all" ? true : c.tendencias === tendenciaFilter
      );
  }, [courses, search, estadoFilter, empleoFilter, tendenciaFilter]);

  return (
    <div className="rounded-2xl border bg-white shadow-sm overflow-hidden">

      {/* 🔎 BARRA SUPERIOR */}
      <div className="p-4 border-b bg-muted/30 flex flex-col gap-4">

        {/* BUSCADOR */}
        <div className="flex items-center gap-3">
          <Search size={16} className="text-muted-foreground" />
          <input
            type="text"
            placeholder="Filtrar curso..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full bg-transparent outline-none text-sm"
          />
        </div>

        {/* FILTROS */}
        <div className="flex gap-3 flex-wrap">
          <SelectFilter
            label="Estado"
            value={estadoFilter}
            onChange={setEstadoFilter}
            options={[
              "Estrategicamente alineado",
              "Altamente alineado",
              "Alineado",
              "No alineado",
            ]}
          />

          <SelectFilter
            label="Demanda"
            value={empleoFilter}
            onChange={setEmpleoFilter}
            options={["Demanda activa", "Sin demanda"]}
          />

          <SelectFilter
            label="Tendencias"
            value={tendenciaFilter}
            onChange={setTendenciaFilter}
            options={["Detectado", "No detectado"]}
          />
        </div>
      </div>

      {/* TABLA */}
      <CourseAlignmentTableGrid
        courses={filteredCourses}
        onSelectCourse={onSelectCourse}
      />
    </div>
  );
}

/* =========================
   SELECT FILTRO
========================= */
function SelectFilter({
  label,
  value,
  onChange,
  options,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  options: string[];
}) {
  return (
    <select
      value={value}
      onChange={(e) => onChange(e.target.value)}
      className="text-xs border rounded-lg px-3 py-1 bg-white"
    >
      <option value="all">{label}: Todos</option>
      {options.map((opt) => (
        <option key={opt} value={opt}>
          {opt}
        </option>
      ))}
    </select>
  );
}
