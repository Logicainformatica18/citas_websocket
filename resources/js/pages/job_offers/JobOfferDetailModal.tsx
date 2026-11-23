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
            <div className="bg-white dark:bg-gray-900 rounded-xl shadow-lg p-6 w-full max-w-5xl max-h-[90vh] overflow-y-auto border dark:border-gray-700">

                <div className="flex justify-between items-center mb-4">
                    <h2 className="text-xl font-bold">Detalle de Oferta</h2>

                    <button
                        onClick={onClose}
                        className="p-2 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-full"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <TitleExclusion info={item.title_exclusion} />
                <Entities entities={item.entities} />

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

/** ¿La oferta está excluida de los indicadores por su título? ¿Con qué patrón? */
function TitleExclusion({ info }: { info: any }) {
    if (!info) return null;
    return (
        <div className={`mb-4 p-3 rounded-lg border text-sm ${info.excluded ? "border-red-300 bg-red-50 dark:bg-red-900/20" : "border-green-300 bg-green-50 dark:bg-green-900/20"}`}>
            {info.excluded ? (
                <>
                    <b>Excluida de los indicadores por su título</b> (rol no TI): patrón <code>{info.pattern ?? `#${info.keyword_id} (eliminado)`}</code>
                    {info.category && <> · categoría {info.category}</>}
                </>
            ) : (
                <b>Válida para los indicadores</b>
            )}
            {info.pending && (
                <div className="text-xs mt-1 text-amber-700 dark:text-amber-300">
                    Con la lista activa de hoy {info.current_list_pattern ? <>la excluiría <code>{info.current_list_pattern}</code></> : "no se excluiría"}:
                    falta ejecutar "Excluir de los indicadores".
                </div>
            )}
        </div>
    );
}

/** Lenguajes, tecnologías y certificaciones asociados y si el detector los confirma. */
function Entities({ entities }: { entities: any }) {
    if (!entities) return null;
    return (
        <div className="mb-4 grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
            {Object.entries(entities).map(([type, group]: any) => (
                <div key={type} className="p-3 border rounded-lg dark:border-gray-700">
                    <div className="font-semibold capitalize mb-1">{group.label} ({group.items.length})</div>
                    {group.items.length === 0 && <div className="text-gray-400 text-xs">Sin asociaciones</div>}
                    <ul className="space-y-1">
                        {group.items.map((e: any) => (
                            <li key={`${type}-${e.id}`} className="flex flex-wrap items-center gap-1">
                                <span className={e.active ? "" : "line-through text-gray-400"} title={e.active ? "" : "Entidad inactiva"}>{e.name}</span>
                                {e.confirmed ? (
                                    <span className="text-[10px] px-1 rounded bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300" title={e.alias ? `Coincide: ${e.alias}` : ""}>confirma el detector</span>
                                ) : (
                                    <span className="text-[10px] px-1 rounded bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300" title={e.verdict ?? ""}>
                                        {e.verdict === "rejected" ? "el detector la descarta" : "sin mención en el texto guardado"}
                                    </span>
                                )}
                                {e.excluded_association && (
                                    <span className="text-[10px] px-1 rounded bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300" title="entity_association_exclusions">no cuenta en el ranking</span>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            ))}
        </div>
    );
}
