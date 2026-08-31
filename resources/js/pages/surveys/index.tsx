import AppLayout from '@/layouts/app-layout';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import { ExternalLink, List, Paintbrush, Trash2 } from 'lucide-react';
import SurveyModal from './modal';

type Survey = { id: number; title: string; description?: string | null; detail?: string | null; front_page?: string | null; visible?: boolean; email_confirmation?: boolean; date_start?: string | null; date_end?: string | null; type?: string | null; state?: string | null; url?: string | null; created_bys?: { names?: string | null; firstname?: string | null; email?: string | null } | null; };
type Pagination<T> = { data: T[]; current_page: number; last_page: number };

export default function Surveys() {
    const { surveys: initial } = usePage<{ surveys: Pagination<Survey> }>().props;
    const [surveys, setSurveys] = useState(initial?.data || []);
    const [pagination, setPagination] = useState(initial);
    const [edit, setEdit] = useState<Survey | null>(null);
    const [open, setOpen] = useState(false);

    const refresh = async (url = '/surveys/fetch') => {
        const response = await axios.get(url);
        setSurveys(response.data.surveys.data);
        setPagination(response.data.surveys);
    };
    const saved = (survey: Survey) => setSurveys((current) => current.some((item) => item.id === survey.id) ? current.map((item) => item.id === survey.id ? survey : item) : [survey, ...current]);

    return <AppLayout breadcrumbs={[{ title: 'Encuestas', href: '/surveys' }]}>
        <div className="p-8">
            <h1 className="mb-4 text-2xl font-bold">Listado de Encuestas</h1>
            <button onClick={() => { setEdit(null); setOpen(true); }} className="mb-4 rounded bg-blue-600 px-4 py-2 text-white">Nueva Encuesta</button>
            <div className="mt-4 w-full max-w-full overflow-hidden rounded-md shadow-md">

                <table className="w-full table-fixed bg-white text-xs shadow-md dark:bg-black"><thead className="bg-gray-100 dark:bg-gray-800">
                    <tr>{['Acciones', 'ID', 'Título', 'Tipo', 'URL cliente', 'Reportes', 'Fecha inicio', 'Fecha fin', 'Creado por', 'Confirmación email', 'Visible'].map((head) => <th key={head} className="break-words px-2 py-2 text-left font-semibold">{head}</th>)}</tr></thead><tbody>
                    {surveys.map((survey) => { const creator = survey.created_bys; return <tr key={survey.id} className="border-t align-top"><td className="break-words px-2 py-2"><div className="flex flex-col items-start gap-1"><button onClick={() => { setEdit(survey); setOpen(true); }} className="inline-flex max-w-full items-center gap-1 text-left text-blue-600"><Paintbrush size={14} className="shrink-0" />Editar</button><button onClick={async () => { if (confirm(`¿Eliminar la encuesta ${survey.title}?`)) { await axios.delete(`/surveys/${survey.id}`); setSurveys((current) => current.filter((item) => item.id !== survey.id)); } }} className="inline-flex max-w-full items-center gap-1 text-left text-red-600"><Trash2 size={14} className="shrink-0" />Eliminar</button><a href={`/surveys/${survey.id}/questions`} className="inline-flex max-w-full items-center gap-1 text-left text-indigo-600"><List size={14} className="shrink-0" />Preguntas</a></div></td><td className="break-words px-2 py-2">{survey.id}</td><td className="break-words px-2 py-2">{survey.title}</td><td className="break-words px-2 py-2">{survey.type || '-'}</td><td className="break-all px-2 py-2"><a href={`/survey/${survey.id}`} target="_blank" rel="noreferrer" className="inline-flex max-w-full items-start gap-1 text-blue-600"><ExternalLink size={14} className="mt-0.5 shrink-0" />Ver encuesta</a></td><td className="break-words px-2 py-2"><div className="flex flex-col items-start gap-1"><a href={`/surveys/${survey.id}/dashboard`} className="text-blue-600 hover:underline">Ver dashboard</a><a href={`/surveys/${survey.id}/report`} className="text-blue-600 hover:underline">Ver reporte</a></div></td><td className="break-words px-2 py-2">{survey.date_start || '-'}</td><td className="break-words px-2 py-2">{survey.date_end || '-'}</td><td className="break-words px-2 py-2">{creator ? `${creator.names || ''} ${creator.firstname || ''}`.trim() || '-' : '-'}</td><td className="break-all px-2 py-2">{creator?.email || '-'}</td><td className="break-words px-2 py-2">{survey.visible ? 'Sí' : 'No'}</td></tr>; })}
                </tbody></table></div>
            <div className="mt-6 flex justify-center gap-2">{[...Array(pagination?.last_page || 0)].map((_, index) => { const page = index + 1; return <button key={page} disabled={pagination.current_page === page} onClick={() => refresh(`/surveys/fetch?page=${page}`)} className="rounded bg-gray-200 px-3 py-1">{page}</button>; })}</div>
        </div>
        <SurveyModal open={open} surveyToEdit={edit} onClose={() => setOpen(false)} onSaved={saved} />
    </AppLayout>;
}