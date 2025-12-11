import { Dialog } from '@headlessui/react';
import { useEffect, useState } from 'react';
import axios from 'axios';
import { X, Briefcase } from 'lucide-react';

type SimpleItem = { id: number; name: string };

type TechPosition = {
    id?: number;
    position_name: string;
    position_name_en?: string | null;
    category?: string | null;
    subcategory?: string | null;
    active?: number;

    languages: SimpleItem[];
    technologies: SimpleItem[];
    methodologies: SimpleItem[];
    competencies: SimpleItem[];
};

type Props = {
    open: boolean;
    onClose: () => void;
    onSaved: (position: TechPosition) => void;
    itemToEdit: TechPosition | null;

    languages: SimpleItem[];
    technologies: SimpleItem[];
    methodologies: SimpleItem[];
    competencies: SimpleItem[];
};

export default function TechPositionModal({
    open,
    onClose,
    onSaved,
    itemToEdit,
    languages,
    technologies,
    methodologies,
    competencies,
}: Props) {
    const [form, setForm] = useState({
        position_name: "",
        position_name_en: "",
        category: "",
        subcategory: "",

        languageIds: [] as number[],
        technologyIds: [] as number[],
        methodologyIds: [] as number[],
        competencyIds: [] as number[],
    });

    const [loading, setLoading] = useState(false);

    // -------------------------------------------------------
    // Cargar datos al editar
    // -------------------------------------------------------
    useEffect(() => {
        if (itemToEdit) {
            setForm({
                position_name: itemToEdit.position_name,
                position_name_en: itemToEdit.position_name_en ?? "",
                category: itemToEdit.category ?? "",
                subcategory: itemToEdit.subcategory ?? "",

                languageIds: itemToEdit.languages.map((l) => l.id),
                technologyIds: itemToEdit.technologies.map((t) => t.id),
                methodologyIds: itemToEdit.methodologies.map((m) => m.id),
                competencyIds: itemToEdit.competencies.map((c) => c.id),
            });
        } else {
            setForm({
                position_name: "",
                position_name_en: "",
                category: "",
                subcategory: "",
                languageIds: [],
                technologyIds: [],
                methodologyIds: [],
                competencyIds: [],
            });
        }
    }, [itemToEdit]);

    const toggleArray = (arr: number[], id: number) =>
        arr.includes(id) ? arr.filter((x) => x !== id) : [...arr, id];


    // -------------------------------------------------------
    // Guardar
    // -------------------------------------------------------
    const handleSubmit = async () => {
        if (!form.position_name.trim()) {
            alert("El nombre del rol es obligatorio");
            return;
        }

        setLoading(true);
        try {
            const payload = {
                position_name: form.position_name,
                position_name_en: form.position_name_en,
                category: form.category,
                subcategory: form.subcategory,

                languages: form.languageIds,
                technologies: form.technologyIds,
                methodologies: form.methodologyIds,
                competencies: form.competencyIds,
            };

            let res;
            if (itemToEdit?.id) {
                res = await axios.patch(`/tech-positions/${itemToEdit.id}`, payload);
            } else {
                res = await axios.post(`/tech-positions`, payload);
            }

            onSaved(res.data.position);
            onClose();
        } catch (e) {
            console.error(e);
            alert("No se pudo guardar el rol tecnológico.");
        } finally {
            setLoading(false);
        }
    };

    if (!open) return null;

    return (
        <Dialog open={open} onClose={onClose} className="fixed inset-0 z-50 flex items-center justify-center p-4">

            {/* Overlay */}
            <div className="fixed inset-0 bg-black/60 dark:bg-black/70 backdrop-blur-sm" />

            {/* Modal */}
            <div className="relative bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 rounded-xl border border-gray-200 dark:border-gray-700 shadow-2xl w-full max-w-3xl flex flex-col max-h-[90vh] overflow-hidden">

                {/* Header */}
                <div className="sticky top-0 px-6 py-4 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Briefcase className="w-5 h-5 text-blue-600 dark:text-blue-400" />
                        <Dialog.Title className="text-lg font-semibold">
                            {itemToEdit ? "Editar Rol Tecnológico" : "Nuevo Rol Tecnológico"}
                        </Dialog.Title>
                    </div>
                    <button onClick={onClose} className="text-gray-500 hover:text-gray-800 dark:hover:text-white">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                {/* Content */}
                <div className="flex-1 overflow-y-auto px-6 py-4 space-y-6">

                    {/* Nombre */}
                    <div>
                        <label className="block text-sm font-medium mb-1">Nombre *</label>
                        <input
                            type="text"
                            value={form.position_name}
                            onChange={(e) => setForm({ ...form, position_name: e.target.value })}
                            className="w-full px-3 py-2 rounded-md border dark:bg-gray-800"
                        />
                    </div>

                    {/* Nombre en inglés */}
                    <div>
                        <label className="block text-sm font-medium mb-1">Nombre (Inglés)</label>
                        <input
                            type="text"
                            value={form.position_name_en}
                            onChange={(e) => setForm({ ...form, position_name_en: e.target.value })}
                            className="w-full px-3 py-2 rounded-md border dark:bg-gray-800"
                        />
                    </div>

                    {/* Categoría */}
                    <div>
                        <label className="block text-sm font-medium mb-1">Categoría</label>
                        <input
                            type="text"
                            value={form.category}
                            onChange={(e) => setForm({ ...form, category: e.target.value })}
                            className="w-full px-3 py-2 rounded-md border dark:bg-gray-800"
                        />
                    </div>

                    {/* Subcategoría */}
                    <div>
                        <label className="block text-sm font-medium mb-1">Subcategoría</label>
                        <input
                            type="text"
                            value={form.subcategory}
                            onChange={(e) => setForm({ ...form, subcategory: e.target.value })}
                            className="w-full px-3 py-2 rounded-md border dark:bg-gray-800"
                        />
                    </div>

                    {/* Lenguajes */}
                    <SectionChips
                        title="Lenguajes"
                        items={languages}
                        selected={form.languageIds}
                        onToggle={(id) => setForm({ ...form, languageIds: toggleArray(form.languageIds, id) })}
                        color="green"
                    />

                    {/* Tecnologías */}
                    <SectionChips
                        title="Tecnologías"
                        items={technologies}
                        selected={form.technologyIds}
                        onToggle={(id) => setForm({ ...form, technologyIds: toggleArray(form.technologyIds, id) })}
                        color="blue"
                    />

                    {/* Metodologías */}
                    <SectionChips
                        title="Metodologías"
                        items={methodologies}
                        selected={form.methodologyIds}
                        onToggle={(id) => setForm({ ...form, methodologyIds: toggleArray(form.methodologyIds, id) })}
                        color="purple"
                    />

                    {/* Competencias */}
                    <SectionChips
                        title="Competencias"
                        items={competencies}
                        selected={form.competencyIds}
                        onToggle={(id) => setForm({ ...form, competencyIds: toggleArray(form.competencyIds, id) })}
                        color="yellow"
                    />

                </div>

                {/* Footer */}
                <div className="sticky bottom-0 bg-gray-100 dark:bg-gray-800 border-t border-gray-200 px-6 py-3 flex justify-end gap-3">
                    <button onClick={onClose} className="px-4 py-2 rounded bg-gray-300 dark:bg-gray-700 dark:text-gray-100">
                        Cancelar
                    </button>
                    <button
                        onClick={handleSubmit}
                        className="px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white font-medium"
                        disabled={loading}
                    >
                        {loading ? "Guardando..." : "Guardar"}
                    </button>
                </div>
            </div>
        </Dialog>
    );
}


