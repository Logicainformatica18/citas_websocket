import {
  CheckCircle2,
  AlertTriangle,
  Sparkles,
  Briefcase,
  Search,
} from "lucide-react";
import { useState, useMemo } from "react";

type Course = {
  id: number;
  name: string;
  estado: string;
  empleo: string;
  tendencias: string;

  // 🔥 nuevo modelo backend
  gap_label?: string;
  gap_count?: number;

  // compatibilidad antigua
  gaps?: string;

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

  const formatTitle = (text: string) => {
    if (!text) return "";
    return text
      .toLowerCase()
      .replace(/\b\w/g, (char) => char.toUpperCase());
  };

  const filteredCourses = useMemo(() => {
    return courses.filter((c) =>
      c.name.toLowerCase().includes(search.toLowerCase())
    );
  }, [courses, search]);

  return (
    <div className="rounded-2xl border bg-white shadow-sm overflow-hidden">

      {/* FILTRO */}
      <div className="p-4 border-b bg-muted/30 flex items-center gap-3">
        <Search size={16} className="text-muted-foreground" />
        <input
          type="text"
          placeholder="Filtrar curso..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-full bg-transparent outline-none text-sm"
        />
      </div>

      <table className="w-full text-sm">
        <thead className="bg-muted/40 text-[11px] uppercase tracking-wide text-muted-foreground">
          <tr className="text-left">
            <th className="px-6 py-4">Curso</th>
            <th className="px-4 py-4">Estado Estratégico</th>
            <th className="px-4 py-4">Demanda Laboral</th>
            <th className="px-4 py-4">Tendencias</th>
            <th className="px-4 py-4">Gap</th>
            <th className="px-4 py-4">Competencias</th>
          </tr>
        </thead>

        <tbody>
          {filteredCourses.map((course) => (
            <tr
              key={course.id}
              onClick={() => onSelectCourse?.(course)}
              className="border-t hover:bg-muted/20 transition-colors cursor-pointer"
            >
              <td className="px-6 py-4 font-medium text-[13px] text-foreground">
                {formatTitle(course.name)}
              </td>

              <td className="px-4 py-4">
                <EstadoBadge estado={course.estado} />
              </td>

              <td className="px-4 py-4">
                <EmpleoBadge empleo={course.empleo} />
              </td>

              <td className="px-4 py-4">
                <TendenciaBadge tendencias={course.tendencias} />
              </td>

              <td className="px-4 py-4">
                <GapBadge
                  label={course.gap_label ?? course.gaps ?? "Sin evaluar"}
                  count={course.gap_count ?? 0}
                />
              </td>

              <td className="px-4 py-4 text-[12px] font-medium">
                {course.competencias}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

/* =========================
   ESTADO
========================= */
function EstadoBadge({ estado }: { estado: string }) {
  const base =
    "inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-medium";

  switch (estado) {
    case "Estrategicamente alineado":
      return (
        <span className={`${base} bg-emerald-100 text-emerald-700`}>
          <CheckCircle2 size={14} />
          Estratégicamente alineado
        </span>
      );

    case "Altamente alineado":
      return (
        <span className={`${base} bg-teal-100 text-teal-700`}>
          <CheckCircle2 size={14} />
          Altamente alineado
        </span>
      );

    case "Alineado":
      return (
        <span className={`${base} bg-blue-100 text-blue-700`}>
          <CheckCircle2 size={14} />
          Alineado
        </span>
      );

    case "No alineado":
      return (
        <span className={`${base} bg-red-100 text-red-600`}>
          <AlertTriangle size={14} />
          No alineado
        </span>
      );

    default:
      return (
        <span className="inline-flex items-center rounded-full bg-muted px-3 py-1 text-[11px] text-muted-foreground">
          Curso base académico
        </span>
      );
  }
}

/* =========================
   EMPLEO
========================= */
function EmpleoBadge({ empleo }: { empleo: string }) {
  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-medium
      ${
        empleo === "Demanda activa"
          ? "bg-emerald-100 text-emerald-700"
          : "bg-muted text-muted-foreground"
      }`}
    >
      <Briefcase size={14} />
      {empleo}
    </span>
  );
}

/* =========================
   TENDENCIA
========================= */
function TendenciaBadge({ tendencias }: { tendencias: string }) {
  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-medium
      ${
        tendencias === "Detectado"
          ? "bg-emerald-100 text-emerald-700"
          : "bg-muted text-muted-foreground"
      }`}
    >
      <Sparkles size={14} />
      {tendencias}
    </span>
  );
}

/* =========================
   GAP (nuevo modelo)
========================= */
function GapBadge({
  label,
  count,
}: {
  label: string;
  count: number;
}) {
  const base =
    "inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-medium";

  switch (label) {
    case "Sin gap":
      return (
        <span className={`${base} bg-emerald-100 text-emerald-700`}>
          <CheckCircle2 size={14} />
          Sin gap
        </span>
      );

    case "Gap leve":
      return (
        <span className={`${base} bg-yellow-100 text-yellow-700`}>
          <AlertTriangle size={14} />
          Gap leve ({count})
        </span>
      );

    case "Gap moderado":
      return (
        <span className={`${base} bg-amber-100 text-amber-700`}>
          <AlertTriangle size={14} />
          Gap moderado ({count})
        </span>
      );

    case "Gap crítico":
      return (
        <span className={`${base} bg-red-100 text-red-600`}>
          <AlertTriangle size={14} />
          Gap crítico ({count})
        </span>
      );

    default:
      return (
        <span className="inline-flex items-center rounded-full bg-muted px-3 py-1 text-[11px] text-muted-foreground">
          Sin evaluar
        </span>
      );
  }
}
