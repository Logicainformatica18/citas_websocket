import { Badge } from "@/components/ui/badge";
import { CheckCircle2, AlertTriangle, XCircle } from "lucide-react";

interface Competency {
  id: number;
  name: string;
  estado: string;
  cursos: {
    name: string;
    estado: string;
  }[];
  interpretation?: string;
}


interface Props {
  competencies: Competency[];
}

/* 🔥 Formato elegante */
const formatTitle = (text: string) => {
  if (!text) return "";
  const lower = text.toLowerCase();
  return lower.charAt(0).toUpperCase() + lower.slice(1);
};

export default function CompetenciesAlignmentTable({
  competencies,
}: Props) {
  /* ============================================================
     🔥 SEMÁFORO REAL
  ============================================================ */

  const getStatusConfig = (estado: string) => {
    const normalized = estado?.toLowerCase() ?? "";

    // 🟢 Verde fuerte
    if (
      normalized.includes("estrategicamente") ||
      normalized.includes("altamente")
    ) {
      return {
        label: estado,
        dot: "bg-emerald-500",
        color:
          "bg-emerald-100 text-emerald-700 border-emerald-200",
        icon: <CheckCircle2 size={16} className="mr-1" />,
      };
    }

    // 🟡 Amarillo
    if (
      normalized.includes("parcialmente") ||
      normalized.includes("alineado")
    ) {
      return {
        label: estado,
        dot: "bg-amber-500",
        color:
          "bg-amber-100 text-amber-700 border-amber-200",
        icon: <AlertTriangle size={16} className="mr-1" />,
      };
    }

    // 🔴 Rojo
    return {
      label: estado || "En riesgo",
      dot: "bg-red-500",
      color: "bg-red-100 text-red-700 border-red-200",
      icon: <XCircle size={16} className="mr-1" />,
    };
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
            const status = getStatusConfig(comp.estado);

            return (
              <tr
                key={comp.id}
                className="border-b hover:bg-muted/30 transition"
              >
                {/* COMPETENCIA */}
                <td className="px-6 py-5 font-medium text-foreground">
                  <div className="flex items-center gap-2">
                    <span
                      className={`h-2.5 w-2.5 rounded-full ${status.dot}`}
                    />
                    {formatTitle(comp.name)}
                  </div>
                </td>

                {/* ESTADO */}
                <td className="px-6 py-5">
                  <Badge
                    variant="outline"
                    className={`flex items-center w-fit px-3 py-1 rounded-full text-xs font-medium border ${status.color}`}
                  >
                    {status.icon}
                    {status.label}
                  </Badge>
                </td>

                {/* CURSOS */}
               {/* ================= Cursos asociados ================= */}
<td className="px-6 py-5">
  {comp.cursos?.length > 0 ? (
    <div className="flex flex-wrap gap-2">
      {comp.cursos.map((course, index) => {
        const normalized = course.estado?.toLowerCase();

        let color =
          "bg-gray-100 text-gray-600 border-gray-200";
        let dot = "bg-gray-400";

        if (normalized?.includes("estrategicamente") || normalized?.includes("altamente")) {
          color = "bg-emerald-100 text-emerald-700 border-emerald-200";
          dot = "bg-emerald-500";
        } else if (normalized?.includes("parcialmente") || normalized?.includes("alineado")) {
          color = "bg-amber-100 text-amber-700 border-amber-200";
          dot = "bg-amber-500";
        } else {
          color = "bg-red-100 text-red-700 border-red-200";
          dot = "bg-red-500";
        }

        return (
          <span
            key={index}
            className={`inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium border ${color}`}
          >
            <span className={`h-1.5 w-1.5 rounded-full ${dot}`} />
            {formatTitle(course.name)}
          </span>
        );
      })}
    </div>
  ) : (
    <span className="text-muted-foreground text-xs">
      Sin cursos asociados
    </span>
  )}
</td>


                {/* INTERPRETACIÓN */}
                <td className="px-6 py-5 text-muted-foreground text-sm leading-relaxed">
                  {comp.estado?.toLowerCase().includes("estrategicamente")
                    ? "Competencia sólidamente fortalecida por cursos con alta alineación estratégica."
                    : comp.estado?.toLowerCase().includes("parcialmente")
                    ? "Existe cobertura parcial; algunos cursos requieren refuerzo."
                    : "Existe una brecha relevante que requiere intervención curricular."}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
