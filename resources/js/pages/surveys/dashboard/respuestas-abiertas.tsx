import { useEffect, useState } from 'react';
import axios from 'axios';
import { MessageSquareQuote } from 'lucide-react';
import { Suprimido } from './confidencialidad';
import type { Abierta, Paginacion, RespuestaAbierta } from './tipos';

/**
 * Respuestas abiertas, separadas por pregunta y paginadas.
 *
 * Se piden bajo demanda a /surveys/{id}/dashboard/open-answers/{pregunta},
 * que pagina en SQL con LIMIT/OFFSET. No vienen en la carga inicial a
 * propósito: son texto libre y son el dato más pesado y más sensible de la
 * encuesta. Traerlas todas de entrada significaría mandar cientos de
 * comentarios al navegador aunque nadie los abra.
 *
 * La regla de confidencialidad también aplica acá, y con más razón: un
 * comentario escrito a mano es lo más identificable que hay en toda la
 * encuesta. El servidor no manda los textos si la pregunta tiene menos de
 * `minimo` respuestas.
 */

function Pregunta({ surveyId, abierta, minimo }: { surveyId: number; abierta: Abierta; minimo: number }) {
    const [pagina, setPagina] = useState(1);
    const [datos, setDatos] = useState<Paginacion<RespuestaAbierta> | null>(null);
    const [suprimido, setSuprimido] = useState(abierta.suprimido);
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        // Si el servidor ya avisó en la carga inicial que esta pregunta va
        // suprimida, ni se pide: no hay nada que traer.
        if (abierta.suprimido) return;

        let vigente = true;

        const traer = async () => {
            try {
                setCargando(true);
                setError('');

                const { data } = await axios.get(
                    `/surveys/${surveyId}/dashboard/open-answers/${abierta.id}?page=${pagina}`,
                );

                if (!vigente) return;

                setSuprimido(Boolean(data.suprimido));
                setDatos(data.suprimido ? null : data.respuestas);
            } catch {
                if (vigente) setError('No se pudieron cargar las respuestas.');
            } finally {
                if (vigente) setCargando(false);
            }
        };

        traer();
        return () => {
            vigente = false;
        };
    }, [surveyId, abierta.id, abierta.suprimido, pagina]);

    return (
        <article className="border-b border-slate-200 py-6 last:border-b-0 last:pb-0 dark:border-slate-700">
            <div className="mb-1 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <h3 className="text-sm font-semibold leading-6 text-slate-900 dark:text-slate-50">
                    {abierta.question}
                </h3>

                <span className="shrink-0 text-xs text-slate-500 tabular-nums dark:text-slate-400">
                    {abierta.total} {abierta.total === 1 ? 'respuesta' : 'respuestas'}
                </span>
            </div>

            <p className="mb-4 text-xs text-slate-500 dark:text-slate-400">{abierta.title}</p>

            {suprimido ? (
                <Suprimido participantes={abierta.total} minimo={minimo} />
            ) : error ? (
                <p className="text-sm text-rose-600 dark:text-rose-400">{error}</p>
            ) : cargando && !datos ? (
                <p className="text-sm text-slate-500 dark:text-slate-400">Cargando respuestas...</p>
            ) : (
                <>
                    <ul className="space-y-3">
                        {datos?.data.map((respuesta, indice) => (
                            <li
                                key={`${datos.current_page}-${indice}`}
                                className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-700 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-300"
                            >
                                {respuesta.answer}
                            </li>
                        ))}
                    </ul>

                    {datos && datos.last_page > 1 && (
                        <div className="mt-4 flex flex-wrap items-center gap-2">
                            {[...Array(datos.last_page)].map((_, indice) => {
                                const numero = indice + 1;
                                const activa = datos.current_page === numero;

                                return (
                                    <button
                                        key={numero}
                                        type="button"
                                        onClick={() => setPagina(numero)}
                                        disabled={activa || cargando}
                                        className={`rounded px-3 py-1 text-sm font-medium transition ${
                                            activa
                                                ? 'bg-blue-600 text-white'
                                                : 'bg-slate-200 text-slate-800 hover:bg-slate-300 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600'
                                        }`}
                                    >
                                        {numero}
                                    </button>
                                );
                            })}
                        </div>
                    )}
                </>
            )}
        </article>
    );
}

export default function RespuestasAbiertas({
    surveyId,
    abiertas,
    minimo,
}: {
    surveyId: number;
    abiertas: Abierta[];
    minimo: number;
}) {
    if (abiertas.length === 0) return null;

    return (
        <section className="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900 sm:p-6">
            <div className="mb-1 flex items-center gap-2">
                <span className="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-indigo-100 dark:bg-indigo-950">
                    <MessageSquareQuote size={16} className="text-indigo-700 dark:text-indigo-300" />
                </span>
                <h2 className="text-base font-bold text-slate-900 dark:text-slate-50">Respuestas abiertas</h2>
            </div>

            <p className="mb-2 text-sm text-slate-500 dark:text-slate-400">
                Comentarios textuales, agrupados por pregunta. Se muestran tal como fueron escritos.
            </p>

            <div>
                {abiertas.map((abierta) => (
                    <Pregunta key={abierta.id} surveyId={surveyId} abierta={abierta} minimo={minimo} />
                ))}
            </div>
        </section>
    );
}
