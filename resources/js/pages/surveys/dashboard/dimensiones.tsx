import { useState } from 'react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import BarraApilada, { LeyendaBandas } from './barra-apilada';
import { Suprimido } from './confidencialidad';
import { pct, prom, type Metricas } from './tipos';

/**
 * Las 5 dimensiones en barras apiladas al 100%, expandibles al detalle de
 * sus preguntas.
 *
 * El detalle NO se pide al servidor al expandir: las 30 preguntas ya
 * vienen en la carga inicial. Son 30 filas ya agregadas en SQL, no 26.000
 * respuestas, así que traerlas de entrada cuesta menos que un round-trip
 * por cada clic y hace que expandir sea instantáneo.
 *
 * IMPORTANTE: el % de una dimensión NO es el promedio de los % de sus
 * preguntas. Cada nivel se agrega por separado en SQL sobre las filas
 * crudas. Promediar porcentajes solo daría lo mismo si todas las preguntas
 * tuvieran exactamente el mismo n, y en cuanto una pregunta tenga una
 * respuesta menos, los números dejarían de cerrar contra el total.
 */

function Fila({
    fila,
    minimo,
    expandida,
    onToggle,
    preguntas,
}: {
    fila: Metricas;
    minimo: number;
    expandida: boolean;
    onToggle: () => void;
    preguntas: Metricas[];
}) {
    return (
        <div className="border-b border-slate-200 last:border-b-0 dark:border-slate-700">
            <button
                type="button"
                onClick={onToggle}
                aria-expanded={expandida}
                className="flex w-full items-start gap-3 px-1 py-4 text-left transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
            >
                <span className="mt-0.5 shrink-0 text-slate-400">
                    {expandida ? <ChevronDown size={18} /> : <ChevronRight size={18} />}
                </span>

                <span className="min-w-0 flex-1">
                    <span className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <span className="font-semibold text-slate-900 dark:text-slate-50">{fila.title}</span>

                        <span className="flex shrink-0 items-baseline gap-3 text-sm">
                            <span className="text-slate-500 tabular-nums dark:text-slate-400">
                                {preguntas.length} {preguntas.length === 1 ? 'pregunta' : 'preguntas'}
                            </span>
                            <span className="font-bold text-slate-900 tabular-nums dark:text-slate-50">
                                {prom(fila.promedio)}
                            </span>
                        </span>
                    </span>

                    <span className="mt-2.5 block">
                        <BarraApilada fila={fila} />
                    </span>

                    {fila.suprimido && (
                        <span className="mt-2 block">
                            <Suprimido participantes={fila.participantes} minimo={minimo} compacto />
                        </span>
                    )}
                </span>
            </button>

            {expandida && (
                <div className="space-y-4 border-t border-dashed border-slate-200 bg-slate-50/60 px-4 py-5 dark:border-slate-700 dark:bg-slate-800/30">
                    {preguntas.length === 0 && (
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Esta dimensión no tiene preguntas con resultados.
                        </p>
                    )}

                    {preguntas.map((pregunta) => (
                        <div key={pregunta.id}>
                            <div className="mb-2 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                <p className="min-w-0 flex-1 text-sm leading-6 text-slate-700 dark:text-slate-300">
                                    {pregunta.question}
                                </p>

                                <span className="shrink-0 text-sm font-semibold text-slate-900 tabular-nums dark:text-slate-50">
                                    {prom(pregunta.promedio)}
                                </span>
                            </div>

                            <BarraApilada fila={pregunta} alto="h-5" />

                            {pregunta.suprimido ? (
                                <div className="mt-2">
                                    <Suprimido participantes={pregunta.participantes} minimo={minimo} compacto />
                                </div>
                            ) : (
                                <p className="mt-1.5 text-xs text-slate-500 tabular-nums dark:text-slate-400">
                                    Favorable {pct(pregunta.favorable)} · Neutral {pct(pregunta.neutral)} ·
                                    Desfavorable {pct(pregunta.desfavorable)} · {pregunta.respuestas} respuestas
                                </p>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

export default function Dimensiones({
    dimensiones,
    preguntas,
    minimo,
}: {
    dimensiones: Metricas[];
    preguntas: Metricas[];
    minimo: number;
}) {
    const [abierta, setAbierta] = useState<string | null>(null);

    return (
        <section className="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900 sm:p-6">
            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-base font-bold text-slate-900 dark:text-slate-50">
                    Resultados por dimensión
                </h2>
                <LeyendaBandas />
            </div>

            <p className="mb-4 text-sm text-slate-500 dark:text-slate-400">
                Hacé clic en una dimensión para ver el detalle de sus preguntas.
            </p>

            <div>
                {dimensiones.map((dimension) => (
                    <Fila
                        key={dimension.category}
                        fila={dimension}
                        minimo={minimo}
                        expandida={abierta === dimension.category}
                        onToggle={() => setAbierta((actual) => (actual === dimension.category ? null : dimension.category))}
                        preguntas={preguntas.filter((pregunta) => pregunta.category === dimension.category)}
                    />
                ))}
            </div>
        </section>
    );
}
