import { Badge } from "@/components/ui/badge";
import { CheckCircle2, AlertTriangle, XCircle } from "lucide-react";

interface Course {
  name: string;
  weight?: number;
}

interface Competency {
  id: number;
  name: string;
  status: "aligned" | "partial" | "gap";
  courses: Course[];
  interpretation?: string;
}

interface Props {
  competencies: Competency[];
}

export default function CompetenciesAlignmentTable({
  competencies,
}: Props) {
  const getStatusConfig = (status: Competency["status"]) => {
    switch (status) {
      case "aligned":
        return {
          label: "Estratégicamente alineado",
          color:
            "bg-emerald-100 text-emerald-700 border-emerald-200",
          icon: <CheckCircle2 size={16} className="mr-1" />,
        };

      case "partial":
        return {
          label: "Parcialmente alineado",
          color:
            "bg-amber-100 text-amber-700 border-amber-200",
          icon: <AlertTriangle size={16} className="mr-1" />,
        };

      case "gap":
      default:
        return {
          label: "Brecha detectada",
          color:
            "bg-red-100 text-red-700 border-red-200",
          icon: <XCircle size={16} className="mr-1" />,
        };
    }
  };

  return (
    <div className="rounded-2xl border bg-white shadow-sm overflow-hidden">
      <table className="w-full text-sm">
        <thead className="bg-[#F4F8FB] border-b">
          <tr className="text-left text-xs uppercase tracking-wide text-muted-foreground">
            <th className="px-6 py-4 w-1/3">Competencia</th>
            <th className="px-6 py-4 w-1/6">Estado</th>
            <th className="px-6 py-4 w-1/3">Cursos asociados</th>
            <th className="px-6 py-4 w-1/4">Interpretación</th>
          </tr>
        </thead>

        <tbody>
          {competencies.map((comp) => {
            const status = getStatusConfig(comp.status);

            return (
              <tr
                key={comp.id}
                className="border-b hover:bg-muted/30 transition"
              >
                {/* Competencia */}
                <td className="px-6 py-5 font-medium text-foreground">
                  <div className="flex items-center gap-2">
                    <span
                      className={`h-2.5 w-2.5 rounded-full ${
                        comp.status === "aligned"
                          ? "bg-emerald-500"
                          : comp.status === "partial"
                          ? "bg-amber-500"
                          : "bg-red-500"
                      }`}
                    />
                    {comp.name}
                  </div>
                </td>

                {/* Estado */}
                <td className="px-6 py-5">
                  <Badge
                    variant="outline"
                    className={`flex items-center w-fit px-3 py-1 rounded-full text-xs font-medium border ${status.color}`}
                  >
                    {status.icon}
                    {status.label}
                  </Badge>
                </td>

                {/* Cursos asociados */}
                <td className="px-6 py-5 space-y-2">
                  {comp.courses?.length > 0 ? (
                    comp.courses.map((course, index) => (
                      <div
                        key={index}
                        className="flex items-center gap-2 text-sm"
                      >
                        <span className="h-2 w-2 rounded-full bg-[#1CBCE8]" />
                        {course.name}
                      </div>
                    ))
                  ) : (
                    <span className="text-muted-foreground text-xs">
                      Sin cursos asociados
                    </span>
                  )}
                </td>

                {/* Interpretación */}
                <td className="px-6 py-5 text-muted-foreground text-sm leading-relaxed">
                  {comp.interpretation ??
                    (comp.status === "aligned"
                      ? "Competencia sólidamente fortalecida por los cursos asociados."
                      : comp.status === "partial"
                      ? "Requiere refuerzo en algunos cursos para alcanzar alineación plena."
                      : "Existe una brecha significativa que requiere intervención curricular.")}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
