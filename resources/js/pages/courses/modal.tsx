import { Dialog } from '@headlessui/react';
import { useEffect, useState } from 'react';
import axios from 'axios';
import { X, BookOpen } from 'lucide-react';

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
  const [form, setForm] = useState({
    name: '',
    languageIds: [] as number[],
    technologyIds: [] as number[],
    methodologyIds: [] as number[],
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

  if (!open) return null;

  return (
    <Dialog open={open} onClose={onClose} className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Overlay */}
      <div className="fixed inset-0 bg-black/60 dark:bg-black/70 backdrop-blur-sm" aria-hidden="true" />

      {/* Modal container */}
      <div className="relative bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 rounded-xl border border-gray-200 dark:border-gray-700 shadow-2xl w-full max-w-2xl flex flex-col max-h-[90vh] overflow-hidden">
        {/* Header */}
        <div className="sticky top-0 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex items-center justify-between z-10">
          <div className="flex items-center gap-2">
            <BookOpen className="w-5 h-5 text-blue-600 dark:text-blue-400" />
            <Dialog.Title className="text-lg font-semibold">
              {itemToEdit ? 'Editar Curso' : 'Nuevo Curso'}
            </Dialog.Title>
          </div>
          <button
            onClick={onClose}
            className="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white transition"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Contenido con scroll */}
        <div className="flex-1 overflow-y-auto px-6 py-4 space-y-6">
          {/* Nombre */}
          <div>
            <label className="block text-sm font-medium mb-1">Nombre del Curso *</label>
            <input
              type="text"
              value={form.name}
              onChange={(e) => setForm((prev) => ({ ...prev, name: e.target.value }))}
              className="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 focus:ring-2 focus:ring-blue-500 focus:outline-none"
              disabled={loading}
            />
          </div>

          {/* Lenguajes */}
          <div>
            <label className="block text-sm font-medium mb-2">Lenguajes</label>
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
                  className={`px-3 py-1.5 rounded-lg text-sm border transition ${
                    form.languageIds.includes(l.id)
                      ? 'bg-green-600 text-white border-green-700 shadow'
                      : 'bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-300 border-gray-300 dark:border-gray-700 hover:bg-gray-200 dark:hover:bg-gray-700'
                  }`}
                  disabled={loading}
                >
                  {l.name}
                </button>
              ))}
            </div>
          </div>

          {/* Tecnologías */}
          <div>
            <label className="block text-sm font-medium mb-2">Tecnologías</label>
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
                  className={`px-3 py-1.5 rounded-lg text-sm border transition ${
                    form.technologyIds.includes(t.id)
                      ? 'bg-blue-600 text-white border-blue-700 shadow'
                      : 'bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-300 border-gray-300 dark:border-gray-700 hover:bg-gray-200 dark:hover:bg-gray-700'
                  }`}
                  disabled={loading}
                >
                  {t.name}
                </button>
              ))}
            </div>
          </div>

          {/* Metodologías */}
          <div>
            <label className="block text-sm font-medium mb-2">Metodologías</label>
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
                  className={`px-3 py-1.5 rounded-lg text-sm border transition ${
                    form.methodologyIds.includes(m.id)
                      ? 'bg-purple-600 text-white border-purple-700 shadow'
                      : 'bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-300 border-gray-300 dark:border-gray-700 hover:bg-gray-200 dark:hover:bg-gray-700'
                  }`}
                  disabled={loading}
                >
                  {m.name}
                </button>
              ))}
            </div>
          </div>
        </div>

        {/* Footer fijo */}
        <div className="sticky bottom-0 bg-gray-100 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 px-6 py-3 flex justify-end gap-3">
          <button
            onClick={onClose}
            className="px-4 py-2 rounded-md bg-gray-300 hover:bg-gray-400 text-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-100 transition"
            disabled={loading}
          >
            Cancelar
          </button>
          <button
            onClick={handleSubmit}
            className="px-4 py-2 rounded-md bg-blue-600 hover:bg-blue-700 text-white font-medium shadow disabled:opacity-60 transition"
            disabled={loading}
          >
            {loading ? 'Guardando...' : 'Guardar'}
          </button>
        </div>
      </div>
    </Dialog>
  );
}
