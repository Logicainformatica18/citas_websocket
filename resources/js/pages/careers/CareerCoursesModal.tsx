import { useEffect, useState } from "react";
import axios from "axios";
import { X, Trash2, Plus, Search } from "lucide-react";

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
    } catch (err) {
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

// 🔤 Función utilitaria para eliminar tildes / acentos
const normalizeText = (text: string) =>
  text
    .normalize("NFD") // descompone caracteres con tilde
    .replace(/[\u0300-\u036f]/g, "") // elimina los diacríticos
    .toLowerCase();

const filteredCourses = availableCourses.filter((c) =>
  normalizeText(c.name).includes(normalizeText(searchTerm))
);


  return (
    <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
      <div className="bg-slate-800 rounded-xl w-full max-w-4xl text-white relative shadow-2xl border border-slate-700 flex flex-col max-h-[85vh]">
        {/* Header */}
        <div className="p-5 border-b border-slate-700 flex justify-between items-center">
          <h2 className="text-lg font-bold">Sincronizar cursos con carrera</h2>
          <button
            onClick={onClose}
            className="text-slate-400 hover:text-white transition"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Contenido scrolleable */}
        <div className="flex-1 overflow-y-auto p-5 space-y-5">
          {/* Selector de semestre y botón */}
          <div className="flex items-end gap-3 flex-wrap">
            <div>
              <label className="block text-sm mb-1">Semestre</label>
              <input
                type="text"
                value={semester}
                onChange={(e) => setSemester(e.target.value)}
                placeholder="Ej: 1er ciclo"
                className="bg-slate-700 text-white rounded p-2 w-40"
              />
            </div>

            <button
              onClick={handleAttach}
              disabled={loading || selectedCourseIds.length === 0}
              className="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded flex items-center gap-2"
            >
              <Plus className="w-4 h-4" /> Agregar seleccionados
            </button>
          </div>

          {/* Buscador */}
          <div className="relative mt-3">
            <Search className="absolute left-3 top-2.5 w-4 h-4 text-slate-400" />
            <input
              type="text"
              placeholder="Buscar curso..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="bg-slate-700 text-white rounded pl-9 pr-3 py-2 w-full"
            />
          </div>

          {/* Tabla de cursos disponibles */}
          <div className="border border-slate-700 rounded-lg overflow-hidden">
            <div className="max-h-[40vh] overflow-y-auto">
              <table className="min-w-full text-sm text-left">
                <thead className="bg-slate-700 uppercase text-slate-300 sticky top-0">
                  <tr>
                    <th className="px-4 py-2 text-center">✓</th>
                    <th className="px-4 py-2">Curso disponible</th>
                  </tr>
                </thead>
      <tbody className="divide-y divide-slate-700">
  {filteredCourses.length > 0 ? (
    filteredCourses.map((c) => {
      const isSelected = selectedCourseIds.includes(c.id);
      return (
        <tr
          key={c.id}
          className={`transition-colors ${
            isSelected
              ? "bg-blue-900/40 hover:bg-blue-900/50"
              : "hover:bg-slate-700/40"
          }`}
        >
          <td className="px-4 py-2 text-center">
            <input
              type="checkbox"
              checked={isSelected}
              onChange={() => toggleCourseSelection(c.id)}
              className="w-4 h-4 cursor-pointer accent-cyan-400"
            />
          </td>
          <td
            className="px-4 py-2 text-slate-100 cursor-pointer select-none"
            onClick={() => toggleCourseSelection(c.id)}
          >
            {c.name}
          </td>
        </tr>
      );
    })
  ) : (
    <tr>
      <td colSpan={2} className="px-4 py-6 text-center text-slate-400">
        No se encontraron cursos.
      </td>
    </tr>
  )}
</tbody>


              </table>
            </div>
          </div>

          {/* Tabla de cursos asociados */}
          <div>
            <h3 className="text-lg font-semibold mb-2">Cursos asociados</h3>
            <div className="overflow-x-auto border border-slate-700 rounded-lg">
              <table className="min-w-full text-sm text-left">
                <thead className="bg-slate-700 uppercase text-slate-300 sticky top-0">
                  <tr>
                    <th className="px-4 py-2">Curso</th>
                    <th className="px-4 py-2">Semestre</th>
                    <th className="px-4 py-2 text-center">Obligatorio</th>
                    <th className="px-4 py-2 text-center">Acción</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-700">
                  {linkedCourses.length > 0 ? (
                    linkedCourses.map((c) => (
                      <tr key={c.id} className="hover:bg-slate-700/40">
                        <td className="px-4 py-2">{c.name}</td>
                        <td className="px-4 py-2">{c.semester ?? "-"}</td>
                        <td className="px-4 py-2 text-center">
                          {c.is_mandatory ? "✅" : "—"}
                        </td>
                        <td className="px-4 py-2 text-center">
                          <button
                            onClick={() => handleDetach(c.id)}
                            className="text-red-400 hover:text-red-600"
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
                        className="px-4 py-6 text-center text-slate-400"
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
        <div className="border-t border-slate-700 p-4 flex justify-end bg-slate-900/50">
          <button
            onClick={onClose}
            className="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded"
          >
            Cerrar
          </button>
        </div>
      </div>
    </div>
  );
}
