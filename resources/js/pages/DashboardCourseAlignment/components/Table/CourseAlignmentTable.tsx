import {
  CheckCircle2,
  AlertTriangle,
  Sparkles,
  Briefcase,
  Brain,
} from "lucide-react";
import { useState } from "react";
import axios from "axios";

export default function CourseAlignmentTableGrid({
  courses,
  onSelectCourse,
}: any) {

  const [loadingId, setLoadingId] = useState<number | null>(null);

  const analyzeWithAI = async (courseId: number) => {
    try {
      setLoadingId(courseId);

      await axios.post(`/dashboard/courses/${courseId}/analyze-ai`);

      alert("Análisis IA ejecutado correctamente");
    } catch (e) {
      alert("Error ejecutando IA");
    } finally {
      setLoadingId(null);
    }
  };

  return (
    <table className="w-full text-sm">
      <thead className="bg-muted/40 text-[11px] uppercase tracking-wide text-muted-foreground">
        <tr className="text-left">
          <th className="px-6 py-4">Curso</th>
          <th className="px-4 py-4">Estado</th>
          <th className="px-4 py-4">Demanda</th>
          <th className="px-4 py-4">Tendencias</th>
          {/* <th className="px-4 py-4">Gap</th> */}
          <th className="px-4 py-4">Comp.</th>
          <th className="px-4 py-4 text-center">IA</th>
        </tr>
      </thead>

      <tbody>
        {courses.map((course: any) => (
         <tr
  key={course.id}
  onClick={() => onSelectCourse?.(course)}
  className="border-t hover:bg-muted/20 transition-colors cursor-pointer"
><td className="px-6 py-4 font-medium text-[13px] uppercase">
  {course.name}
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

            {/* <td className="px-4 py-4">
              {course.gap_label}
            </td> */}

            <td className="px-4 py-4">
              {course.competencias}
            </td>

            <td className="px-4 py-4 text-center">
             <button
  onClick={(e) => {
    e.stopPropagation(); // 🔥 evita activar el click del row
    analyzeWithAI(course.id);
  }}
  disabled={loadingId === course.id}
  className="inline-flex items-center gap-1.5 bg-indigo-600 text-white text-xs px-3 py-1.5 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50"
>
  <Brain size={14} />
  {loadingId === course.id ? "Analizando..." : "IA"}
</button>

            </td>
          </tr>
        ))}
      </tbody>
    </table>
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
