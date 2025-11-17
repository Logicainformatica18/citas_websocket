import { X } from "lucide-react";

export default function JobOfferDetailModal({ open, onClose, item }) {
    if (!open || !item) return null;

    const fields = [
        ["Título", item.title],
        ["Empresa", item.company],
        ["País", item.country],
        ["Región", item.region],
        ["Ciudad", item.city],
        ["Modalidad", item.modality],
        ["Carga laboral", item.workload],
        ["Nivel experiencia", item.experience_level],
        ["Educación requerida", item.education_level],
        ["Certificaciones", item.certifications],
        ["Requerimientos", item.requirements],
        ["Habilidades", item.skills],
        ["Descripción", item.description],
        ["Beneficios", item.benefits],
        ["Salario mínimo", item.salary_min],
        ["Salario máximo", item.salary_max],
        ["Moneda", item.currency],
        ["Tipo de compensación", item.compensation_type],
        ["Fuente", item.source],
        ["Query de búsqueda", item.search_query],
        ["Publicado", item.published_at],
        ["Registrado", item.created_at],
        ["URL", item.url]
    ];

    return (
        <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
            <div className="bg-white dark:bg-gray-900 rounded-xl shadow-lg p-6 w-full max-w-3xl max-h-[90vh] overflow-y-auto border dark:border-gray-700">

                <div className="flex justify-between items-center mb-4">
                    <h2 className="text-xl font-bold">Detalle de Oferta</h2>

                    <button
                        onClick={onClose}
                        className="p-2 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-full"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="grid grid-cols-1 gap-4">
                    {fields.map(([label, value]) =>
                        value ? (
                            <div key={label} className="p-3 border rounded-lg dark:border-gray-700">
                                <span className="font-semibold text-gray-700 dark:text-gray-300">
                                    {label}:
                                </span>
                                <div className="mt-1 text-gray-900 dark:text-gray-100 whitespace-pre-wrap">
                                    {value}
                                </div>
                            </div>
                        ) : null
                    )}
                </div>

                {item.url && (
                    <a
                        href={item.url}
                        target="_blank"
                        className="mt-4 block text-center bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700"
                    >
                        Ir a la oferta original
                    </a>
                )}
            </div>
        </div>
    );
}
