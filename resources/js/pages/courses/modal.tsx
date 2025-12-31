import { Dialog } from '@headlessui/react';
import { useEffect, useState } from 'react';
import axios from 'axios';
import { X, BookOpen } from 'lucide-react';

type SimpleItem = { id: number; name: string };

type CertificationPivot = {
    relevance_level: string;
    weight: number;
};

type Certification = {
    id: number;
    name: string;
    relevance_level?: string;
    weight?: number;
    pivot?: CertificationPivot;
};

type Course = {
    id?: number;
    name: string;
    languages: SimpleItem[];
    technologies: SimpleItem[];
    methodologies: SimpleItem[];
    certifications?: Certification[]; // 👈 NUEVO
};


type Props = {
    open: boolean;
    onClose: () => void;
    onSaved: (course: Course) => void;
    itemToEdit: Course | null;
    languages: SimpleItem[];
    technologies: SimpleItem[];
    methodologies: SimpleItem[];
    certifications: SimpleItem[]; // 👈 NUEVO
};


export default function CourseModal({
    open,
    onClose,
    onSaved,
    itemToEdit,
    languages,
    technologies,
    methodologies,
    certifications, // 👈 FALTABA
}: Props) {

 const [form, setForm] = useState({
    name: '',
    languageIds: [] as number[],
    technologyIds: [] as number[],
    methodologyIds: [] as number[],
    certifications: [] as {
        id: number;
        relevance_level: string;
        weight: number;
    }[],
});


    const [loading, setLoading] = useState(false);

   useEffect(() => {
    if (itemToEdit) {
        setForm({
            name: itemToEdit.name,
            languageIds: itemToEdit.languages.map((l) => l.id),
            technologyIds: itemToEdit.technologies.map((t) => t.id),
            methodologyIds: itemToEdit.methodologies.map((m) => m.id),
            certifications: itemToEdit.certifications?.map((c) => ({
                id: c.id,
                relevance_level: c.pivot?.relevance_level ?? 'intermediate',
                weight: c.pivot?.weight ?? 1.0,
            })) ?? [],
        });
    } else {
        setForm({
            name: '',
            languageIds: [],
            technologyIds: [],
            methodologyIds: [],
            certifications: [],
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
    certifications: form.certifications, // 👈 NUEVO
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
                            {[...languages]
                                .sort((a, b) => {
                                    const selectedA = form.languageIds.includes(a.id);
                                    const selectedB = form.languageIds.includes(b.id);
                                    return selectedA === selectedB ? a.name.localeCompare(b.name) : selectedA ? -1 : 1;
                                })
                                .map((l) => (
                                    <button
                                        key={l.id}
                                        type="button"
                                        onClick={() =>
                                            setForm((prev) => ({
                                                ...prev,
                                                languageIds: toggleArrayValue(prev.languageIds, l.id),
                                            }))
                                        }
                                        className={`px-3 py-1.5 rounded-lg text-sm border transition ${form.languageIds.includes(l.id)
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
                  {/* Tecnologías */}
<div>
  <label className="block text-sm font-medium mb-2">Tecnologías</label>
  <div className="flex flex-wrap gap-2">
    {[...technologies]
      .sort((a, b) => {
        const selectedA = form.technologyIds.includes(a.id);
        const selectedB = form.technologyIds.includes(b.id);
        // 🧠 Si ambos tienen el mismo estado (seleccionados o no), ordenar por nombre
        if (selectedA === selectedB) {
          return a.name.localeCompare(b.name);
        }
        // 🔝 Los seleccionados primero
        return selectedA ? -1 : 1;
      })
      .map((t) => (
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
                 {/* Metodologías */}
<div>
  <label className="block text-sm font-medium mb-2">Metodologías</label>
  <div className="flex flex-wrap gap-2">
    {[...methodologies]
      .sort((a, b) => {
        const selectedA = form.methodologyIds.includes(a.id);
        const selectedB = form.methodologyIds.includes(b.id);
        // 🧠 Si ambos tienen el mismo estado (seleccionados o no), ordenar alfabéticamente
        if (selectedA === selectedB) {
          return a.name.localeCompare(b.name);
        }
        // 🔝 Los seleccionados primero
        return selectedA ? -1 : 1;
      })
      .map((m) => (
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
{/* CERTIFICACIONES */}
<div>
  <label className="block text-sm font-medium mb-2">Certificaciones</label>

  <div className="space-y-2">
    {certifications.map((c) => {
      const selected = form.certifications.find((x) => x.id === c.id);

      return (
        <div
          key={c.id}
          className="flex items-center gap-2 p-2 rounded border border-gray-300 dark:border-gray-700"
        >
          <input
            type="checkbox"
            checked={!!selected}
            onChange={() =>
              setForm((prev) => ({
                ...prev,
                certifications: selected
                  ? prev.certifications.filter((x) => x.id !== c.id)
                  : [
                      ...prev.certifications,
                      { id: c.id, relevance_level: 'intermediate', weight: 1.0 },
                    ],
              }))
            }
          />

          <span className="flex-1 text-sm">{c.name}</span>

          {selected && (
            <>
              <select
                value={selected.relevance_level}
                onChange={(e) =>
                  setForm((prev) => ({
                    ...prev,
                    certifications: prev.certifications.map((x) =>
                      x.id === c.id ? { ...x, relevance_level: e.target.value } : x
                    ),
                  }))
                }
                className="px-2 py-1 rounded border text-sm"
              >
                <option value="introductory">Intro</option>
                <option value="intermediate">Intermedio</option>
                <option value="advanced">Avanzado</option>
              </select>

              <input
                type="number"
                min={0}
                max={1}
                step={0.1}
                value={selected.weight}
                onChange={(e) =>
                  setForm((prev) => ({
                    ...prev,
                    certifications: prev.certifications.map((x) =>
                      x.id === c.id ? { ...x, weight: Number(e.target.value) } : x
                    ),
                  }))
                }
                className="w-20 px-2 py-1 rounded border text-sm"
              />
            </>
          )}
        </div>
      );
    })}
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
