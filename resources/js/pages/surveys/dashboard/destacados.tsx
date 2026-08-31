import { TrendingDown, TrendingUp } from 'lucide-react';
import BarraApilada from './barra-apilada';
import { pct, prom, type Metricas } from './tipos';

/**
 * Top 3 fortalezas y Top 3 aspectos a priorizar.
 *
 * Las dos listas se calculan en el servidor por % favorable, con desempate
 * por promedio descendente y después por orden ascendente.
 *
 * El desempate no es un detalle: hay dos preguntas empatadas en 81.3%. Sin
 * un criterio fijo, cuál aparece en el podio podría cambiar entre recargas
 * según cómo devuelva las filas el motor, y eso se termina reportando como
 * un bug fantasma que nadie puede reproducir.
 *
 * Las preguntas suprimidas por confidencialidad no entran en ninguna de
 * las dos listas: el servidor las filtra antes de ordenar, así que acá no
 * hay que contemplar el caso.
 */

function Lista({
    titulo,
    descripcion,
    icono,
    acento,
    filas,
    vacio,
}: {
    titulo: string;
    descripcion: string;
    icono: React.ReactNode;
    acento: string;
    filas: Metricas[];
    vacio: string;
}) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900 sm:p-6">
            <div className="mb-1 flex items-center gap-2">
                <span className={`grid h-8 w-8 shrink-0 place-items-center rounded-lg ${acento}`}>{icono}</span>
                <h2 className="text-base font-bold text-slate-900 dark:text-slate-50">{titulo}</h2>
            </div>

            <p className="mb-5 text-sm text-slate-500 dark:text-slate-400">{descripcion}</p>

            {filas.length === 0 ? (
                <p className="text-sm text-slate-500 dark:text-slate-400">{vacio}</p>
            ) : (
                <ol className="space-y-5">
                    {filas.map((fila, indice) => (
                        <li key={fila.id}>
                            <div className="mb-2 flex items-start gap-3">
                                <span className="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    {indice + 1}
                                </span>

                                <div className="min-w-0 flex-1">
                                    <p className="text-sm leading-6 text-slate-800 dark:text-slate-200">
                                        {fila.question}
                                    </p>
                                    <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{fila.title}</p>
                                </div>

                                <span className="shrink-0 text-right">
                                    <span className="block text-sm font-bold text-slate-900 tabular-nums dark:text-slate-50">
                                        {pct(fila.favorable)}
                                    </span>
                                    <span className="block text-xs text-slate-500 tabular-nums dark:text-slate-400">
                                        {prom(fila.promedio)}
                                    </span>
                                </span>
                            </div>

                            <BarraApilada fila={fila} alto="h-5" />
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
}

export default function Destacados({
    fortalezas,
    prioridades,
}: {
    fortalezas: Metricas[];
    prioridades: Metricas[];
}) {
    return (
        <section className="grid gap-5 lg:grid-cols-2">
            <Lista
                titulo="Top 3 fortalezas"
                descripcion="Las preguntas con mayor porcentaje de respuestas favorables."
                icono={<TrendingUp size={16} className="text-emerald-700 dark:text-emerald-300" />}
                acento="bg-emerald-100 dark:bg-emerald-950"
                filas={fortalezas}
                vacio="Todavía no hay preguntas con resultados suficientes."
            />

            <Lista
                titulo="Top 3 aspectos a priorizar"
                descripcion="Las preguntas con menor porcentaje de respuestas favorables."
                icono={<TrendingDown size={16} className="text-rose-700 dark:text-rose-300" />}
                acento="bg-rose-100 dark:bg-rose-950"
                filas={prioridades}
                vacio="Todavía no hay preguntas con resultados suficientes."
            />
        </section>
    );
}
