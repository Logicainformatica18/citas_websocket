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
  semester?: string | null;
  is_mandatory?: boolean;
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
    text.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();

  const filteredCourses = availableCourses.filter((c) =>
    normalizeText(c.name).includes(normalizeText(searchTerm))
  );

  return (
    <div className="fixed inset-0 bg-black/50 dark:bg-black/60 flex items-center justify-center z-50 backdrop-blur-sm transition-colors">
      <div className="bg-white dark:bg-gray-900 rounded-xl w-full max-w-5xl text-gray-900 dark:text-gray-100 shadow-2xl border border-gray-200 dark:border-gray-700 flex flex-col max-h-[85vh] transition-all">
        {/* Header */}
        <div className="p-5 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
          <h2 className="text-xl font-semibold flex items-center gap-2">
            <BookOpen className="w-5 h-5 text-blue-600 dark:text-blue-400" />
            Sincronizar cursos con carrera
          </h2>
          <button
            onClick={onClose}
            className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white transition"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Contenido scrolleable */}
        <div className="flex-1 overflow-y-auto p-5 space-y-6">
          {/* Selector de semestre y acción */}
          <div className="flex items-end gap-3 flex-wrap">
            <div>
              <label className="block text-sm mb-1 font-medium text-gray-700 dark:text-gray-300">
                Semestre
              </label>
              <input
                type="text"
                value={semester}
                onChange={(e) => setSemester(e.target.value)}
                placeholder="Ej: 1er ciclo"
                className="bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-800 dark:text-gray-100 rounded-md px-3 py-2 w-44 focus:ring-2 focus:ring-blue-500 focus:outline-none"
              />
            </div>

            <button
              onClick={handleAttach}
              disabled={loading || selectedCourseIds.length === 0}
              className={`px-4 py-2 rounded-md flex items-center gap-2 transition-all ${
                selectedCourseIds.length > 0
                  ? "bg-blue-600 hover:bg-blue-700 text-white"
                  : "bg-gray-400 text-gray-200 cursor-not-allowed"
              }`}
            >
              <Plus className="w-4 h-4" /> Agregar seleccionados
            </button>
          </div>

          {/* Buscador */}
          <div className="relative mt-4">
            <Search className="absolute left-3 top-2.5 w-4 h-4 text-gray-400 dark:text-gray-500" />
            <input
              type="text"
              placeholder="Buscar curso..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-800 dark:text-gray-100 rounded-md pl-9 pr-3 py-2 w-full focus:ring-2 focus:ring-blue-500 focus:outline-none"
            />
          </div>

          {/* Cursos disponibles */}
          <div className="border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden">
            <div className="max-h-[40vh] overflow-y-auto">
              <table className="min-w-full text-sm text-left">
                <thead className="bg-gray-200 dark:bg-gray-800 uppercase text-gray-700 dark:text-gray-300 sticky top-0">
                  <tr>
                    <th className="px-4 py-2 text-center">✓</th>
                    <th className="px-4 py-2">Curso disponible</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                  {filteredCourses.length > 0 ? (
                    filteredCourses.map((c) => {
                      const isSelected = selectedCourseIds.includes(c.id);
                      return (
                        <tr
                          key={c.id}
                          className={`transition-colors cursor-pointer ${
                            isSelected
                              ? "bg-blue-100 dark:bg-blue-900/40"
                              : "hover:bg-gray-100 dark:hover:bg-gray-800/50"
                          }`}
                          onClick={() => toggleCourseSelection(c.id)}
                        >
                          <td className="px-4 py-2 text-center">
                            <input
                              type="checkbox"
                              checked={isSelected}
                              onChange={() => toggleCourseSelection(c.id)}
                              className="w-4 h-4 cursor-pointer accent-blue-500"
                            />
                          </td>
                          <td className="px-4 py-2">{c.name}</td>
                        </tr>
                      );
                    })
                  ) : (
                    <tr>
                      <td
                        colSpan={2}
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
            <h3 className="text-lg font-semibold mb-2 text-gray-800 dark:text-gray-200">
              Cursos asociados
            </h3>
            <div className="overflow-x-auto border border-gray-300 dark:border-gray-700 rounded-lg">
              <table className="min-w-full text-sm text-left">
                <thead className="bg-gray-200 dark:bg-gray-800 uppercase text-gray-700 dark:text-gray-300 sticky top-0">
                  <tr>
                    <th className="px-4 py-2">Curso</th>
                    <th className="px-4 py-2">Semestre</th>
                    <th className="px-4 py-2 text-center">Obligatorio</th>
                    <th className="px-4 py-2 text-center">Acción</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                  {linkedCourses.length > 0 ? (
                    linkedCourses.map((c) => (
                      <tr
                        key={c.id}
                        className="hover:bg-gray-100 dark:hover:bg-gray-800/50 transition"
                      >
                        <td className="px-4 py-2">{c.name}</td>
                        <td className="px-4 py-2">{c.semester ?? "-"}</td>
                        <td className="px-4 py-2 text-center">
                          {c.is_mandatory ? "✅" : "—"}
                        </td>
                        <td className="px-4 py-2 text-center">
                          <button
                            onClick={() => handleDetach(c.id)}
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
                        colSpan={4}
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
        <div className="border-t border-gray-200 dark:border-gray-700 p-4 flex justify-end bg-gray-100 dark:bg-gray-800/70">
          <button
            onClick={onClose}
            className="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-md transition"
          >
            Cerrar
          </button>
        </div>
      </div>
    </div>
  );
}