// -------------------------------------------------------------
// 🌟 Subcomponente para listas de chips seleccionables
// -------------------------------------------------------------
function SectionChips({
    title,
    items,
    selected,
    onToggle,
    color,
}: {
    title: string;
    items: SimpleItem[];
    selected: number[];
    onToggle: (id: number) => void;
    color: "green" | "blue" | "purple" | "yellow";
}) {
    const colorClasses = {
        green: "bg-green-600 border-green-700",
        blue: "bg-blue-600 border-blue-700",
        purple: "bg-purple-600 border-purple-700",
        yellow: "bg-yellow-500 border-yellow-600",
    };

    return (
        <div>
            <label className="block text-sm font-medium mb-2">{title}</label>

            <div className="flex flex-wrap gap-2">
                {[...items]
                    .sort((a, b) => {
                        const sa = selected.includes(a.id);
                        const sb = selected.includes(b.id);
                        if (sa === sb) return a.name.localeCompare(b.name);
                        return sa ? -1 : 1;
                    })
                    .map((item) => (
                        <button
                            key={item.id}
                            onClick={() => onToggle(item.id)}
                            className={`px-3 py-1.5 rounded-lg text-sm border transition 
                                ${
                                    selected.includes(item.id)
                                        ? `${colorClasses[color]} text-white shadow`
                                        : "bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-300 border-gray-300 dark:border-gray-700"
                                }
                            `}
                        >
                            {item.name}
                        </button>
                    ))}
            </div>
        </div>
    );
}
