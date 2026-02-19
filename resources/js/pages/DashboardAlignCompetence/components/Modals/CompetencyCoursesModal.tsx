import { Dialog } from "@headlessui/react";
import {
  X,
  ShieldCheck,
  TrendingUp,
  AlertTriangle,
  BarChart3,
} from "lucide-react";

interface Course {
  id: number;
  name: string;
  cycle?: string;
  final_score: number;
  level: "Fuerte" | "Media" | "Débil" | "Baja" | "Crítica";
}

interface Props {
  open: boolean;
  onClose: () => void;
  competencyName: string;
  courses: Course[];
}

export default function CompetencyCoursesModal({
  open,
  onClose,
  competencyName,
  courses,
}: Props) {

  /* ===============================
     BADGE NIVEL ESTRATÉGICO
  =============================== */

  const renderLevel = (level: Course["level"]) => {
    const base =
      "inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium";

    switch (level) {
      case "Fuerte":
        return (
          <span className={`${base} bg-green-100 text-green-700`}>
            <ShieldCheck size={14} />
            Fuerte
          </span>
        );

      case "Media":
        return (
          <span className={`${base} bg-yellow-100 text-yellow-700`}>
            <TrendingUp size={14} />
            Media
          </span>
        );

      case "Débil":
        return (
          <span className={`${base} bg-orange-100 text-orange-700`}>
            <AlertTriangle size={14} />
            Débil
          </span>
        );

      case "Baja":
        return (
          <span className={`${base} bg-gray-100 text-gray-700`}>
            <AlertTriangle size={14} />
            Baja
          </span>
        );

      case "Crítica":
      default:
        return (
          <span className={`${base} bg-red-100 text-red-700`}>
            <AlertTriangle size={14} />
            Crítica
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
            <div className="space-y-2">
              <div className="flex items-center gap-3">
                <div className="bg-indigo-100 dark:bg-indigo-900 p-2 rounded-lg">
                  <BarChart3
                    className="text-indigo-600 dark:text-indigo-300"
                    size={18}
                  />
                </div>

                <h2 className="text-lg font-semibold text-slate-800 dark:text-slate-100">
                  {competencyName}
                </h2>
              </div>

              <div className="text-sm text-slate-600 dark:text-slate-400">
                Evaluación estratégica de cursos asociados
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

                <div className="flex items-center gap-4">
                  {/* Puntaje */}
                  <div className="text-sm font-semibold text-slate-700 dark:text-slate-200">
                    {course.final_score}%
                  </div>

                  {/* Nivel */}
                  {renderLevel(course.level)}
                </div>
              </div>
            ))}
          </div>
        </Dialog.Panel>
      </div>
    </Dialog>
  );
}
