import { useState, useEffect } from "react";
import { router } from "@inertiajs/react";
import { Check, ChevronDown, X, Filter } from "lucide-react";

export default function JobOfferFilters({ filters, combos }) {

    // ================================
    // 🚀 Estados internos
    // ================================
    const [state, setState] = useState({
        companies: filters.companies ?? [],
        countries: filters.countries ?? [],
        cities: filters.cities ?? [],
        sources: filters.sources ?? [],
        modalities: filters.modalities ?? [],
        job_types: filters.job_types ?? [],
        remote_types: filters.remote_types ?? [],
        workloads: filters.workloads ?? [],

        published_from: filters.published_from ?? "",
        published_to: filters.published_to ?? "",
        created_from: filters.created_from ?? "",
        created_to: filters.created_to ?? "",
    });

    const [open, setOpen] = useState({});
    const [isApplying, setIsApplying] = useState(false);

    // ================================
    // 🚀 Manejo global del loading
    // ================================
// ================================
// 🚀 Manejo global del loading
// ================================
useEffect(() => {
    const start = () => setIsApplying(true);
    const finish = () => setIsApplying(false);

    // SUSCRIPCIÓN GLOBAL
    router.on("start", start);
    router.on("finish", finish);

    // ❌ NO usar cleanup porque router.off NO existe
    // Inertia no duplica listeners, es seguro.
}, []);


    // ================================
    // 🧹 Helpers
    // ================================
    const toggleDropdown = (key) => {
        setOpen((prev) => ({ ...prev, [key]: !prev[key] }));
    };

    const update = (key, value) => {
        setState((prev) => ({ ...prev, [key]: value }));
    };

    const toggleValue = (key, value) => {
        const list = state[key];
        if (list.includes(value)) {
            update(key, list.filter((i) => i !== value));
        } else {
            update(key, [...list, value]);
        }
    };

    const cleanFilters = (data) => {
        const cleaned = {};

        Object.keys(data).forEach(key => {
            const v = data[key];

            if (v === null || v === "" || v === "null" || v === undefined) {
                cleaned[key] = null;
                return;
            }

            if (Array.isArray(v)) {
                cleaned[key] = v.length ? v : null;
                return;
            }

            if (typeof v === "string" && v.includes(",")) {
                cleaned[key] = v.split(",").filter(x => x.trim() !== "");
                return;
            }

            cleaned[key] = v;
        });

        return cleaned;
    };

    // ================================
    // 🚀 Aplicar
    // ================================
    const applyFilters = () => {
        const cleaned = cleanFilters(state);

        router.get("/job-offers", cleaned, {
            preserveScroll: true,
            preserveState: false,
        });
    };

    // ================================
    // 🧽 Limpiar
    // ================================
    const clearFilters = () => {
        router.get(
            "/job-offers",
            {},
            { preserveScroll: true, preserveState: false }
        );
    };

    // ================================
    // 🎛 Multi Select Combobox
    // ================================
    const renderMultiCombobox = (label, key, options) => (
        <div className="flex flex-col w-full gap-2 relative">
            <label className="text-sm font-semibold text-gray-700 dark:text-gray-300">
                {label}
            </label>

            {/* Chips */}
            <div className="flex flex-wrap gap-1">
                {state[key]?.length > 0 ? (
                    state[key].map((item) => (
                        <span
                            key={item}
                            className="flex items-center gap-1 bg-blue-600 text-white text-xs px-2 py-1 rounded-full"
                        >
                            {item}
                            <X
                                className="w-3 h-3 cursor-pointer"
                                onClick={() => toggleValue(key, item)}
                            />
                        </span>
                    ))
                ) : (
                    <span className="text-xs text-gray-400">Sin selección</span>
                )}
            </div>

            {/* Button */}
            <button
                type="button"
                onClick={() => toggleDropdown(key)}
                className="flex justify-between items-center w-full px-3 py-2 border rounded-lg dark:bg-gray-800 dark:border-gray-700"
            >
                <span className="text-sm text-gray-600 dark:text-gray-300">
                    Seleccionar {label.toLowerCase()}
                </span>
                <ChevronDown className="w-4 h-4" />
            </button>

            {/* Dropdown */}
            {open[key] && (
                <div className="absolute z-50 top-full mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                    <input
                        className="w-full px-3 py-2 border-b dark:bg-gray-900 text-sm"
                        placeholder={`Buscar ${label.toLowerCase()}...`}
                        onChange={(e) =>
                            update(`${key}_search`, e.target.value.toLowerCase())
                        }
                    />

                    {options
                        .filter((o) =>
                            state[`${key}_search`]
                                ? o.toLowerCase().includes(state[`${key}_search`])
                                : true
                        )
                        .map((item) => {
                            const selected = state[key]?.includes(item);
                            return (
                                <div
                                    key={item}
                                    onClick={() => toggleValue(key, item)}
                                    className="flex justify-between px-3 py-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
                                >
                                    <span>{item}</span>
                                    {selected && (
                                        <Check className="w-4 h-4 text-blue-600" />
                                    )}
                                </div>
                            );
                        })}
                </div>
            )}
        </div>
    );

    // ================================
    // 🚀 Render principal
    // ================================
    return (
        <div className="mb-6 bg-white dark:bg-gray-800 p-6 rounded-xl shadow border border-gray-200 dark:border-gray-700">
            <div className="flex items-center gap-2 mb-4">
                <Filter className="w-5 h-5 text-blue-600 dark:text-blue-400" />
                <h2 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                    Filtros Avanzados
                </h2>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">

                {renderMultiCombobox("Empresa", "companies", combos.companies)}
                {renderMultiCombobox("País", "countries", combos.countries)}
                {renderMultiCombobox("Ciudad", "cities", combos.cities)}
                {renderMultiCombobox("Fuente", "sources", combos.sources)}

                {renderMultiCombobox("Modalidad", "modalities", combos.modalities)}
                {renderMultiCombobox("Tipo de Trabajo", "job_types", combos.job_types)}
                {renderMultiCombobox("Tipo Remoto", "remote_types", combos.remote_types)}
                {renderMultiCombobox("Carga Laboral", "workloads", combos.workloads)}

                {/* Fechas */}
                <div className="flex flex-col gap-2">
                    <label className="text-sm font-semibold">Publicado Desde</label>
                    <input
                        type="date"
                        value={state.published_from}
                        onChange={(e) => update("published_from", e.target.value)}
                        className="border rounded-lg px-3 py-2 dark:bg-gray-800 dark:border-gray-700"
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <label className="text-sm font-semibold">Publicado Hasta</label>
                    <input
                        type="date"
                        value={state.published_to}
                        onChange={(e) => update("published_to", e.target.value)}
                        className="border rounded-lg px-3 py-2 dark:bg-gray-800 dark:border-gray-700"
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <label className="text-sm font-semibold">Registrado Desde</label>
                    <input
                        type="date"
                        value={state.created_from}
                        onChange={(e) => update("created_from", e.target.value)}
                        className="border rounded-lg px-3 py-2 dark:bg-gray-800 dark:border-gray-700"
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <label className="text-sm font-semibold">Registrado Hasta</label>
                    <input
                        type="date"
                        value={state.created_to}
                        onChange={(e) => update("created_to", e.target.value)}
                        className="border rounded-lg px-3 py-2 dark:bg-gray-800 dark:border-gray-700"
                    />
                </div>
            </div>

            {/* Actions */}
            <div className="mt-6 flex gap-4">

                {/* APPLY */}
                <button
                    onClick={applyFilters}
                    disabled={isApplying}
                    className="px-5 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-lg shadow flex items-center gap-2"
                >
                    {isApplying ? (
                        <>
                            <span className="animate-spin border-2 border-white border-t-transparent rounded-full w-4 h-4"></span>
                            Aplicando...
                        </>
                    ) : (
                        <>Aplicar Filtros</>
                    )}
                </button>

                {/* CLEAR */}
                <button
                    onClick={clearFilters}
                    className="px-5 py-2 bg-gray-300 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg shadow flex items-center gap-2"
                >
                    <X className="w-4 h-4" /> Limpiar Todo
                </button>

            </div>
        </div>
    );
}
