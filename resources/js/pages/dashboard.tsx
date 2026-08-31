import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { Activity, AlertTriangle, BarChart3, ClipboardCheck, Clock3, ShieldAlert } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

type Summary = {
    total_surveys: number;
    public_surveys: number;
    hidden_surveys: number;
    respondents: number;
    submitted_answers: number;
    due_soon: number;
    expired: number;
};

type SurveyBreakdown = {
    id: number;
    title: string;
    state: string;
    date_start: string | null;
    date_end: string | null;
    questions: number;
    answers: number;
    respondents: number;
};

type AlertItem = {
    level: 'warning' | 'danger' | 'info' | 'success';
    title: string;
    message: string;
};

type PageProps = {
    summary: Summary;
    surveyBreakdown: SurveyBreakdown[];
    recentActivity: Array<{
        survey_id: number;
        survey_title: string;
        completed_at: string;
        answers: number;
    }>;
    alerts: AlertItem[];
};

const levelStyles: Record<AlertItem['level'], string> = {
    warning: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/60 dark:bg-amber-900/20 dark:text-amber-200',
    danger: 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-200',
    info: 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900/60 dark:bg-sky-900/20 dark:text-sky-200',
    success: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-900/20 dark:text-emerald-200',
};

const formatDate = (value: string | null) => value ? new Date(value).toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—';

export default function DashboardPage() {
    const { summary, surveyBreakdown, recentActivity, alerts } = usePage<PageProps>().props;

    const cards = [
        {
            title: 'Encuestas activas',
            value: summary.total_surveys,
            icon: ClipboardCheck,
            accent: 'text-sky-600 dark:text-sky-400',
            chip: `${summary.public_surveys} públicas`,
        },
        {
            title: 'Participantes',
            value: summary.respondents,
            icon: Activity,
            accent: 'text-emerald-600 dark:text-emerald-400',
            chip: `${summary.submitted_answers} respuestas`,
        },
        {
            title: 'Vencen pronto',
            value: summary.due_soon,
            icon: Clock3,
            accent: 'text-amber-600 dark:text-amber-400',
            chip: `${summary.expired} vencidas`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm font-medium uppercase tracking-[0.14em] text-sky-700 dark:text-sky-300">Panel operativo</p>
                        <h1 className="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Dashboard general</h1>
                    </div>
                    <div className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                        <BarChart3 className="h-3.5 w-3.5" />
                        Estado global del sistema
                    </div>
                </div>

                <section className="grid gap-4 md:grid-cols-3">
                    {cards.map(({ title, value, icon: Icon, accent, chip }) => (
                        <div key={title} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-sm text-slate-500 dark:text-slate-400">{title}</p>
                                    <p className="mt-3 text-3xl font-bold text-slate-900 dark:text-slate-100">{value}</p>
                                </div>
                                <div className={`rounded-lg bg-slate-100 p-2.5 dark:bg-slate-800 ${accent}`}>
                                    <Icon className="h-5 w-5" />
                                </div>
                            </div>
                            <div className="mt-4 text-xs font-medium text-slate-500 dark:text-slate-400">{chip}</div>
                        </div>
                    ))}
                </section>

                <section className="grid gap-6 xl:grid-cols-[1.4fr_0.8fr]">
                    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        <div className="mb-4 flex items-center justify-between gap-3">
                            <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">Volumen por encuesta</h2>
                            <span className="text-xs uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Live</span>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-slate-200 text-slate-500 dark:border-slate-700 dark:text-slate-400">
                                        <th className="pb-3 pr-4 font-medium">Encuesta</th>
                                        <th className="pb-3 pr-4 font-medium">Estado</th>
                                        <th className="pb-3 pr-4 font-medium">Preguntas</th>
                                        <th className="pb-3 pr-4 font-medium">Respuestas</th>
                                        <th className="pb-3 pr-4 font-medium">Participantes</th>
                                        <th className="pb-3 font-medium">Periodo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {surveyBreakdown.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="py-6 text-center text-slate-500 dark:text-slate-400">
                                                No hay encuestas cargadas.
                                            </td>
                                        </tr>
                                    ) : (
                                        surveyBreakdown.map((survey) => (
                                            <tr key={survey.id} className="border-b border-slate-100 align-top last:border-b-0 dark:border-slate-800">
                                                <td className="py-3 pr-4">
                                                    <div className="font-medium text-slate-800 dark:text-slate-200">{survey.title}</div>
                                                </td>
                                                <td className="py-3 pr-4">
                                                    <span className="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                        {survey.state || 'public'}
                                                    </span>
                                                </td>
                                                <td className="py-3 pr-4 text-slate-700 dark:text-slate-300">{survey.questions}</td>
                                                <td className="py-3 pr-4 text-slate-700 dark:text-slate-300">{survey.answers}</td>
                                                <td className="py-3 pr-4 text-slate-700 dark:text-slate-300">{survey.respondents}</td>
                                                <td className="py-3 text-slate-600 dark:text-slate-400">
                                                    <div>{formatDate(survey.date_start)}</div>
                                                    <div className="text-xs">→ {formatDate(survey.date_end)}</div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        <div className="mb-4 flex items-center justify-between gap-2">
                            <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">Alertas</h2>
                            <ShieldAlert className="h-4 w-4 text-slate-500 dark:text-slate-400" />
                        </div>

                        <div className="space-y-3">
                            {alerts.map((alert, index) => (
                                <div key={`${alert.title}-${index}`} className={`rounded-lg border p-3 ${levelStyles[alert.level]}`}>
                                    <div className="flex items-start gap-2">
                                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                        <div>
                                            <div className="text-sm font-semibold">{alert.title}</div>
                                            <p className="mt-1 text-xs leading-relaxed opacity-90">{alert.message}</p>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">Actividad reciente</h2>
                        <span className="text-xs uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Últimos cierres</span>
                    </div>

                    {recentActivity.length === 0 ? (
                        <div className="rounded-lg border border-dashed border-slate-300 p-6 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            No hubo actividad reciente en encuestas completadas.
                        </div>
                    ) : (
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {recentActivity.map((item) => (
                                <div key={`${item.survey_id}-${item.completed_at}`} className="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                                    <div className="text-xs uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">{item.survey_title}</div>
                                    <div className="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-100">{item.answers}</div>
                                    <div className="mt-1 text-xs text-slate-500 dark:text-slate-400">respuestas en {new Date(item.completed_at).toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' })}</div>
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
