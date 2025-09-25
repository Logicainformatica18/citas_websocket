import { Dialog } from '@headlessui/react';
import { useEffect, useState } from 'react';
import axios from 'axios';

type SimpleItem = { id: number; name: string };

type Course = {
  id?: number;
  name: string;
  languages: SimpleItem[];
  technologies: SimpleItem[];
  methodologies: SimpleItem[];
};

type Props = {
  open: boolean;
  onClose: () => void;
  onSaved: (course: Course) => void;
  itemToEdit: Course | null;
  languages: SimpleItem[];
  technologies: SimpleItem[];
  methodologies: SimpleItem[];
};

export default function CourseModal({
  open,
  onClose,
  onSaved,
  itemToEdit,
  languages,
  technologies,
  methodologies,
}: Props) {
  const [form, setForm] = useState<{
    name: string;
    languageIds: number[];
    technologyIds: number[];
    methodologyIds: number[];
  }>({
    name: '',
    languageIds: [],
    technologyIds: [],
    methodologyIds: [],
  });

  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (itemToEdit) {
      setForm({
        name: itemToEdit.name,
        languageIds: itemToEdit.languages.map((l) => l.id),
        technologyIds: itemToEdit.technologies.map((t) => t.id),
        methodologyIds: itemToEdit.methodologies.map((m) => m.id),
      });
    } else {
      setForm({
        name: '',
        languageIds: [],
        technologyIds: [],
        methodologyIds: [],
      });
    }
  }, [itemToEdit]);

  const toggleArrayValue = (arr: number[], id: number) =>
    arr.includes(id) ? arr.filter((x) => x !== id) : [...arr, id];

  const handleSubmit = async () => {
    if (!form.name.trim()) {
      alert('El nombre es obligatorio');
      return;
    }

    setLoading(true);
    try {
      const payload = {
        name: form.name,
        languages: form.languageIds,
        technologies: form.technologyIds,
        methodologies: form.methodologyIds,
      };

      let res;
      if (itemToEdit?.id) {
        res = await axios.put(`/courses/${itemToEdit.id}`, payload);
      } else {
        res = await axios.post('/courses', payload);
      }

      onSaved(res.data.course);
      onClose();
    } catch (e) {
      console.error('Error al guardar curso', e);
      alert('No se pudo guardar el curso.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog
      open={open}
      onClose={onClose}
      className="fixed inset-0 z-50 overflow-y-auto"
    >
      <div className="flex items-center justify-center min-h-screen p-4">
        {/* Overlay */}
        <div className="fixed inset-0 bg-black/40" aria-hidden="true" />

        {/* Contenido del modal */}
        <div className="relative bg-white rounded-xl shadow-2xl max-w-lg w-full p-6 z-10">
          <Dialog.Title className="text-xl font-bold text-gray-900 mb-6">
            {itemToEdit ? 'Editar Curso' : 'Nuevo Curso'}
          </Dialog.Title>

          {/* Nombre */}
          <div className="mb-5">
            <label className="block text-sm font-semibold text-gray-700 mb-1">
              Nombre del Curso
            </label>
            <input
              type="text"
              value={form.name}
              onChange={(e) =>
                setForm((prev) => ({ ...prev, name: e.target.value }))
              }
              className="w-full border border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
              disabled={loading}
            />
          </div>

          {/* Lenguajes */}
          <div className="mb-5">
            <label className="block text-sm font-semibold text-gray-700 mb-2">
              Lenguajes
            </label>
            <div className="flex flex-wrap gap-2">
              {languages.map((l) => (
                <button
                  key={l.id}
                  type="button"
                  onClick={() =>
                    setForm((prev) => ({
                      ...prev,
                      languageIds: toggleArrayValue(prev.languageIds, l.id),
                    }))
                  }
                  className={`px-3 py-1 rounded-lg text-sm border transition ${
                    form.languageIds.includes(l.id)
                      ? 'bg-green-600 text-white border-green-700 shadow'
                      : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                  }`}
                  disabled={loading}
                >
                  {l.name}
                </button>
              ))}
            </div>
          </div>

          {/* Tecnologías */}
          <div className="mb-5">
            <label className="block text-sm font-semibold text-gray-700 mb-2">
              Tecnologías
            </label>
            <div className="flex flex-wrap gap-2">
              {technologies.map((t) => (
                <button
                  key={t.id}
                  type="button"
                  onClick={() =>
                    setForm((prev) => ({
                      ...prev,
                      technologyIds: toggleArrayValue(prev.technologyIds, t.id),
                    }))
                  }
                  className={`px-3 py-1 rounded-lg text-sm border transition ${
                    form.technologyIds.includes(t.id)
                      ? 'bg-blue-600 text-white border-blue-700 shadow'
                      : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                  }`}
                  disabled={loading}
                >
                  {t.name}
                </button>
              ))}
            </div>
          </div>

          {/* Metodologías */}
          <div className="mb-6">
            <label className="block text-sm font-semibold text-gray-700 mb-2">
              Metodologías
            </label>
            <div className="flex flex-wrap gap-2">
              {methodologies.map((m) => (
                <button
                  key={m.id}
                  type="button"
                  onClick={() =>
                    setForm((prev) => ({
                      ...prev,
                      methodologyIds: toggleArrayValue(prev.methodologyIds, m.id),
                    }))
                  }
                  className={`px-3 py-1 rounded-lg text-sm border transition ${
                    form.methodologyIds.includes(m.id)
                      ? 'bg-purple-600 text-white border-purple-700 shadow'
                      : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                  }`}
                  disabled={loading}
                >
                  {m.name}
                </button>
              ))}
            </div>
          </div>

          {/* Acciones */}
          <div className="flex justify-end gap-3">
            <button
              onClick={onClose}
              className="px-4 py-2 rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-700 transition"
              disabled={loading}
            >
              Cancelar
            </button>
            <button
              onClick={handleSubmit}
              className="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 shadow transition"
              disabled={loading}
            >
              {loading ? 'Guardando...' : 'Guardar'}
            </button>
          </div>
        </div>
      </div>
    </Dialog>
  );
}
