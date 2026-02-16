import { Badge } from "@/components/ui/badge";
import { BookOpen, TrendingUp } from "lucide-react";

interface Props {
  meta: {
    year: number;
    period: "s1" | "s2";
    periodo_label: string;
    vacantes_analizadas?: number;
    reportes_analizados?: number;
    actualizado?: string;
  };
}

export default function CourseAlignmentHeader({ meta }: Props) {
  return (
    <header className="relative border-b bg-[#E6F7FD] dark:bg-[#0A2540] px-6 py-6 rounded-xl shadow-sm">

      {/* =========================
         TÍTULO PRINCIPAL
      ========================== */}
      <div className="flex justify-between items-start">

        <div className="space-y-2">
          <div className="flex items-center gap-3">
            <BookOpen className="w-6 h-6 text-[#1CBCE8]" />
            <h1 className="text-2xl font-bold text-[#0A2540] dark:text-white">
              Conexión Curso–Tendencia (CCTC)
            </h1>
          </div>

          <p className="text-sm text-gray-600 dark:text-gray-300">
            Indicador estratégico de actualización curricular basado en tendencias del Observatorio.
          </p>
        </div>

        {/* =========================
           BADGE PERIODO
        ========================== */}
        <Badge
          variant="outline"
          className="text-xs bg-white dark:bg-[#102E4A] border-[#1CBCE8]"
        >
          {meta?.periodo_label}
        </Badge>
      </div>

      {/* =========================
         META INFO
      ========================== */}
      <div className="mt-4 flex flex-wrap gap-6 text-xs text-gray-600 dark:text-gray-400">

        <div className="flex items-center gap-2">
          <TrendingUp className="w-4 h-4" />
          <span>
            Vacantes analizadas:{" "}
            <strong>{meta?.vacantes_analizadas ?? 0}</strong>
          </span>
        </div>

        <div>
          Tendencias analizadas:{" "}
          <strong>{meta?.reportes_analizados ?? 0}</strong>
        </div>

        <div>
          Actualizado:{" "}
          <strong>{meta?.actualizado ?? "-"}</strong>
        </div>
      </div>
    </header>
  );
}
