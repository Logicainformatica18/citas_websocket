import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";

type Course = {
  id: number;
  name: string;
  estado: string;
  empleo: string;
  tendencias: string;
  gaps: string;
  competencias: string | number;
};

interface Props {
  courses: Course[];
}

function getEstadoColor(estado: string) {
  switch (estado) {
    case "Estrategicamente alineado":
      return "bg-emerald-100 text-emerald-700 border-emerald-200";
    case "Parcialmente alineado":
      return "bg-amber-100 text-amber-700 border-amber-200";
    case "En riesgo":
      return "bg-red-100 text-red-700 border-red-200";
    default:
      return "bg-muted text-muted-foreground";
  }
}

function getSimpleColor(value: string) {
  if (value === "Demanda activa" || value === "Detectado") {
    return "bg-emerald-100 text-emerald-700 border-emerald-200";
  }
  if (value.includes("gap")) {
    return "bg-red-100 text-red-700 border-red-200";
  }
  return "bg-muted text-muted-foreground";
}

export default function CourseBoard({ courses }: Props) {
  return (
    <Card className="shadow-sm border rounded-2xl">
      <CardContent className="p-0">

        <div className="overflow-x-auto">
          <table className="w-full text-sm">

            {/* ================= HEADER ================= */}
            <thead className="bg-muted/40 border-b">
              <tr className="text-left">
                <th className="px-6 py-4 font-medium">Curso</th>
                <th className="px-6 py-4 font-medium">Estado Estratégico</th>
                <th className="px-6 py-4 font-medium">Demanda Laboral</th>
                <th className="px-6 py-4 font-medium">Tendencias</th>
                <th className="px-6 py-4 font-medium">Brechas</th>
                <th className="px-6 py-4 font-medium">Competencias</th>
              </tr>
            </thead>

            {/* ================= BODY ================= */}
            <tbody>
              {courses.map((course) => (
                <tr
                  key={course.id}
                  className="border-b hover:bg-muted/30 transition"
                >
                  <td className="px-6 py-4 font-medium">
                    {course.name}
                  </td>

                  <td className="px-6 py-4">
                    <Badge
                      variant="outline"
                      className={getEstadoColor(course.estado)}
                    >
                      {course.estado}
                    </Badge>
                  </td>

                  <td className="px-6 py-4">
                    <Badge
                      variant="outline"
                      className={getSimpleColor(course.empleo)}
                    >
                      {course.empleo}
                    </Badge>
                  </td>

                  <td className="px-6 py-4">
                    <Badge
                      variant="outline"
                      className={getSimpleColor(course.tendencias)}
                    >
                      {course.tendencias}
                    </Badge>
                  </td>

                  <td className="px-6 py-4">
                    <Badge
                      variant="outline"
                      className={getSimpleColor(course.gaps)}
                    >
                      {course.gaps}
                    </Badge>
                  </td>

                  <td className="px-6 py-4 font-medium">
                    {course.competencias}
                  </td>
                </tr>
              ))}
            </tbody>

          </table>
        </div>

      </CardContent>
    </Card>
  );
}
