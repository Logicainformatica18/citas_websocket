import {
  Sparkles,
  CheckCircle2,
  AlertTriangle,
  XCircle,
} from "lucide-react";

interface Props {
  courses: any[];
}

export default function CourseBoard({ courses }: Props) {
  return (
    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
      {courses.map((course) => (
        <CourseCard key={course.id} course={course} />
      ))}
    </div>
  );
}

/* =========================================
   CARD INDIVIDUAL
========================================= */
function CourseCard({ course }: any) {
  const levelConfig = getLevelConfig(course.level);

  return (
    <div
      className={`relative border rounded-xl p-5 bg-white dark:bg-[#0A2540] shadow-sm hover:shadow-md transition`}
    >
      {/* Barra lateral de estado */}
      <div
        className="absolute left-0 top-0 bottom-0 w-1 rounded-l-xl"
        style={{ backgroundColor: levelConfig.color }}
      />

      {/* Título */}
      <h3 className="text-base font-semibold text-[#0A2540] dark:text-white mb-3">
        {course.name}
      </h3>

      {/* Conexiones */}
      <div className="flex items-center justify-between mb-3">
        <span className="text-sm text-gray-500 dark:text-gray-400">
          Conexiones detectadas
        </span>

        <span className="text-xl font-bold text-[#1CBCE8]">
          {course.cctc}
        </span>
      </div>

      {/* Estado */}
      <div
        className="inline-flex items-center gap-2 px-3 py-1 text-xs font-medium rounded-full"
        style={{
          backgroundColor: levelConfig.bg,
          color: levelConfig.text,
        }}
      >
        {levelConfig.icon}
        {levelConfig.label}
      </div>
    </div>
  );
}

/* =========================================
   CONFIGURACIÓN DE NIVELES
========================================= */
function getLevelConfig(level: string) {
  switch (level) {
    case "strategically_aligned":
      return {
        label: "Estratégicamente alineado",
        color: "#0A2540",
        bg: "#0A254015",
        text: "#0A2540",
        icon: <Sparkles size={14} />,
      };

    case "highly_aligned":
      return {
        label: "Altamente alineado",
        color: "#1CBCE8",
        bg: "#1CBCE815",
        text: "#0A2540",
        icon: <CheckCircle2 size={14} />,
      };

    case "aligned":
      return {
        label: "Alineado",
        color: "#F59E0B",
        bg: "#F59E0B20",
        text: "#7C2D12",
        icon: <AlertTriangle size={14} />,
      };

    default:
      return {
        label: "No alineado",
        color: "#EF4444",
        bg: "#EF444420",
        text: "#7F1D1D",
        icon: <XCircle size={14} />,
      };
  }
}
