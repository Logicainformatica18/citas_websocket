import { useState } from 'react';
import axios from 'axios';
import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

type Option = { id: number; description: string; associate_detail_id?: number | null };
type Question = { id: number; title?: string | null; question: string; detail?: string | null; detail_3?: string | null; type: string; option?: string[]; correct?: string | null; requerid?: string | null; selection_id?: number | null; selection?: { description: string; state?: string; details?: Option[] } | null };
type Survey = { id: number; title: string; description?: string | null; detail?: string | null; state?: string | null; date_end?: string | null };

const typeLabels: Record<string, string> = { short_answer: 'Respuesta corta', number: 'Número', email: 'Correo electrónico', date: 'Fecha', file: 'Archivo', multiple_option: 'Varias opciones', selection: 'Selección' };

export default function PublicSurvey({ survey, questions }: { survey: Survey; questions: Question[] }) {
    const [started, setStarted] = useState(false);
    const [clientId, setClientId] = useState<number | null>(null);
    const [accessCode, setAccessCode] = useState('');
    const [current, setCurrent] = useState(0);
    const [answer, setAnswer] = useState('');
    const [file, setFile] = useState<File | null>(null);
    const [finished, setFinished] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const question = questions[current];

    const start = async () => {
        try {
            setBusy(true); setError('');
            const response = await axios.post(`/survey/${survey.id}/client`, { state: survey.state === 'private' ? 'private' : 'public', code: accessCode });
            setClientId(response.data.client_id); setStarted(true);
        } catch (exception: any) { setError(exception?.response?.data?.message || 'No se pudo iniciar la encuesta.'); } finally { setBusy(false); }
    };

    const saveAnswer = async () => {
        if (!question || !clientId) return;
        if (question.requerid === 'yes' && !answer && !file) { setError('Esta pregunta es obligatoria.'); return; }
        try {
            setBusy(true); setError('');
            const data = new FormData(); data.append('client_id', String(clientId)); data.append('survey_detail_id', String(question.id)); data.append('type', question.type);
            if (file) data.append('answer', file); else if (question.type === 'multiple_option') data.append('option', answer); else if (question.type === 'selection') data.append('selection_detail_id', answer); else data.append('answer', answer);
            await axios.post(`/survey/${survey.id}/answers`, data);
            setAnswer(''); setFile(null); if (current === questions.length - 1) setFinished(true); else setCurrent((value) => value + 1);
        } catch (exception: any) { setError(exception?.response?.data?.message || 'No se pudo guardar la respuesta.'); } finally { setBusy(false); }
    };

    if (finished) return <main className="min-h-screen bg-slate-100 px-4 py-12"><Head title={survey.title} /><section className="mx-auto max-w-2xl rounded-xl bg-white p-8 text-center shadow-sm"><h1 className="text-2xl font-bold">Gracias por completar la encuesta</h1><p className="mt-3 text-slate-600">Tus respuestas fueron guardadas correctamente.</p></section></main>;
    if (!started) return <main className="min-h-screen bg-slate-100 px-4 py-12"><Head title={survey.title} /><section className="mx-auto max-w-2xl rounded-xl bg-white p-8 shadow-sm"><h1 className="text-3xl font-bold text-slate-900">{survey.title}</h1><p className="mt-3 whitespace-pre-wrap text-slate-600">{survey.description || survey.detail}</p>{survey.state === 'private' && <div className="mt-6"><label className="text-sm font-medium">Código de acceso</label><Input className="mt-2" type="password" value={accessCode} onChange={(event) => setAccessCode(event.target.value)} /></div>} {error && <p className="mt-4 text-sm text-red-600">{error}</p>}<Button className="mt-6" onClick={start} disabled={busy}>{busy ? 'Iniciando...' : 'Iniciar encuesta'}</Button></section></main>;
    if (!question) return <main className="min-h-screen bg-slate-100 px-4 py-12"><section className="mx-auto max-w-2xl rounded-xl bg-white p-8 shadow-sm"><h1 className="text-2xl font-bold">No hay preguntas disponibles</h1></section></main>;

    return <main className="min-h-screen bg-slate-100 px-4 py-8"><Head title={survey.title} /><section className="mx-auto max-w-3xl"><div className="mb-5 flex items-center justify-between"><div><p className="text-sm text-slate-500">{survey.title}</p><h1 className="text-2xl font-bold">Pregunta {current + 1} de {questions.length}</h1></div><span className="rounded-full bg-blue-100 px-3 py-1 text-sm text-blue-700">{typeLabels[question.type]}</span></div><div className="h-2 overflow-hidden rounded-full bg-slate-200"><div className="h-full bg-blue-600 transition-all" style={{ width: `${((current + 1) / questions.length) * 100}%` }} /></div><article className="mt-6 rounded-xl bg-white p-6 shadow-sm"><h2 className="text-xl font-semibold">{question.title || 'Pregunta'}</h2>{question.detail && <p className="mt-2 text-slate-600">{question.detail}</p>}<h3 className="mt-6 text-lg font-medium">{question.question}{question.requerid === 'yes' && <span className="text-red-600"> *</span>}</h3>{question.detail_3 && <p className="mt-2 text-sm text-slate-500">{question.detail_3}</p>}<div className="mt-5">{question.type === 'short_answer' && <Textarea value={answer} onChange={(event) => setAnswer(event.target.value)} rows={4} />}{question.type === 'number' && <Input type="number" value={answer} onChange={(event) => setAnswer(event.target.value)} />}{question.type === 'email' && <Input type="email" value={answer} onChange={(event) => setAnswer(event.target.value)} />}{question.type === 'date' && <Input type="date" value={answer} onChange={(event) => setAnswer(event.target.value)} />}{question.type === 'file' && <Input type="file" onChange={(event) => setFile(event.target.files?.[0] || null)} />}{question.type === 'multiple_option' && <div className="space-y-3">{(question.option || []).map((option, index) => <label key={`${option}-${index}`} className="flex cursor-pointer items-center gap-3 rounded-lg border p-3 hover:bg-slate-50"><input type="radio" name="answer" value={option} checked={answer === option} onChange={(event) => setAnswer(event.target.value)} />{option}</label>)}</div>}{question.type === 'selection' && <select value={answer} onChange={(event) => setAnswer(event.target.value)} className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"><option value="">Selecciona una opción</option>{(question.selection?.details || []).map((option) => <option key={option.id} value={option.id}>{option.description}</option>)}</select>}</div>{error && <p className="mt-4 text-sm text-red-600">{error}</p>}<div className="mt-8 flex justify-end"><Button onClick={saveAnswer} disabled={busy}>{busy ? 'Guardando...' : current === questions.length - 1 ? 'Finalizar' : 'Siguiente'}</Button></div></article></section></main>;
}