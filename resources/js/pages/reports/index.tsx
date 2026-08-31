import AppLayout from '@/layouts/app-layout';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { useCallback, useRef, useState } from 'react';
import type { MouseEvent } from 'react';
import { toast } from 'sonner';
import { ReportLegend } from './legend';
import { ReportTable } from './table';

type Question = { id: number; question: string };
type Survey = { id: number; title: string };
type ReportRow = {
    client_id: number;
    answered: number;
    [key: `answer_${number}`]: string | number | null;
};
type ReportProps = {
    survey: Survey;
    questions: Question[];
    totalQuestions: number;
    results: ReportRow[];
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    summary: {
        participants: number;
        incomplete: number;
    };
    filters: {
        only_incomplete: boolean;
        per_page: number;
    };
};

type Tooltip = { texto: string; x: number; y: number; arriba: boolean } | null;

export default function Report() {
    const {
        survey,
        questions,
        totalQuestions,
        results: initialResults,
        pagination: initialPagination,
        summary: initialSummary,
        filters: initialFilters,
    } = usePage<ReportProps>().props;

    const [compacto, setCompacto] = useState(true);
    const [results, setResults] = useState<ReportRow[]>(initialResults);
    const [pagination, setPagination] = useState(initialPagination);
    const [summary, setSummary] = useState(initialSummary);
    const [filters, setFilters] = useState(initialFilters);
    const [loading, setLoading] = useState(false);
    const [tooltip, setTooltip] = useState<Tooltip>(null);
    const tableRef = useRef<HTMLDivElement | null>(null);

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

    const fetchReport = useCallback(
        async (page = pagination.current_page, perPage = filters.per_page, onlyIncomplete = filters.only_incomplete) => {
            setLoading(true);

            try {
                const response = await axios.get(`/surveys/${survey.id}/report/fetch`, {
                    params: {
                        page,
                        per_page: perPage,
                        only_incomplete: onlyIncomplete ? 1 : 0,
                    },
                });

                setResults(response.data.results);
                setPagination(response.data.pagination);
                setSummary(response.data.summary);
                setFilters(response.data.filters);

                if (tableRef.current) {
                    tableRef.current.scrollTo({ top: 0, behavior: 'smooth' });
                }
            } catch (error: any) {
                toast.error(error?.response?.data?.message || 'No se pudo cargar el reporte.');
            } finally {
                setLoading(false);
            }
        },
        [filters.only_incomplete, filters.per_page, pagination.current_page, survey.id],
    );

    const handleToggleIncomplete = useCallback(() => {
        const nextOnlyIncomplete = !filters.only_incomplete;
        setFilters((current) => ({ ...current, only_incomplete: nextOnlyIncomplete }));
        void fetchReport(1, filters.per_page, nextOnlyIncomplete);
    }, [fetchReport, filters.only_incomplete, filters.per_page]);

    const handlePageChange = useCallback(
        (nextPage: number) => {
            if (nextPage < 1 || nextPage > pagination.last_page || loading) return;

            void fetchReport(nextPage, filters.per_page, filters.only_incomplete);
        },
        [fetchReport, filters.only_incomplete, filters.per_page, loading, pagination.last_page],
    );

    const handlePerPageChange = useCallback(
        (value: number) => {
            if (loading) return;
            setFilters((current) => ({ ...current, per_page: value }));
            void fetchReport(1, value, filters.only_incomplete);
        },
        [fetchReport, filters.only_incomplete, loading],
    );

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Encuestas', href: '/surveys' },
                { title: 'Reporte', href: `/surveys/${survey.id}/report` },
            ]}
        >
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
                            {summary.participants} participantes · {totalQuestions} preguntas
                        </span>

                        {summary.incomplete > 0 && (
                            <button
                                onClick={handleToggleIncomplete}
                                aria-pressed={filters.only_incomplete}
                                className={[
                                    'rounded-md border px-3 py-1.5 transition',
                                    filters.only_incomplete
                                        ? 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-200'
                                        : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800',
                                ].join(' ')}
                            >
                                {summary.incomplete} incompleto{summary.incomplete === 1 ? '' : 's'}
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

                <div ref={tableRef} className="min-w-0 overflow-auto">
                    <ReportTable
                        questions={questions}
                        results={results}
                        compact={compacto}
                        loading={loading}
                        onHover={mostrarTooltip}
                        onLeave={ocultarTooltip}
                    />
                </div>

                <div className="flex min-w-0 flex-col gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-neutral-800 dark:bg-neutral-900 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-wrap items-center gap-3 text-sm text-slate-600 dark:text-neutral-300">
                        <span>
                            Mostrando {results.length} de {pagination.total} participantes
                        </span>
                        <label className="flex items-center gap-2">
                            <span>Por página</span>
                            <select
                                value={filters.per_page}
                                onChange={(event) => handlePerPageChange(Number(event.target.value))}
                                className="rounded border border-slate-200 bg-slate-50 px-2 py-1 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >
                                <option value={25}>25</option>
                                <option value={50}>50</option>
                                <option value={100}>100</option>
                            </select>
                        </label>
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => handlePageChange(pagination.current_page - 1)}
                            disabled={pagination.current_page <= 1 || loading}
                            className="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                        >
                            Anterior
                        </button>
                        <span className="text-sm text-slate-600 dark:text-neutral-300">
                            Página {pagination.current_page} de {pagination.last_page}
                        </span>
                        <button
                            type="button"
                            onClick={() => handlePageChange(pagination.current_page + 1)}
                            disabled={pagination.current_page >= pagination.last_page || loading}
                            className="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                        >
                            Siguiente
                        </button>
                    </div>
                </div>

                <ReportLegend questions={questions} compact={compacto} />
            </div>

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