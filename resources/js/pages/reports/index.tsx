import AppLayout from '@/layouts/app-layout';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';

type Question = { id: number; question: string };
type Survey = { id: number; title: string };
type Result = { client_id: number; [key: string]: string | number | null };

type Tooltip = { texto: string; x: number; y: number } | null;

/**
 * Las respuestas Likert se guardan como "4-De acuerdo".
 * Devuelve { score, label }, con score null si es texto libre (abiertas).
 */
function parseAnswer(raw: string | number | null | undefined) {
    if (raw === null || raw === undefined || raw === '') return null;

    const texto = String(raw);
    const m = texto.match(/^(\d+)-(.*)$/s);

    if (!m) return { score: null, label: texto };

    return { score: Number(m[1]), label: m[2] };
}

/** 1-2 desfavorable · 3 neutral · 4-5 favorable */
function scoreColor(score: number | null) {
    if (score === null) return 'bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
    if (score <= 2) return 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300';
    if (score === 3) return 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300';
    return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300';
}

export default function Report() {
    const { survey, questions, results } = usePage<{
        survey: Survey;
        questions: Question[];
        results: Result[];
    }>().props;

    // Compacto muestra solo el puntaje; extendido muestra la etiqueta.
    const [compacto, setCompacto] = useState(true);
    const [tooltip, setTooltip] = useState<Tooltip>(null);

    /**
     * El tooltip se posiciona con `fixed` a partir del rect del elemento.
     *
     * Con `absolute` quedaría recortado por el overflow-x-auto de la tabla:
     * al hacer scroll horizontal, el tooltip de las últimas columnas se
     * cortaría contra el borde del contenedor. Con `fixed` sale del flujo y
     * se posiciona respecto al viewport, así que nunca se clipea.
     *
     * El Math.min/Math.max evita que se salga por los costados de la
     * pantalla en las columnas del extremo.
     */
    const mostrarTooltip = (e: React.MouseEvent<HTMLElement>, texto: string) => {
        if (!texto) return;

        const r = e.currentTarget.getBoundingClientRect();

        setTooltip({
            texto,
            x: Math.min(Math.max(r.left + r.width / 2, 180), window.innerWidth - 180),
            y: r.bottom + 8,
        });
    };

    const ocultarTooltip = () => setTooltip(null);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Encuestas', href: '/surveys' },
                { title: 'Reporte', href: `/surveys/${survey.id}/report` },
            ]}
        >
            <div className="p-8">
                <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">Reporte de encuesta</h1>
                        <p className="text-gray-500">{survey.title}</p>
                    </div>

                    <div className="flex items-center gap-4 text-sm">
                        <span className="text-gray-500">
                            {results.length} {results.length === 1 ? 'participante' : 'participantes'}
                            {' · '}
                            {questions.length} preguntas
                        </span>

                        <button
                            onClick={() => setCompacto(!compacto)}
                            className="rounded border px-3 py-1.5 transition hover:bg-gray-50 dark:hover:bg-gray-800"
                        >
                            {compacto ? 'Ver texto completo' : 'Ver compacto'}
                        </button>
                    </div>
                </div>

                {/* Referencia de color */}
                <div className="mb-4 flex flex-wrap items-center gap-3 text-xs">
                    <span className="rounded bg-red-50 px-2 py-1 text-red-700 dark:bg-red-950 dark:text-red-300">
                        1-2 Desfavorable
                    </span>
                    <span className="rounded bg-amber-50 px-2 py-1 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                        3 Neutral
                    </span>
                    <span className="rounded bg-emerald-50 px-2 py-1 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                        4-5 Favorable
                    </span>
                    <span className="text-gray-400">· Pasá el cursor para ver el texto completo</span>
                </div>

                <div className="w-full overflow-x-auto rounded-md border bg-white shadow-sm dark:bg-black">
                    <table className="min-w-full border-collapse text-sm">
                        <thead className="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                {/* sticky: queda fija al hacer scroll horizontal */}
                                <th className="sticky left-0 z-20 whitespace-nowrap border-r bg-gray-100 px-4 py-3 text-left dark:bg-gray-800">
                                    Participante
                                </th>

                                {questions.map((question, i) => (
                                    <th
                                        key={question.id}
                                        onMouseEnter={(e) => mostrarTooltip(e, question.question)}
                                        onMouseLeave={ocultarTooltip}
                                        className={`cursor-help px-2 py-3 align-top font-medium transition hover:bg-gray-200 dark:hover:bg-gray-700 ${
                                            compacto ? 'w-14 text-center' : 'w-44 min-w-44 text-left'
                                        }`}
                                    >
                                        {compacto ? (
                                            `P${i + 1}`
                                        ) : (
                                            <span className="line-clamp-2 block text-xs font-normal leading-snug">
                                                {question.question}
                                            </span>
                                        )}
                                    </th>
                                ))}
                            </tr>
                        </thead>

                        <tbody>
                            {results.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={questions.length + 1}
                                        className="px-4 py-8 text-center text-gray-500"
                                    >
                                        Aún no hay respuestas para esta encuesta.
                                    </td>
                                </tr>
                            ) : (
                                results.map((result) => (
                                    <tr key={result.client_id} className="border-t">
                                        <td className="sticky left-0 z-10 whitespace-nowrap border-r bg-white px-4 py-2 font-medium dark:bg-black">
                                            #{result.client_id}
                                        </td>

                                        {questions.map((question) => {
                                            const a = parseAnswer(result[`answer_${question.id}`]);

                                            if (!a) {
                                                return (
                                                    <td
                                                        key={question.id}
                                                        className="px-2 py-2 text-center text-gray-300"
                                                    >
                                                        –
                                                    </td>
                                                );
                                            }

                                            // Texto libre (preguntas abiertas): una línea con
                                            // elipsis; el completo va al tooltip.
                                            if (a.score === null) {
                                                return (
                                                    <td
                                                        key={question.id}
                                                        onMouseEnter={(e) => mostrarTooltip(e, a.label)}
                                                        onMouseLeave={ocultarTooltip}
                                                        className="w-44 max-w-44 cursor-help px-2 py-2"
                                                    >
                                                        <span className="block truncate text-xs">
                                                            {a.label}
                                                        </span>
                                                    </td>
                                                );
                                            }

                                            return (
                                                <td
                                                    key={question.id}
                                                    onMouseEnter={(e) =>
                                                        mostrarTooltip(
                                                            e,
                                                            `${question.question}\n\n→ ${a.label}`,
                                                        )
                                                    }
                                                    onMouseLeave={ocultarTooltip}
                                                    className={`cursor-help px-2 py-2 text-center ${scoreColor(a.score)}`}
                                                >
                                                    {compacto ? (
                                                        <span className="font-semibold">{a.score}</span>
                                                    ) : (
                                                        <span className="block truncate text-xs">
                                                            {a.label}
                                                        </span>
                                                    )}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Leyenda: en modo compacto los encabezados son P1..Pn, así que
                    el texto tiene que estar disponible sin depender del hover. */}
                {compacto && questions.length > 0 && (
                    <details className="mt-6 rounded-md border p-4 text-sm">
                        <summary className="cursor-pointer font-medium">
                            Preguntas ({questions.length})
                        </summary>

                        <ol className="mt-4 grid gap-2 md:grid-cols-2">
                            {questions.map((question, i) => (
                                <li key={question.id} className="flex gap-2">
                                    <span className="shrink-0 font-semibold text-gray-500">
                                        P{i + 1}
                                    </span>
                                    <span className="text-gray-700 dark:text-gray-300">
                                        {question.question}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    </details>
                )}
            </div>

            {/* Tooltip. Fuera de la tabla y con position fixed para que el
                overflow-x-auto no lo recorte. */}
            {tooltip && (
                <div
                    className="pointer-events-none fixed z-50 max-w-sm -translate-x-1/2 rounded-md bg-gray-900 px-3 py-2 text-xs leading-relaxed text-white shadow-lg dark:bg-gray-100 dark:text-gray-900"
                    style={{ left: tooltip.x, top: tooltip.y }}
                >
                    <span className="whitespace-pre-wrap">{tooltip.texto}</span>
                </div>
            )}
        </AppLayout>
    );
}
