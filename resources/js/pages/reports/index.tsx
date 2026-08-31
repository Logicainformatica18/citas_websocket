import AppLayout from '@/layouts/app-layout';
import { usePage } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import type { MouseEvent } from 'react';
import { ReportLegend } from './legend';
import { ReportTable } from './table';

type Question = { id: number; question: string };
type Survey = { id: number; title: string };
type Result = { client_id: number; [key: string]: string | number | null };

type Tooltip = { texto: string; x: number; y: number; arriba: boolean } | null;

export default function Report() {
    const { survey, questions, results } = usePage<{
        survey: Survey;
        questions: Question[];
        results: Result[];
    }>().props;

    const [compacto, setCompacto] = useState(true);
    const [soloIncompletos, setSoloIncompletos] = useState(false);
    const [tooltip, setTooltip] = useState<Tooltip>(null);

    const mostrarTooltip = useCallback((e: MouseEvent<HTMLElement>, texto: string) => {
        if (!texto) return;

        const rect = e.currentTarget.getBoundingClientRect();
        const arriba = rect.bottom + 120 > window.innerHeight;

        setTooltip({
            texto,
            x: Math.min(Math.max(rect.left + rect.width / 2, 200), window.innerWidth - 200),
            y: arriba ? rect.top - 8 : rect.bottom + 8,
            arriba,
        });
    }, []);

    const ocultarTooltip = useCallback(() => setTooltip(null), []);

    // Cuántas preguntas contestó cada participante. Sirve para detectar grillas
    // incompletas de un vistazo, que hoy solo se ven mirando la tabla entera.
    const contestadasPorFila = useMemo(() => {
        const mapa = new Map<number, number>();

        for (const result of results) {
            let contadas = 0;

            for (const question of questions) {
                const valor = result[`answer_${question.id}`];
                if (valor !== null && valor !== undefined && valor !== '' && valor !== 'no_respondido') {
                    contadas += 1;
                }
            }

            mapa.set(result.client_id, contadas);
        }

        return mapa;
    }, [results, questions]);

    const incompletos = useMemo(
        () => results.filter((r) => (contestadasPorFila.get(r.client_id) ?? 0) < questions.length).length,
        [results, contestadasPorFila, questions.length],
    );

    const visibles = useMemo(
        () =>
            soloIncompletos
                ? results.filter((r) => (contestadasPorFila.get(r.client_id) ?? 0) < questions.length)
                : results,
        [soloIncompletos, results, contestadasPorFila, questions.length],
    );

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Encuestas', href: '/surveys' },
                { title: 'Reporte', href: `/surveys/${survey.id}/report` },
            ]}
        >
            {/* min-w-0 en toda la cadena: sin esto la tabla empuja el layout
                entero y la barra horizontal aparece en la página, no acá. */}
            <div className="flex w-full min-w-0 flex-1 flex-col gap-4 p-4 sm:p-6">
                <div className="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0">
                        <h1 className="truncate text-2xl font-bold text-slate-800 dark:text-neutral-100">
                            Reporte de encuesta
                        </h1>
                        <p className="truncate text-sm text-slate-500 dark:text-neutral-400">{survey.title}</p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 text-xs sm:text-sm">
                        <span className="text-slate-500 dark:text-neutral-400">
                            {results.length} {results.length === 1 ? 'participante' : 'participantes'} ·{' '}
                            {questions.length} preguntas
                        </span>

                        {incompletos > 0 && (
                            <button
                                onClick={() => setSoloIncompletos(!soloIncompletos)}
                                aria-pressed={soloIncompletos}
                                className={[
                                    'rounded-md border px-3 py-1.5 transition',
                                    soloIncompletos
                                        ? 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-200'
                                        : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800',
                                ].join(' ')}
                            >
                                {incompletos} incompleto{incompletos === 1 ? '' : 's'}
                            </button>
                        )}

                        <button
                            onClick={() => setCompacto(!compacto)}
                            className="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-slate-700 transition hover:bg-slate-50 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800"
                        >
                            {compacto ? 'Ver texto completo' : 'Ver compacto'}
                        </button>
                    </div>
                </div>

                <div className="flex min-w-0 flex-wrap items-center gap-2 text-[11px] sm:text-xs">
                    <span className="rounded bg-red-50 px-2 py-1 text-red-700 dark:bg-red-950/50 dark:text-red-300">
                        1-2 Desfavorable
                    </span>
                    <span className="rounded bg-amber-50 px-2 py-1 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">
                        3 Neutral
                    </span>
                    <span className="rounded bg-emerald-50 px-2 py-1 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                        4-5 Favorable
                    </span>
                    <span className="text-slate-400 dark:text-neutral-500">
                        · Pasá el cursor por una celda para ver el texto completo
                    </span>
                </div>

                <ReportTable
                    questions={questions}
                    results={visibles}
                    contestadasPorFila={contestadasPorFila}
                    compact={compacto}
                    onHover={mostrarTooltip}
                    onLeave={ocultarTooltip}
                />

                <ReportLegend questions={questions} compact={compacto} />
            </div>

            {/* Fuera del contenedor de la tabla: adentro quedaba a merced del
                overflow-hidden del borde redondeado. */}
            {tooltip && (
                <div
                    role="tooltip"
                    className={[
                        'pointer-events-none fixed z-50 max-w-sm -translate-x-1/2 rounded-md bg-slate-900 px-3 py-2 text-xs leading-relaxed text-white shadow-lg dark:bg-neutral-100 dark:text-neutral-900',
                        tooltip.arriba ? '-translate-y-full' : '',
                    ].join(' ')}
                    style={{ left: tooltip.x, top: tooltip.y }}
                >
                    <span className="whitespace-pre-wrap">{tooltip.texto}</span>
                </div>
            )}
        </AppLayout>
    );
}