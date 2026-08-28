import AppLayout from '@/layouts/app-layout';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import { Paintbrush, Trash2 } from 'lucide-react';
import QuestionsModal from './questions-modal';

type Question = { id: number; question: string; title?: string | null; type: string; detail?: string | null; option?: string[]; selection_id?: number | null; selection?: { description: string } | null };
type Survey = { id: number; title: string };
type Pagination<T> = { data: T[]; current_page: number; last_page: number };

export default function Questions() {
    const { survey, questions: initialQuestions, selections } = usePage<{ survey: Survey; questions: Pagination<Question>; selections: any[] }>().props;
    const [questions, setQuestions] = useState(initialQuestions?.data || []);
    const [pagination, setPagination] = useState(initialQuestions);
    const [edit, setEdit] = useState<Question | null>(null);
    const [open, setOpen] = useState(false);
    const fetchPage = async (page: number) => { const response = await axios.get(`/surveys/${survey.id}/questions/fetch?page=${page}`); setQuestions(response.data.questions.data); setPagination(response.data.questions); };
    const saved = (question: Question) => setQuestions((current) => current.some((item) => item.id === question.id) ? current.map((item) => item.id === question.id ? question : item) : [question, ...current]);
    return <AppLayout breadcrumbs={[{ title: 'Encuestas', href: '/surveys' }, { title: survey.title, href: `/surveys/${survey.id}/questions` }]}><div className="p-8"><div className="mb-4 flex items-center justify-between"><div><h1 className="text-2xl font-bold">Preguntas</h1><p className="text-gray-500">{survey.title}</p></div><button onClick={() => { setEdit(null); setOpen(true); }} className="rounded bg-blue-600 px-4 py-2 text-white">Nueva pregunta</button></div><div className="overflow-x-auto"><table className="min-w-full bg-white shadow-md dark:bg-black"><thead className="bg-gray-100 dark:bg-gray-800"><tr>{['Acciones', 'Pregunta', 'Tipo', 'Opciones / selección', 'Requerida'].map((head) => <th key={head} className="px-4 py-2 text-left">{head}</th>)}</tr></thead><tbody>{questions.map((question) => <tr key={question.id} className="border-t"><td className="space-x-3 px-4 py-2 text-sm"><button onClick={() => { setEdit(question); setOpen(true); }} className="inline-flex items-center gap-1 text-blue-600"><Paintbrush size={16} />Editar</button><button onClick={async () => { if (confirm('¿Eliminar esta pregunta?')) { await axios.delete(`/questions/${question.id}`); setQuestions((current) => current.filter((item) => item.id !== question.id)); } }} className="inline-flex items-center gap-1 text-red-600"><Trash2 size={16} />Eliminar</button></td><td className="px-4 py-2">{question.question}</td><td className="px-4 py-2">{question.type}</td><td className="px-4 py-2">{question.type === 'multiple_option' ? question.option?.join(', ') : question.selection?.description || '-'}</td><td className="px-4 py-2">{question.requerid === 'yes' ? 'Sí' : 'No'}</td></tr>)}</tbody></table></div><div className="mt-6 flex justify-center gap-2">{[...Array(pagination?.last_page || 0)].map((_, index) => { const page = index + 1; return <button key={page} disabled={pagination.current_page === page} onClick={() => fetchPage(page)} className="rounded bg-gray-200 px-3 py-1">{page}</button>; })}</div></div><QuestionsModal open={open} surveyId={survey.id} questionToEdit={edit} selections={selections || []} onClose={() => setOpen(false)} onSaved={saved} /></AppLayout>;
}