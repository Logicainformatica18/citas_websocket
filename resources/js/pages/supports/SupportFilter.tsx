import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { ChevronDown, ChevronUp } from 'lucide-react';

interface Props {
    onFilter: (filters: Record<string, any>) => void;
    areas: { id_area: number; descripcion: string }[];
    internalStates: { id: number; description: string }[];
}

export default function SupportFilter({ onFilter, areas, internalStates }: Props) {
    const [filters, setFilters] = useState({
        subject: '',
        project: '',
        external_state: '',
        area_id: '',
        internal_state_id: '',
        priority: '',
        date_start: '',
        date_end: '',
    });

    const [isOpen, setIsOpen] = useState(false);

    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
        setFilters({ ...filters, [e.target.name]: e.target.value });
    };
    const [loading, setLoading] = useState(false);

    const handleSubmit = async () => {
        setLoading(true);
        await onFilter(filters);
        setLoading(false);
    };


    return (
        <div className="mb-4">
            <Button
                variant="outline"
                onClick={() => setIsOpen(!isOpen)}
                className="mb-2 flex items-center gap-2"
            >
                {isOpen ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                {isOpen ? 'Ocultar filtros' : 'Mostrar filtros'}
            </Button>

            {isOpen && (
                <div className="space-y-4 border rounded-md p-4 bg-gray-50 text-sm">
                    {/* 🟦 Sección: Texto */}
                    <div className="border p-4 rounded-md bg-white">
                        <h4 className="text-xs font-bold uppercase text-gray-600 mb-2">Filtrar por texto</h4>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-semibold mb-1">Asunto</label>
                                <select
                                    name="subject"
                                    className="w-full border px-2 py-1 rounded"
                                    value={filters.subject}
                                    onChange={handleChange}
                                >
                                    <option value="">-- Todos --</option>
                                    {[
                                        'Avance de Proyecto',
                                        'Boletas',
                                        'Cesion',
                                        'Cita con legal',
                                        'Certificado de lote',
                                        'Constancia de no adeudo',
                                        'Desestimiento',
                                        'EE.CC',
                                        'Formalización',
                                        'Información de su lote',
                                        'Pagos',
                                        'Recojo de contrato',
                                        'Recojo de Letras',
                                        'Traspaso de aportes',
                                        'Visita a proyecto',
                                    ]
                                        .sort()
                                        .map((label) => (
                                            <option key={label} value={label}>
                                                {label}
                                            </option>
                                        ))}
                                </select>

                            </div>
                            <div>
                                <label className="block text-xs font-semibold mb-1">Proyecto</label>
                                <input
                                    type="text"
                                    name="project"
                                    className="w-full border px-2 py-1 rounded"
                                    value={filters.project}
                                    onChange={handleChange}
                                />
                            </div>
                        </div>
                    </div>

                    {/* 🟨 Sección: Estado, Área, Prioridad */}
                    <div className="border p-4 rounded-md bg-white">
                        <h4 className="text-xs font-bold uppercase text-gray-600 mb-2">Estado y asignación</h4>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label className="block text-xs font-semibold mb-1">Estado Atención</label>
                                <select
                                    name="external_state"
                                    className="w-full border px-2 py-1 rounded"
                                    value={filters.external_state}
                                    onChange={handleChange}
                                >
                                    <option value="">-- Todos --</option>
                                    <option value="Por Asignar">Por Asignar</option>
                                    <option value="Asignado">Asignado</option>
                                    <option value="Atendido por ATC">Atendido por ATC</option>
                                </select>

                            </div>
                            <div>
                                <label className="block text-xs font-semibold mb-1">Área</label>
                                <select
                                    name="area_id"
                                    className="w-full border px-2 py-1 rounded"
                                    value={filters.area_id}
                                    onChange={handleChange}
                                >
                                    <option value="">-- Todas --</option>
                                    {areas.map((a) => (
                                        <option key={a.id_area} value={a.id_area}>
                                            {a.descripcion}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-semibold mb-1">Estado Interno</label>
                                <select
                                    name="internal_state_id"
                                    className="w-full border px-2 py-1 rounded"
                                    value={filters.internal_state_id}
                                    onChange={handleChange}
                                >
                                    <option value="">-- Todos --</option>
                                    {internalStates.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.description}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-semibold mb-1">Prioridad</label>
                                <select
                                    name="priority"
                                    className="w-full border px-2 py-1 rounded"
                                    value={filters.priority}
                                    onChange={handleChange}
                                >
                                    <option value="">-- Todas --</option>
                                    <option value="Baja">Baja</option>
                                    <option value="Media">Media</option>
                                    <option value="Alta">Alta</option>

                                </select>
                            </div>
                        </div>
                    </div>

                    {/* 🟩 Sección: Fechas */}
                    <div className="border p-4 rounded-md bg-white">
                        <h4 className="text-xs font-bold uppercase text-gray-600 mb-2">Rango de fechas</h4>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-semibold mb-1">Desde</label>
                                <input
                                    type="date"
                                    name="date_start"
                                    className="w-full border px-2 py-1 rounded"
                                    value={filters.date_start}
                                    onChange={handleChange}
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-semibold mb-1">Hasta</label>
                                <input
                                    type="date"
                                    name="date_end"
                                    className="w-full border px-2 py-1 rounded"
                                    value={filters.date_end}
                                    onChange={handleChange}
                                />
                            </div>
                        </div>
                    </div>

                    <div className="pt-2">
                        <Button onClick={handleSubmit} disabled={loading}>
                            {loading ? (
                                <>
                                    <svg
                                        className="animate-spin h-4 w-4 mr-2 text-white"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle
                                            className="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="10"
                                            stroke="currentColor"
                                            strokeWidth="4"
                                        ></circle>
                                        <path
                                            className="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                                        ></path>
                                    </svg>
                                    Cargando...
                                </>
                            ) : (
                                'Aplicar Filtros'
                            )}
                        </Button>

                    </div>
                </div>
            )}
        </div>
    );
}
