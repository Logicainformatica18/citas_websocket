import {
  CheckCircle2,
  AlertTriangle,
  Sparkles,
  Briefcase,
} from "lucide-react";

type Course = {
  id: number;
  name: string;
  estado: string;
  empleo: string;
  tendencias: string;
  gaps: string;
  competencias: number | string;
};

interface Props {
  courses: Course[];
}

export default function CourseAlignmentTable({ courses }: Props) {
  return (
    <div className="rounded-2xl border bg-white shadow-sm overflow-hidden">
      <table className="w-full text-sm">
        {/* ================= HEADER ================= */}
        <thead className="bg-muted/40 text-[11px] uppercase tracking-wide text-muted-foreground">
          <tr className="text-left">
            <th className="px-6 py-4">Curso</th>
            <th className="px-4 py-4">Estado Estratégico</th>
            <th className="px-4 py-4">Demanda Laboral</th>
            <th className="px-4 py-4">Tendencias</th>
            <th className="px-4 py-4">Brechas</th>
            <th className="px-4 py-4">Competencias</th>
          </tr>
        </thead>

        {/* ================= BODY ================= */}
        <tbody>
          {courses.map((course) => (
            <tr
              key={course.id}
              className="border-t hover:bg-muted/20 transition-colors"
            >
              {/* CURSO */}
              <td className="px-6 py-4 font-medium text-[13px] text-foreground">
                {course.name}
              </td>

              {/* ESTADO */}
              <td className="px-4 py-4">
                <EstadoBadge estado={course.estado} />
              </td>

              {/* EMPLEO */}
              <td className="px-4 py-4">
                <EmpleoBadge empleo={course.empleo} />
              </td>

              {/* TENDENCIAS */}
              <td className="px-4 py-4">
                <TendenciaBadge tendencias={course.tendencias} />
              </td>

              {/* GAPS */}
              <td className="px-4 py-4">
                <GapBadge gaps={course.gaps} />
              </td>

              {/* COMPETENCIAS */}
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

/* ============================================================
   BADGES
============================================================ */

function EstadoBadge({ estado }: { estado: string }) {
  if (estado === "Estrategicamente alineado") {
    return (
      <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 text-emerald-700 px-3 py-1 text-[11px] font-medium">
        <CheckCircle2 size={14} />
        Estratégicamente alineado
      </span>
    );
  }

  if (estado === "Parcialmente alineado") {
    return (
      <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-100 text-amber-700 px-3 py-1 text-[11px] font-medium">
        <AlertTriangle size={14} />
        Parcialmente alineado
      </span>
    );
  }

  if (estado === "En riesgo") {
    return (
      <span className="inline-flex items-center gap-1.5 rounded-full bg-red-100 text-red-600 px-3 py-1 text-[11px] font-medium">
        <AlertTriangle size={14} />
        En riesgo
      </span>
    );
  }

  return (
    <span className="inline-flex items-center rounded-full bg-muted px-3 py-1 text-[11px] text-muted-foreground">
      Sin entidades
    </span>
  );
}

function EmpleoBadge({ empleo }: { empleo: string }) {
  if (empleo === "Demanda activa") {
    return (
      <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 text-emerald-700 px-3 py-1 text-[11px] font-medium">
        <Briefcase size={14} />
        Demanda activa
      </span>
    );
  }

  return (
    <span className="inline-flex items-center rounded-full bg-muted px-3 py-1 text-[11px] text-muted-foreground">
      Sin demanda
    </span>
  );
}

function TendenciaBadge({ tendencias }: { tendencias: string }) {
  if (tendencias === "Detectado") {
    return (
      <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 text-emerald-700 px-3 py-1 text-[11px] font-medium">
        <Sparkles size={14} />
        Detectado
      </span>
    );
  }

  return (
    <span className="inline-flex items-center rounded-full bg-muted px-3 py-1 text-[11px] text-muted-foreground">
      No detectado
    </span>
  );
}

function GapBadge({ gaps }: { gaps: string }) {
  if (gaps === "Sin brechas") {
    return (
      <span className="inline-flex items-center rounded-full bg-emerald-100 text-emerald-700 px-3 py-1 text-[11px] font-medium">
        Sin brechas
      </span>
    );
  }

  if (gaps === "0 gaps") {
    return (
      <span className="inline-flex items-center rounded-full bg-muted px-3 py-1 text-[11px] text-muted-foreground">
        0 gaps
      </span>
    );
  }

  return (
    <span className="inline-flex items-center rounded-full bg-red-100 text-red-600 px-3 py-1 text-[11px] font-medium">
      {gaps}
    </span>
  );
}
