import { useEffect, useState } from "react";
import axios from "axios";
import { X, Trash2, Plus, Search, BookOpen } from "lucide-react";

interface Props {
  open: boolean;
  onClose: () => void;
  careerId: number;
  onSynced: () => void;
}

interface Course {
  id: number;
  name: string;
  code?: string | null; // código del curso en silabos_v2
  semester?: string | null;
  is_mandatory?: boolean;
}

// Estilos ISIL compartidos con las demás tablas y formularios
const theadClass =
  "bg-[#1CBCE8] dark:bg-[#1CBCE8]/20 text-white dark:text-[#1CBCE8] uppercase text-xs tracking-wide sticky top-0 z-10";
const inputClass =
  "px-3 py-2 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-[#1CBCE8] outline-none";

// Código de silabos_v2: etiqueta como las de tecnologías, o "Sin código" en gris claro
function CodeTag({ code }: { code?: string | null }) {
  return code ? (
    <span className="px-2 py-0.5 bg-[#C9F3FF] text-[#0C647A] dark:bg-[#1CBCE8]/20 dark:text-[#1CBCE8] border border-[#1CBCE8]/30 rounded text-xs font-mono">
      {code}
    </span>
  ) : (
    <span className="text-xs text-gray-400 dark:text-gray-500">Sin código</span>
  );
}

