import AppLayout from '@/layouts/app-layout';
import { Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import axios from 'axios';
import {
    BarChart3,
    Bell,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Eye,
    ExternalLink,
    FileText,
    ListFilter,
    Paintbrush,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import SurveyModal from './modal';

type Survey = {
    id: number;
    title: string;
    description?: string | null;
    detail?: string | null;
    front_page?: string | null;
    visible?: boolean;
    email_confirmation?: boolean;
    date_start?: string | null;
    date_end?: string | null;
    type?: string | null;
    state?: string | null;
    url?: string | null;
    created_bys?: {
        names?: string | null;
        firstname?: string | null;
        email?: string | null;
    } | null;
};

type Pagination<T> = { data: T[]; current_page: number; last_page: number };

const tabs = [
    { key: 'all', label: 'Todas' },
    { key: 'active', label: 'En curso' },
    { key: 'finished', label: 'Finalizada' },
];

export default function Surveys() {
    const { surveys: initial } = usePage<{ surveys: Pagination<Survey> }>().props;
    const [surveys, setSurveys] = useState(initial?.data || []);
    const [pagination, setPagination] = useState(initial);
    const [edit, setEdit] = useState<Survey | null>(null);
    const [open, setOpen] = useState(false);
    const [selectedTab, setSelectedTab] = useState<'all' | 'active' | 'finished'>('all');

    const filteredSurveys = useMemo(() => {
        if (selectedTab === 'all') return surveys;
        if (selectedTab === 'active') {
            return surveys.filter((survey) => !(survey.state || '').toLowerCase().includes('final'));
        }
        return surveys.filter((survey) => (survey.state || '').toLowerCase().includes('final'));
    }, [selectedTab, surveys]);

    const refresh = async (url = '/surveys/fetch') => {
        const response = await axios.get(url);
        setSurveys(response.data.surveys.data);
        setPagination(response.data.surveys);
    };

    const saved = (survey: Survey) => {
        setSurveys((current) =>
            current.some((item) => item.id === survey.id)
                ? current.map((item) => (item.id === survey.id ? survey : item))
                : [survey, ...current],
        );
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Encuestas', href: '/surveys' }]}>
            <div className="min-h-screen bg-[#edf2f5] p-4 md:p-6">
               

                <div className="mb-4 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-[15px] text-[#6a788b]">Organiza y monitorea tus formularios institucionales.</p>
                        <h2 className="mt-2 text-[28px] font-black leading-none tracking-[-0.05em] text-[#202c36] md:text-[36px]">Encuestas</h2>
                        <p className="mt-3 text-[15px] text-[#67788c]">{surveys.length} encuestas registradas · 1 actualmente en curso</p>
                    </div>

                    <button
                        onClick={() => {
                            setEdit(null);
                            setOpen(true);
                        }}
                        className="inline-flex items-center gap-2 rounded-xl bg-[#1da5d7] px-5 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-[#1589ba]"
                    >
                        <Plus className="h-4 w-4" />
                        Nueva encuesta
                    </button>
                </div>

                <div className="rounded-[20px] border border-[#dfe6eb] bg-[#f7f9fb] p-4 shadow-[0_1px_0_rgba(15,23,42,0.02)]">
                    <div className="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div className="flex flex-wrap gap-2">
                            {tabs.map((tab) => (
                                <button
                                    key={tab.key}
                                    onClick={() => setSelectedTab(tab.key as 'all' | 'active' | 'finished')}
                                    className={`rounded-none border-b-2 px-3 pb-2 text-[15px] font-semibold transition ${selectedTab === tab.key ? 'border-[#1ea5d4] text-[#1ea5d4]' : 'border-transparent text-[#4f5d6d]'}`}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>

                        <div className="relative w-full max-w-md">
                            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#75839a]" />
                            <input
                                type="text"
                                placeholder="Buscar por nombre..."
                                className="w-full rounded-xl border border-[#dfe6eb] bg-white py-2.5 pl-9 pr-3 text-[15px] text-[#273548] outline-none placeholder:text-[#7c8aa0] focus:border-[#1ea5d4]"
                            />
                        </div>
                    </div>

                    <div className="overflow-hidden rounded-xl border border-[#dfe6eb] bg-white">
                        <table className="min-w-full text-left text-[13px]">
                            <thead>
                                <tr className="bg-[#f0f3f6] text-[11px] font-semibold uppercase tracking-[0.12em] text-[#64768b]">
                                    <th className="px-4 py-3">Encuesta</th>
                                    <th className="px-4 py-3">Estado</th>
                                    <th className="px-4 py-3">Actividad</th>
                                    <th className="px-4 py-3">Periodo</th>
                                    <th className="px-4 py-3">Responsable</th>
                                    <th className="px-4 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filteredSurveys.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-10 text-center text-[#6d7c8b]">
                                            No hay encuestas para este filtro.
                                        </td>
                                    </tr>
                                ) : (
                                    filteredSurveys.map((survey) => {
                                        const isFinished = (survey.state || '').toLowerCase().includes('final');
                                        const creator = survey.created_bys;
                                        const stateClass = isFinished ? 'bg-[#eef0f3] text-[#4c5867]' : 'bg-[#dff5ea] text-[#1d7c58]';

                                        return (
                                            <tr key={survey.id} className="border-t border-[#edf1f4] align-middle">
                                                <td className="px-4 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-[#daf3fb] text-[#1da5d7]">
                                                            <ListFilter className="h-4 w-4" />
                                                        </div>
                                                        <div>
                                                            <div className="text-[16px] font-bold text-[#223248]">{survey.title}</div>
                                                            <div className="mt-1 text-[11px] text-[#6d7c8b]">
                                                                ID {survey.id} · {survey.state || 'Encuesta'}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4">
                                                    <span className={`inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ${stateClass}`}>
                                                        {isFinished ? 'Finalizada' : 'En curso'}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-4 text-[#46586d]">
                                                    <div className="text-[13px] font-medium">{survey.detail || '1,494'} respuestas</div>
                                                    <div className="mt-1 text-[11px] text-[#6d7c8b]">48 participantes</div>
                                                </td>
                                                <td className="px-4 py-4 text-[#46586d]">
                                                    <div className="text-[12px] font-medium">{survey.date_start ? new Date(survey.date_start).toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—'}</div>
                                                    <div className="mt-1 text-[11px] text-[#6d7c8b]">hasta {survey.date_end ? new Date(survey.date_end).toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—'}</div>
                                                </td>
                                                <td className="px-4 py-4 text-[#46586d]">
                                                    <div className="text-[12px] font-medium">{creator ? `${creator.names || ''} ${creator.firstname || ''}`.trim() || '—' : '—'}</div>
                                                    <div className="mt-1 text-[11px] text-[#6d7c8b]">{creator?.email || 'Sin email'}</div>
                                                </td>
                                                <td className="px-4 py-4">
                                                    <div className="flex justify-end gap-2">
                                                        <Link
                                                            href={`/surveys/${survey.id}/dashboard`}
                                                            className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-[#dfe6eb] bg-white text-[#516077] transition hover:border-[#1da5d7] hover:text-[#1da5d7]"
                                                            title="Dashboard"
                                                        >
                                                            <BarChart3 className="h-4 w-4" />
                                                        </Link>
                                                        <Link
                                                            href={`/surveys/${survey.id}/report`}
                                                            className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-[#dfe6eb] bg-white text-[#516077] transition hover:border-[#1da5d7] hover:text-[#1da5d7]"
                                                            title="Reporte"
                                                        >
                                                            <FileText className="h-4 w-4" />
                                                        </Link>
                                                        <Link
                                                            href={`/surveys/${survey.id}/questions`}
                                                            className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-[#dfe6eb] bg-white text-[#516077] transition hover:border-[#1da5d7] hover:text-[#1da5d7]"
                                                            title="Ver preguntas"
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setEdit(survey);
                                                                setOpen(true);
                                                            }}
                                                            className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-[#dfe6eb] bg-white text-[#516077] transition hover:border-[#1da5d7] hover:text-[#1da5d7]"
                                                            title="Editar"
                                                        >
                                                            <Paintbrush className="h-4 w-4" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={async () => {
                                                                if (!confirm(`¿Eliminar la encuesta "${survey.title}"?`)) return;
                                                                try {
                                                                    await axios.delete(`/surveys/${survey.id}`);
                                                                    setSurveys((current) => current.filter((item) => item.id !== survey.id));
                                                                } catch (error) {
                                                                    console.error(error);
                                                                    alert('No se pudo eliminar la encuesta.');
                                                                }
                                                            }}
                                                            className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-red-200 bg-red-50 text-red-600 transition hover:bg-red-100"
                                                            title="Eliminar"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="mt-4 flex items-center justify-between gap-3 rounded-xl bg-[#f0f3f6] px-4 py-3">
                        <span className="text-[14px] text-[#5d6d7e]">Mostrando 2 de 2 encuestas</span>
                        <div className="flex items-center gap-2">
                            <button className="flex h-8 w-8 items-center justify-center rounded-lg border border-[#dfe6eb] bg-white text-[#697b8b]">
                                <ChevronLeft className="h-4 w-4" />
                            </button>
                            <button className="flex h-8 w-8 items-center justify-center rounded-lg bg-[#1da5d7] text-sm font-semibold text-white">
                                1
                            </button>
                            <button className="flex h-8 w-8 items-center justify-center rounded-lg border border-[#dfe6eb] bg-white text-[#697b8b]">
                                <ChevronRight className="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>

               
            </div>

            <SurveyModal open={open} surveyToEdit={edit} onClose={() => setOpen(false)} onSaved={saved} />
        </AppLayout>
    );
}
