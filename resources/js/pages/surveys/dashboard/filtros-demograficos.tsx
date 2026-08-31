import { Info, Lock } from 'lucide-react';

/**
 * Filtros de Gerencia / Área / Jerarquía / Modalidad.
 *
 * ------------------------------------------------------------------
 * ESTÁN DESHABILITADOS A PROPÓSITO, Y VACÍOS A PROPÓSITO
 * ------------------------------------------------------------------
 *
 * La tabla `clients` tiene tres columnas: id, completed_at y timestamps.
 * No guarda gerencia, ni área, ni jerarquía, ni modalidad — ni nombre ni
 * correo. Ese es justamente el diseño que hace anónima a la encuesta.
 *
 * Por eso los selects se renderizan SIN OPCIONES. Llenarlos con una lista
 * de gerencias de ejemplo daría la impresión de que el corte existe y que
 * solo falta habilitarlo, y alguien terminaría preguntando por qué los
 * números no cambian al elegir una.
 *
 * Cuando se capturen esos datos, este componente recibe las opciones por
 * props y se le saca el `disabled`. La regla de confidencialidad de menos
 * de 5 participantes ya está implementada en el servidor y aplica a
 * cualquier combinación de filtros, así que no hay que agregarla después:
 * ese es el motivo de haberla construido desde el inicio.
 */

const filtros = [
    { id: 'gerencia', etiqueta: 'Gerencia' },
    { id: 'area', etiqueta: 'Área' },
    { id: 'jerarquia', etiqueta: 'Jerarquía' },
    { id: 'modalidad', etiqueta: 'Modalidad' },
];

export default function FiltrosDemograficos() {
    return (
        <section className="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 className="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    <Lock size={15} className="text-slate-400" />
                    Filtros
                </h2>

                <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    Próximamente
                </span>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {filtros.map((filtro) => (
                    <div key={filtro.id}>
                        <label
                            htmlFor={`filtro-${filtro.id}`}
                            className="mb-1.5 block text-xs font-semibold text-slate-500 dark:text-slate-400"
                        >
                            {filtro.etiqueta}
                        </label>

                        <select
                            id={`filtro-${filtro.id}`}
                            disabled
                            aria-describedby="filtros-aviso"
                            className="w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500"
                        >
                            <option>Todas</option>
                        </select>
                    </div>
                ))}
            </div>

            <p
                id="filtros-aviso"
                className="mt-4 flex items-start gap-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
            >
                <Info size={14} className="mt-0.5 shrink-0" />
                <span>
                    Estos cortes todavía no se pueden aplicar: la encuesta es anónima y hoy no se registra
                    gerencia, área, jerarquía ni modalidad de quien responde. Cuando esos datos se capturen,
                    los filtros se habilitan acá y seguirán la misma regla de confidencialidad: ninguna
                    combinación con menos de 5 participantes muestra resultados.
                </span>
            </p>
        </section>
    );
}