export default function CareerCoursesModal({
  open,
  onClose,
  careerId,
  onSynced,
}: Props) {
  const [availableCourses, setAvailableCourses] = useState<Course[]>([]);
  const [linkedCourses, setLinkedCourses] = useState<Course[]>([]);
  const [selectedCourseIds, setSelectedCourseIds] = useState<number[]>([]);
  const [semester, setSemester] = useState<string>("");
  const [loading, setLoading] = useState(false);
  const [searchTerm, setSearchTerm] = useState("");

  useEffect(() => {
    if (open) {
      fetchAvailableCourses();
      fetchLinkedCourses();
    }
  }, [open]);

  const fetchAvailableCourses = async () => {
    const { data } = await axios.get("/api/courses/list");
    setAvailableCourses(data.courses ?? []);
  };

  const fetchLinkedCourses = async () => {
    const { data } = await axios.get(`/careers/${careerId}`);
    setLinkedCourses(data.courses ?? []);
  };

  const toggleCourseSelection = (courseId: number) => {
    setSelectedCourseIds((prev) =>
      prev.includes(courseId)
        ? prev.filter((id) => id !== courseId)
        : [...prev, courseId]
    );
  };

  const handleAttach = async () => {
    if (selectedCourseIds.length === 0) {
      alert("Selecciona al menos un curso");
      return;
    }

    setLoading(true);
    try {
      for (const courseId of selectedCourseIds) {
        await axios.post(`/careers/${careerId}/attach-course`, {
          course_id: courseId,
          semester,
          is_mandatory: true,
        });
      }

      await fetchLinkedCourses();
      setSelectedCourseIds([]);
      setSemester("");
      onSynced();
    } catch {
      alert("Error al asociar los cursos");
    } finally {
      setLoading(false);
    }
  };

  const handleDetach = async (courseId: number) => {
    if (!confirm("¿Quitar este curso de la carrera?")) return;
    await axios.delete(`/careers/${careerId}/detach-course/${courseId}`);
    setLinkedCourses((prev) => prev.filter((c) => c.id !== courseId));
    onSynced();
  };

  if (!open) return null;

  // 🔤 Normalización para búsqueda insensible a tildes
  const normalizeText = (text: string) =>
    text.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();

  // Busca por nombre o por código de silabos_v2
  const term = normalizeText(searchTerm.trim());
  const filteredCourses = availableCourses.filter(
    (c) => normalizeText(c.name).includes(term) || normalizeText(c.code ?? "").includes(term)
  );

  const selectedCount = selectedCourseIds.length;

  return (
    <div className="fixed inset-0 bg-black/50 dark:bg-black/60 flex items-center justify-center z-50 backdrop-blur-sm transition-colors">
      <div className="bg-white dark:bg-gray-900 rounded-xl w-full max-w-5xl text-gray-900 dark:text-gray-100 shadow-2xl border border-gray-200 dark:border-gray-700 border-t-4 border-t-[#1CBCE8] flex flex-col max-h-[85vh] overflow-hidden transition-all">
        {/* Header ISIL */}
        <div className="p-5 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
          <h2 className="text-xl font-semibold flex items-center gap-2">
            <BookOpen className="w-5 h-5 text-[#1CBCE8]" />
            <span className="text-[#0C647A] dark:text-[#1CBCE8]">Sincronizar cursos con carrera</span>
          </h2>
          <button
            onClick={onClose}
            aria-label="Cerrar"
            className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white transition"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Contenido scrolleable */}
        <div className="flex-1 overflow-y-auto p-5 space-y-6">
          {/* Selector de semestre y acción */}
          <div className="flex items-start gap-3 flex-wrap">
            <div>
              <label
                htmlFor="career-courses-semester"
                className="block text-sm mb-1 font-medium text-gray-700 dark:text-gray-300"
              >
                Semestre de los cursos a agregar
              </label>
              <input
                id="career-courses-semester"
                type="text"
                value={semester}
                onChange={(e) => setSemester(e.target.value)}
                placeholder="Ej: 1er ciclo"
                className={`${inputClass} w-56`}
              />
              <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Opcional. Se aplica a todos los seleccionados.
              </p>
            </div>

            <button
              onClick={handleAttach}
              disabled={loading || selectedCount === 0}
              className="mt-6 px-4 py-2 rounded-md flex items-center gap-2 font-medium shadow transition
                bg-[#1CBCE8] hover:bg-[#17A8D0] text-white
                disabled:shadow-none disabled:cursor-not-allowed
                disabled:bg-gray-200 disabled:hover:bg-gray-200 disabled:text-gray-600
                dark:disabled:bg-gray-800 dark:disabled:hover:bg-gray-800 dark:disabled:text-gray-300"
            >
              <Plus className="w-4 h-4" />
              {selectedCount > 0
                ? `Agregar ${selectedCount} seleccionado${selectedCount === 1 ? "" : "s"}`
                : "Agregar seleccionados"}
            </button>
          </div>

          {/* Buscador */}
          <div className="relative">
            <Search className="absolute left-3 top-2.5 w-4 h-4 text-gray-400 dark:text-gray-500" />
            <input
              type="text"
              placeholder="Buscar curso por nombre o código..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className={`${inputClass} pl-9 w-full`}
            />
          </div>

          {/* Cursos disponibles */}
          <div className="border border-gray-200 dark:border-gray-800 rounded-lg overflow-hidden shadow">
            <div className="max-h-[40vh] overflow-y-auto">
              <table className="min-w-full text-sm text-left">
                <thead className={theadClass}>
                  <tr>
                    <th className="px-4 py-2 text-center w-12">✓</th>
                    <th className="px-4 py-2 w-32">Código</th>
                    <th className="px-4 py-2">Curso disponible</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                  {filteredCourses.length > 0 ? (
                    filteredCourses.map((c) => {
                      const isSelected = selectedCourseIds.includes(c.id);
                      return (
                        <tr
                          key={c.id}
                          className={`transition-colors cursor-pointer ${
                            isSelected
                              ? "bg-[#C9F3FF]/60 dark:bg-[#1CBCE8]/20"
                              : "hover:bg-[#E7F9FD] dark:hover:bg-[#1CBCE8]/10"
                          }`}
                          onClick={() => toggleCourseSelection(c.id)}
                        >
                          <td className="px-4 py-2 text-center">
                            <input
                              type="checkbox"
                              checked={isSelected}
                              onChange={() => toggleCourseSelection(c.id)}
                              // Sin esto, el clic en la casilla también llega a la fila y se desmarca solo
                              onClick={(e) => e.stopPropagation()}
                              className="w-4 h-4 cursor-pointer accent-[#1CBCE8]"
                            />
                          </td>
                          <td className="px-4 py-2 whitespace-nowrap">
                            <CodeTag code={c.code} />
                          </td>
                          <td className={`px-4 py-2 ${isSelected ? "font-medium text-[#0C647A] dark:text-[#1CBCE8]" : ""}`}>
                            {c.name}
                          </td>
                        </tr>
                      );
                    })
                  ) : (
                    <tr>
                      <td
                        colSpan={3}
                        className="px-4 py-6 text-center text-gray-500 dark:text-gray-400"
                      >
                        No se encontraron cursos.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>

          {/* Cursos asociados */}
          <div>
            <h3 className="text-lg font-semibold mb-2 text-[#0C647A] dark:text-[#1CBCE8]">
              Cursos asociados
            </h3>
            <div className="overflow-x-auto border border-gray-200 dark:border-gray-800 rounded-lg shadow">
              <table className="min-w-full text-sm text-left">
                <thead className={theadClass}>
                  <tr>
                    <th className="px-4 py-2 w-32">Código</th>
                    <th className="px-4 py-2">Curso</th>
                    <th className="px-4 py-2">Semestre</th>
                    <th className="px-4 py-2 text-center">Obligatorio</th>
                    <th className="px-4 py-2 text-center">Acción</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                  {linkedCourses.length > 0 ? (
                    linkedCourses.map((c) => (
                      <tr
                        key={c.id}
                        className="hover:bg-[#E7F9FD] dark:hover:bg-[#1CBCE8]/10 transition-colors"
                      >
                        <td className="px-4 py-2 whitespace-nowrap">
                          <CodeTag code={c.code} />
                        </td>
                        <td className="px-4 py-2">{c.name}</td>
                        <td className="px-4 py-2">{c.semester ?? "-"}</td>
                        <td className="px-4 py-2 text-center">
                          {c.is_mandatory ? "✅" : "—"}
                        </td>
                        <td className="px-4 py-2 text-center">
                          <button
                            onClick={() => handleDetach(c.id)}
                            aria-label={`Quitar ${c.name}`}
                            className="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300"
                          >
                            <Trash2 className="w-4 h-4 inline" />
                          </button>
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td
                        colSpan={5}
                        className="px-4 py-6 text-center text-gray-500 dark:text-gray-400"
                      >
                        No hay cursos asociados.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="border-t border-gray-200 dark:border-gray-700 p-4 flex justify-end bg-gray-50 dark:bg-gray-900">
          <button
            onClick={onClose}
            className="px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
          >
            Cerrar
          </button>
        </div>
      </div>
    </div>
  );
}
