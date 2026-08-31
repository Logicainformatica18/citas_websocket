import { useState } from 'react';
import type { MouseEvent } from 'react';

type Question = { id: number; question: string };
type Result = { client_id: number; answered: number; [key: `answer_${number}`]: string | number | null };

function parseAnswer(raw: string | number | null | undefined) {
    if (raw === null || raw === undefined || raw === '' || raw === 'no_respondido') return null;

    const texto = String(raw);
    const match = texto.match(/^(\d+)-(.*)$/s);

    if (!match) return { score: null, label: texto };

    return { score: Number(match[1]), label: match[2] };
}

function scoreColor(score: number | null) {
    if (score === null) return 'bg-slate-50 text-slate-700 dark:bg-neutral-800 dark:text-neutral-300';
    if (score <= 2) return 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300';
    if (score === 3) return 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300';
    return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300';
}

const BORDE = 'border-b border-r border-slate-200 dark:border-neutral-800';
const FIJA = 'bg-white dark:bg-neutral-900';
const CABECERA = 'bg-slate-100 dark:bg-neutral-800';

export function ReportTable({
    questions,
    results,
    compact,
    loading = false,
    onHover,
    onLeave,
}: {
    questions: Question[];
    results: Result[];
    compact: boolean;
    loading?: boolean;
    onHover: (event: MouseEvent<HTMLElement>, text: string) => void;
    onLeave: () => void;
}) {
    const [columna, setColumna] = useState<number | null>(null);

    const anchoCelda = compact ? 'w-[52px] min-w-[52px]' : 'w-[200px] min-w-[200px]';

    const entrar = (event: MouseEvent<HTMLElement>, texto: string, indice: number) => {
        setColumna(indice);
        onHover(event, texto);
    };

    const salir = () => {
        setColumna(null);
        onLeave();
    };

    if (results.length === 0) {
        return (
            <div className="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-4 py-12 text-center text-sm text-slate-500 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-400">
                Aún no hay respuestas para esta encuesta.
            </div>
        );
    }

    return (
        // min-w-0 acá es lo que hace que el scroll quede adentro y no en la página.
        <div className={[
            'w-full min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-opacity dark:border-neutral-800 dark:bg-neutral-900',
            loading ? 'opacity-60' : 'opacity-100',
        ].join(' ')}>
            <div className="max-h-[70vh] w-full min-w-0 overflow-auto overscroll-x-contain">
                {/* w-max: la tabla mide lo que mide y el contenedor scrollea.
                    border-separate porque border-collapse borra los bordes de
                    las celdas sticky. */}
                <table className="w-max border-separate border-spacing-0 text-[11px] sm:text-xs">
                    <thead>
                        <tr>
                            <th
                                scope="col"
                                className={`sticky left-0 top-0 z-40 w-[92px] min-w-[92px] px-2 py-3 text-left font-semibold text-slate-700 ${CABECERA} ${BORDE} dark:text-neutral-200`}
                            >
                                Participante
                            </th>

                            <th
                                scope="col"
                                className={`sticky left-[92px] top-0 z-40 w-[64px] min-w-[64px] px-2 py-3 text-center font-semibold text-slate-700 ${CABECERA} ${BORDE} dark:text-neutral-200`}
                            >
                                Resp.
                            </th>

                            {questions.map((question, index) => (
                                <th
                                    key={question.id}
                                    scope="col"
                                    title={question.question}
                                    onMouseEnter={(event) => entrar(event, question.question, index)}
                                    onMouseLeave={salir}
                                    className={[
                                        'sticky top-0 z-30 cursor-help px-1 py-3 align-top font-medium text-slate-700 transition dark:text-neutral-200',
                                        CABECERA,
                                        BORDE,
                                        anchoCelda,
                                        compact ? 'text-center' : 'text-left',
                                        columna === index ? 'bg-slate-200 dark:bg-neutral-700' : '',
                                    ].join(' ')}
                                >
                                    {compact ? (
                                        `P${index + 1}`
                                    ) : (
                                        <span className="line-clamp-3 block text-left text-[11px] leading-snug">
                                            {question.question}
                                        </span>
                                    )}
                                </th>
                            ))}
                        </tr>
                    </thead>

                    <tbody>
                        {results.map((result) => {
                            const contestadas = result.answered ?? 0;
                            const incompleta = contestadas < questions.length;

                            return (
                                <tr key={result.client_id} className="group">
                                    <th
                                        scope="row"
                                        className={`sticky left-0 z-20 w-[92px] min-w-[92px] px-2 py-2 text-left font-medium text-slate-800 ${FIJA} ${BORDE} group-hover:bg-slate-50 dark:text-neutral-200 dark:group-hover:bg-neutral-800`}
                                    >
                                        #{result.client_id}
                                    </th>

                                    <td
                                        title={`${contestadas} de ${questions.length} preguntas contestadas`}
                                        className={[
                                            'sticky left-[92px] z-20 w-[64px] min-w-[64px] px-2 py-2 text-center tabular-nums',
                                            FIJA,
                                            BORDE,
                                            'group-hover:bg-slate-50 dark:group-hover:bg-neutral-800',
                                            incompleta
                                                ? 'font-semibold text-amber-700 dark:text-amber-300'
                                                : 'text-slate-500 dark:text-neutral-400',
                                        ].join(' ')}
                                    >
                                        {contestadas}/{questions.length}
                                    </td>

                                    {questions.map((question, index) => {
                                        const answer = parseAnswer(result[`answer_${question.id}`]);
                                        const resaltada = columna === index ? 'ring-1 ring-inset ring-slate-300 dark:ring-neutral-600' : '';

                                        if (!answer) {
                                            return (
                                                <td
                                                    key={question.id}
                                                    onMouseEnter={() => setColumna(index)}
                                                    onMouseLeave={salir}
                                                    className={`px-1 py-2 text-center text-slate-300 dark:text-neutral-600 ${BORDE} ${anchoCelda} ${resaltada}`}
                                                >
                                                    –
                                                </td>
                                            );
                                        }

                                        // Respuesta abierta: texto sin prefijo de posición.
                                        if (answer.score === null) {
                                            return (
                                                <td
                                                    key={question.id}
                                                    title={answer.label}
                                                    onMouseEnter={(event) => entrar(event, answer.label, index)}
                                                    onMouseLeave={salir}
                                                    className={`max-w-[200px] cursor-help px-1 py-2 align-top ${BORDE} ${anchoCelda} ${resaltada}`}
                                                >
                                                    <span
                                                        className={[
                                                            'max-w-full break-words text-[11px] leading-relaxed text-slate-700 dark:text-neutral-300',
                                                            compact ? 'line-clamp-2' : 'block',
                                                        ].join(' ')}
                                                    >
                                                        {answer.label}
                                                    </span>
                                                </td>
                                            );
                                        }

                                        return (
                                            <td
                                                key={question.id}
                                                title={`${question.question}\n\n→ ${answer.score} · ${answer.label}`}
                                                onMouseEnter={(event) =>
                                                    entrar(event, `${question.question}\n\n→ ${answer.score} · ${answer.label}`, index)
                                                }
                                                onMouseLeave={salir}
                                                className={[
                                                    'cursor-help px-1 py-2 text-center align-middle tabular-nums',
                                                    scoreColor(answer.score),
                                                    BORDE,
                                                    anchoCelda,
                                                    resaltada,
                                                ].join(' ')}
                                            >
                                                {compact ? (
                                                    <span className="font-semibold">{answer.score}</span>
                                                ) : (
                                                    <span className="block break-words text-[11px] leading-relaxed">
                                                        <span className="font-semibold">{answer.score}</span> · {answer.label}
                                                    </span>
                                                )}
                                            </td>
                                        );
                                    })}
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <div className="border-t border-slate-200 px-3 py-2 text-[11px] text-slate-400 dark:border-neutral-800 dark:text-neutral-500">
                {results.length} {results.length === 1 ? 'fila' : 'filas'} · desplazá la tabla en horizontal para ver las{' '}
                {questions.length} preguntas
            </div>
        </div>
    );
}