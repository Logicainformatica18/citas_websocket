import AppLayout from '@/layouts/app-layout';
import { usePage } from '@inertiajs/react';

type Question = { id: number; question: string };
type Survey = { id: number; title: string };
type Result = { client_id: number; [key: string]: string | number | null };

export default function Report() {
    const { survey, questions, results } = usePage<{ survey: Survey; questions: Question[]; results: Result[] }>().props;

    return <AppLayout breadcrumbs={[{ title: 'Encuestas', href: '/surveys' }, { title: 'Reporte', href: `/surveys/${survey.id}/report` }]}>
        <div className="p-8">
            <div className="mb-6">
                <h1 className="text-2xl font-bold">Reporte de encuesta</h1>
                <p className="text-gray-500">{survey.title}</p>
            </div>
            <div className="w-full overflow-x-auto rounded-md border bg-white shadow-sm dark:bg-black">
                <table className="min-w-full text-sm">
                    <thead className="bg-gray-100 dark:bg-gray-800"><tr><th className="whitespace-nowrap px-4 py-3 text-left">Participante</th>{questions.map((question) => <th key={question.id} className="min-w-48 px-4 py-3 text-left">{question.question}</th>)}</tr></thead>
                    <tbody>{results.length === 0 ? <tr><td colSpan={questions.length + 1} className="px-4 py-8 text-center text-gray-500">Aún no hay respuestas para esta encuesta.</td></tr> : results.map((result) => <tr key={result.client_id} className="border-t align-top"><td className="whitespace-nowrap px-4 py-3">{result.client_id}</td>{questions.map((question) => <td key={question.id} className="whitespace-pre-wrap px-4 py-3">{result[`answer_${question.id}`] ?? '-'}</td>)}</tr>)}</tbody>
                </table>
            </div>
        </div>
    </AppLayout>;
}