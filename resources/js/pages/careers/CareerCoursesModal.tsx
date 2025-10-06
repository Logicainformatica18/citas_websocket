import { useEffect, useState } from "react";
import axios from "axios";
import { X, Plus, Trash2 } from "lucide-react";

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

export default function CareerCoursesModal({ open, onClose, careerId, onSynced }: Props) {
  const [availableCourses, setAvailableCourses] = useState<Course[]>([]);
  const [linkedCourses, setLinkedCourses] = useState<Course[]>([]);
  const [selectedCourseId, setSelectedCourseId] = useState<number | null>(null);
  const [semester, setSemester] = useState<string>("");
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (open) {
      fetchAvailableCourses();
      fetchLinkedCourses();
    }
  }, [open]);

 const fetchAvailableCourses = async () => {
  const { data } = await axios.get("/api/courses/list");
  setAvailableCourses(data.courses ?? []); // ✅ ahora carga correctamente
};


  const fetchLinkedCourses = async () => {
    const { data } = await axios.get(`/careers/${careerId}`);
    setLinkedCourses(data.courses ?? []);
  };

  const handleAttach = async () => {
    if (!selectedCourseId) return alert("Selecciona un curso");

    setLoading(true);
    try {
      await axios.post(`/careers/${careerId}/attach-course`, {
        course_id: selectedCourseId,
        semester,
        is_mandatory: true,
      });
      await fetchLinkedCourses();
      setSelectedCourseId(null);
      setSemester("");
      onSynced();
    } catch (err) {
      alert("Error al asociar el curso");
    } finally {
      setLoading(false);
    }
  };

  const handleDetach = async (courseId: number) => {
    if (!confirm("¿Quitar este curso de la carrera?")) return;
    await axios.delete(`/careers/${careerId}/detach-course/${courseId}`);
    setLinkedCourses(prev => prev.filter(c => c.id !== courseId));
    onSynced();
  };

  if (!open) return null;

  return (
    <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
      <div className="bg-slate-800 rounded-xl w-full max-w-3xl p-6 text-white relative">
        <button
          onClick={onClose}
          className="absolute top-3 right-3 text-slate-400 hover:text-white"
        >
          <X className="w-5 h-5" />
        </button>

        <h2 className="text-xl font-bold mb-4">
          Sincronizar cursos con carrera
        </h2>

        {/* Selector de curso */}
        <div className="flex gap-2 items-end mb-4">
          <div className="flex-1">
            <label className="block text-sm mb-1">Selecciona curso</label>
            <select
              value={selectedCourseId ?? ""}
              onChange={(e) => setSelectedCourseId(Number(e.target.value))}
              className="w-full bg-slate-700 text-white rounded p-2"
            >
              <option value="">-- Selecciona --</option>
              {availableCourses.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-sm mb-1">Semestre</label>
            <input
              type="text"
              value={semester}
              onChange={(e) => setSemester(e.target.value)}
              placeholder="Ej: 1er ciclo"
              className="bg-slate-700 text-white rounded p-2 w-32"
            />
          </div>

          <button
            onClick={handleAttach}
            disabled={loading || !selectedCourseId}
            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded flex items-center gap-2"
          >
            <Plus className="w-4 h-4" /> Agregar
          </button>
        </div>

        {/* Tabla de cursos asociados */}
        <div className="overflow-x-auto border border-slate-700 rounded-lg">
          <table className="min-w-full text-sm text-left">
            <thead className="bg-slate-700 uppercase text-slate-300">
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
  );
}
