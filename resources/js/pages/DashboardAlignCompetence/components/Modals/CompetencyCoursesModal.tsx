import { Dialog } from "@headlessui/react";
import {
  X,
  CheckCircle2,
  AlertTriangle,
  ShieldCheck,
  BookOpen,
  Sparkles,
  TrendingUp,
} from "lucide-react";

interface Course {
  id: number;
  name: string;
  cycle?: string;
  status:
    | "Estrategicamente alineado"
    | "Altamente alineado"
    | "Alineado"
    | "No alineado";
}

interface Props {
  open: boolean;
  onClose: () => void;
  competencyName: string;
  courses: Course[];
  marketAligned?: boolean;
  trendAligned?: boolean;
  matchLevel?: "Alta" | "Media" | "Baja";
}

export default function CompetencyCoursesModal({
  open,
  onClose,
  competencyName,
  courses,
  marketAligned = true,
  trendAligned = true,
  matchLevel = "Alta",
}: Props) {
  /* ===============================
     Badge de coincidencia superior
  =============================== */

  const renderMatchBadge = () => {
    const base =
      "inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium";

    switch (matchLevel) {
      case "Alta":
        return (
          <span className={`${base} bg-emerald-100 text-emerald-700`}>
            <Sparkles size={14} />
            Alta coincidencia
          </span>
        );

      case "Media":
        return (
          <span className={`${base} bg-yellow-100 text-yellow-700`}>
            <Sparkles size={14} />
            Coincidencia media
          </span>
        );

      default:
        return (
          <span className={`${base} bg-red-100 text-red-700`}>
            <Sparkles size={14} />
            Baja coincidencia
          </span>
        );
    }
  };

  /* ===============================
     Badge de estado por curso
  =============================== */

  const renderStatus = (status: Course["status"]) => {
    const base =
      "inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium";

    switch (status) {
      case "Estrategicamente alineado":
        return (
          <span className={`${base} bg-green-100 text-green-700`}>
            <ShieldCheck size={14} />
            Estratégico
          </span>
        );

      case "Altamente alineado":
        return (
          <span className={`${base} bg-emerald-100 text-emerald-700`}>
            <CheckCircle2 size={14} />
            Alta alineación
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
      default:
        return (
          <span className={`${base} bg-red-100 text-red-700`}>
            <AlertTriangle size={14} />
            No alineado
          </span>
        );
    }
  };

  return (
    <Dialog open={open} onClose={onClose} className="relative z-50">
      {/* Overlay */}
      <div className="fixed inset-0 bg-black/40 backdrop-blur-sm" />

      {/* Contenedor */}
      <div className="fixed inset-0 flex items-center justify-center p-6">
        <Dialog.Panel className="w-full max-w-2xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl p-6 space-y-6">

          {/* HEADER */}
          <div className="flex justify-between items-start">
            <div className="space-y-3">
              <div className="flex items-center gap-3">
                <div className="bg-indigo-100 dark:bg-indigo-900 p-2 rounded-lg">
                  <BookOpen
                    className="text-indigo-600 dark:text-indigo-300"
                    size={18}
                  />
                </div>

                <h2 className="text-lg font-semibold text-slate-800 dark:text-slate-100">
                  {competencyName}
                </h2>
              </div>

              {/* BADGES SUPERIORES */}
              <div className="flex flex-wrap items-center gap-4">
                {renderMatchBadge()}

                <div className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                  <TrendingUp size={14} />
                  Mercado:
                  {marketAligned ? (
                    <CheckCircle2 className="text-green-600" size={16} />
                  ) : (
                    <AlertTriangle className="text-red-500" size={16} />
                  )}
                </div>

                <div className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                  <Sparkles size={14} />
                  Tendencias:
                  {trendAligned ? (
                    <CheckCircle2 className="text-green-600" size={16} />
                  ) : (
                    <AlertTriangle className="text-red-500" size={16} />
                  )}
                </div>
              </div>
            </div>

            <button
              onClick={onClose}
              className="hover:bg-gray-100 dark:hover:bg-slate-800 p-2 rounded-lg transition"
            >
              <X size={18} />
            </button>
          </div>

          {/* SUBTITULO */}
          <div className="text-sm font-semibold text-slate-700 dark:text-slate-200">
            Cursos asociados
          </div>

          {/* LISTA */}
          <div className="space-y-3 max-h-[350px] overflow-y-auto pr-1">
            {courses.length === 0 && (
              <div className="text-sm text-gray-500 text-center py-6">
                No hay cursos asociados.
              </div>
            )}

            {courses.map((course) => (
              <div
                key={course.id}
                className="
                  flex justify-between items-center
                  bg-gray-50 dark:bg-slate-800
                  border border-gray-200 dark:border-slate-700
                  rounded-xl
                  p-4
                  transition
                  hover:bg-gray-100 dark:hover:bg-slate-700
                "
              >
                <div>
                  <div className="font-medium text-slate-800 dark:text-slate-100">
                    {course.name}
                  </div>

                  {course.cycle && (
                    <div className="text-xs text-slate-500">
                      {course.cycle}
                    </div>
                  )}
                </div>

                {renderStatus(course.status)}
              </div>
            ))}
          </div>
        </Dialog.Panel>
      </div>
    </Dialog>
  );
}
