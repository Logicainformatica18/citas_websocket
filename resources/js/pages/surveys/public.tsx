import { Head } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useMemo, useState } from 'react';
import { Check, ChevronLeft, ChevronRight, ClipboardList, FileUp, LockKeyhole, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

type Option = { id: number; description: string; associate_detail_id?: number | null };
type Question = {
    id: number;
    title?: string | null;
    question: string;
    detail?: string | null;
    detail_2?: string | null;
    detail_3?: string | null;
    type: string;
    option?: string[];
    requerid?: string | null;
    selection_id?: number | null;
    selection?: { description: string; state?: string; details?: Option[] } | null;
};
type Survey = { id: number; title: string; description?: string | null; detail?: string | null; state?: string | null; password?: string | null; date_start?: string | null; date_end?: string | null };

/** Una respuesta ya guardada, tal como la devuelve el endpoint de progreso. */
type Respuesta = {
    answer?: string | null;
    option?: string[] | null;
    selection_detail_id?: number | null;
    /**
     * Valor exacto que este navegador mandó en esta sesión. Existe solo para
     * las respuestas guardadas acá, no para las que llegan del servidor.
     *
     * Sirve para no tener que des-normalizar lo que ya sabemos: el servidor
     * devuelve las de opción múltiple como "4-De acuerdo" y hay que volver a
     * mapearlas contra la lista de opciones. Si lo acabamos de mandar, nos
     * ahorramos ese ida y vuelta y evitamos cualquier ambigüedad.
     */
    crudo?: string;
};

type Step = 'cargando' | 'bloqueado' | 'welcome' | 'instructions' | 'questions' | 'finished';

const typeLabels: Record<string, string> = {
    short_answer: 'Respuesta corta', number: 'Número', email: 'Correo electrónico', date: 'Fecha', file: 'Archivo', multiple_option: 'Varias opciones', selection: 'Selección',
};
const scaleLabels = ['Totalmente en desacuerdo', 'En desacuerdo', 'Neutral', 'De acuerdo', 'Totalmente de acuerdo'];

/*
 * MARCAS EN EL NAVEGADOR
 *
 * Son el espejo de las dos cookies que emite el servidor (ver
 * App\Helpers\MarcaEncuesta). Se guardan las dos cosas en los dos lados a
 * propósito: limpiar cookies no toca localStorage y limpiar localStorage no
 * toca las cookies, así que hace falta borrar ambos para volver a entrar.
 *
 * Todo va envuelto en try/catch porque en modo incógnito con almacenamiento
 * bloqueado el solo hecho de leer localStorage tira una excepción, y no vale
 * la pena romper la encuesta entera por una marca de conveniencia.
 *
 * Lo que NO se hace acá: confiar en estas marcas para permitir responder. El
 * front solo las usa para no dibujar el asistente de más. Quien decide es el
 * servidor, que valida su propia cookie firmada en show(), en store() y en la
 * creación del client_id.
 */
const claveOk = (surveyId: number) => `encuesta_${surveyId}_ok`;
const claveClient = (surveyId: number) => `encuesta_${surveyId}_client`;

const leerLocal = (clave: string): string | null => {
    try { return window.localStorage.getItem(clave); } catch { return null; }
};
const escribirLocal = (clave: string, valor: string) => {
    try { window.localStorage.setItem(clave, valor); } catch { /* almacenamiento bloqueado */ }
};
const borrarLocal = (clave: string) => {
    try { window.localStorage.removeItem(clave); } catch { /* almacenamiento bloqueado */ }
};

/**
 * ¿Está la cookie de bloqueo en este navegador?
 *
 * Solo mira que el nombre exista. El valor es el texto cifrado por
 * EncryptCookies, no se puede (ni hace falta) leer desde el JS.
 */
const tieneCookieOk = (surveyId: number): boolean => {
    try {
        const nombre = `${claveOk(surveyId)}=`;
        return document.cookie.split(';').some((parte) => parte.trim().startsWith(nombre));
    } catch { return false; }
};

/**
 * Reconstruye el valor que hay que dejar en el formulario para una respuesta
 * ya guardada, para que al retomar o al volver atrás el encuestado vea lo que
 * había puesto en vez de un campo en blanco.
 *
 * El caso delicado es `multiple_option`: en la base la respuesta vive
 * normalizada como "4-De acuerdo", pero el radio del formulario compara
 * contra el texto pelado de question.option. Se resuelve POR POSICIÓN y no
 * cortando el string por el primer guion, porque una opción que se llame
 * "2-3 años" quedaría mutilada en "3 años" y no coincidiría con ninguna.
 */
const valorPrecargado = (question: Question, guardada?: Respuesta): string => {
    if (!guardada) return '';
    if (guardada.crudo !== undefined) return guardada.crudo;

    if (question.type === 'selection') {
        const id = guardada.selection_detail_id ?? guardada.answer;
        return id === null || id === undefined ? '' : String(id);
    }

    // Los archivos no se pueden precargar: el input file no acepta un valor
    // por JS. Se muestra vacío y, si la pregunta es obligatoria, hay que
    // volver a subirlo.
    if (question.type === 'file') return '';

    if (question.type === 'multiple_option') {
        const opciones = question.option || [];
        // Con evaluate='yes' el `answer` guarda el puntaje (2 o 0), no la
        // opción: la opción elegida vive siempre en `option[0]`.
        const normalizado = guardada.option?.[0] ?? guardada.answer ?? '';
        const partes = /^(\d+)-([\s\S]*)$/.exec(normalizado);
        if (partes) {
            const porPosicion = opciones[Number(partes[1]) - 1];
            if (porPosicion !== undefined) return porPosicion;
        }
        return opciones.includes(normalizado) ? normalizado : '';
    }

    return guardada.answer ?? '';
};

export default function PublicSurvey({ survey, questions, yaRespondio = false }: { survey: Survey; questions: Question[]; yaRespondio?: boolean }) {
    // Se arranca en 'cargando' y no en 'welcome' a propósito: hay que
    // preguntar por las marcas antes de dibujar nada, o el encuestado que ya
    // respondió vería la portada de la encuesta un instante antes de que
    // aparezca el bloqueo.
    const [step, setStep] = useState<Step>(yaRespondio ? 'bloqueado' : 'cargando');
    const [clientId, setClientId] = useState<number | null>(null);
    const [accessCode, setAccessCode] = useState('');
    const [current, setCurrent] = useState(0);
    const [answer, setAnswer] = useState('');
    const [file, setFile] = useState<File | null>(null);
    const [respuestas, setRespuestas] = useState<Record<string, Respuesta>>({});
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const question = questions[current];
    const progress = questions.length ? Math.round(((current + 1) / questions.length) * 100) : 0;
    const isPrivate = survey.state === 'private';
    const selectionOptions = useMemo(() => question?.selection?.details || [], [question]);

    const bloquear = () => { escribirLocal(claveOk(survey.id), '1'); setStep('bloqueado'); };

    /*
     * Decidir en qué pantalla se entra. Tres desenlaces:
     *
     *   marca de completado           -> bloqueo
     *   client_id sin completar       -> retomar en la primera sin responder
     *   sin nada                      -> portada
     *
     * Se consulta el progreso contra el servidor en vez de confiar en lo que
     * diga localStorage: la marca local puede haber quedado de una encuesta
     * que después se borró, o apuntar a un client_id que ya no existe.
     */
    useEffect(() => {
        let vigente = true;

        const resolver = async () => {
            if (yaRespondio) { escribirLocal(claveOk(survey.id), '1'); return; }

            if (leerLocal(claveOk(survey.id)) === '1' || tieneCookieOk(survey.id)) { bloquear(); return; }

            const guardado = leerLocal(claveClient(survey.id));
            if (!guardado || !/^\d+$/.test(guardado)) { setStep('welcome'); return; }

            try {
                const { data } = await axios.get(`/survey/${survey.id}/answers/${guardado}`);
                if (!vigente) return;

                if (data?.completado) { bloquear(); return; }

                // El servidor no reconoce esa sesión (cookies borradas, otro
                // navegador, client_id viejo). Se limpia la marca local y se
                // empieza de cero: es lo único que se puede hacer, y además
                // deja de reintentar contra un id que no va a resolver nunca.
                if (!data?.existe) { borrarLocal(claveClient(survey.id)); setStep('welcome'); return; }

                const previas: Record<string, Respuesta> = data.respuestas || {};
                setRespuestas(previas);
                setClientId(Number(guardado));

                if (!questions.length) { setStep('welcome'); return; }

                // Primera pregunta que todavía no tiene fila. Si están todas,
                // se cae en la última: quedaron obligatorias vacías (una fila
                // con answer null cuenta como visitada pero no como completa)
                // y el servidor las va a volver a exigir al guardar.
                const pendiente = questions.findIndex((q) => !(String(q.id) in previas));
                setCurrent(pendiente === -1 ? questions.length - 1 : pendiente);
                setStep('questions');
            } catch {
                if (vigente) setStep('welcome');
            }
        };

        resolver();
        return () => { vigente = false; };
        // Solo al montar: es la decisión de entrada.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Al cambiar de pregunta, se repone lo que el encuestado ya había
    // contestado. Sin esto, el botón "Atrás" mostraba el campo vacío y daba
    // la impresión de que la respuesta se había perdido.
    useEffect(() => {
        if (step !== 'questions' || !question) return;
        setAnswer(valorPrecargado(question, respuestas[String(question.id)]));
        setFile(null);
        setError('');
        // `respuestas` queda fuera de las dependencias a propósito: se quiere
        // reponer al MOVERSE de pregunta, no cada vez que el mapa cambia (lo
        // cual pisaría lo que el encuestado está tipeando justo después de
        // guardar).
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [step, question?.id]);

    const start = async () => {
        try {
            setBusy(true); setError('');
            const response = await axios.post(`/survey/${survey.id}/client`, { state: isPrivate ? 'private' : 'public', code: accessCode });
            const nuevo = response.data.client_id;
            setClientId(nuevo);
            escribirLocal(claveClient(survey.id), String(nuevo));
            setStep('instructions');
        } catch (exception: any) {
            // 403 es el bloqueo por dispositivo: no es un error para mostrar
            // en rojo arriba del botón, es el fin del recorrido.
            if (exception?.response?.status === 403) { bloquear(); return; }
            setError(exception?.response?.data?.message || 'No se pudo iniciar la encuesta.');
        } finally { setBusy(false); }
    };

    const beginQuestions = () => {
        if (!questions.length) { setError('Esta encuesta todavía no tiene preguntas.'); return; }
        setStep('questions'); setCurrent(0); setError('');
    };

    const saveAnswer = async () => {
        if (!question || !clientId) return;
        if (question.requerid === 'yes' && !answer && !file) { setError('Esta pregunta es obligatoria.'); return; }
        try {
            setBusy(true); setError('');
            const data = new FormData();
            data.append('client_id', String(clientId)); data.append('survey_detail_id', String(question.id)); data.append('type', question.type);
            if (file) data.append('answer', file);
            else if (question.type === 'multiple_option') data.append('option', answer);
            else if (question.type === 'selection') data.append('selection_detail_id', answer);
            else data.append('answer', answer);
            const respuesta = await axios.post(`/survey/${survey.id}/answers`, data);

            // Se anota localmente lo que se acaba de mandar, para que "Atrás"
            // lo muestre sin tener que volver a pedir el progreso al servidor.
            setRespuestas((previas) => ({ ...previas, [String(question.id)]: { crudo: file ? '' : answer } }));

            // El servidor avisa cuando quedaron cubiertas todas las
            // obligatorias, y en esa misma respuesta manda la cookie de
            // bloqueo. Acá se deja la marca gemela en localStorage. No se
            // cambia de pantalla: si todavía quedan preguntas opcionales por
            // delante, el encuestado las sigue contestando con el mismo
            // client_id.
            if (respuesta.data?.completado) escribirLocal(claveOk(survey.id), '1');

            setFile(null); setError('');
            if (current === questions.length - 1) { setAnswer(''); setStep('finished'); } else setCurrent((value) => value + 1);
        } catch (exception: any) {
            if (exception?.response?.status === 403) { bloquear(); return; }
            setError(exception?.response?.data?.message || 'No se pudo guardar la respuesta.');
        } finally { setBusy(false); }
    };

    const content = step === 'cargando' ? <section className="grid min-h-[420px] place-items-center rounded-[24px] border border-[#e7e9ee] bg-white p-8 text-center shadow-[0_20px_60px_rgba(20,24,33,.08)]"><div><div className="mx-auto mb-5 h-10 w-10 animate-spin rounded-full border-[3px] border-[#eceef2] border-t-[#ff5a36]" /><p className="text-sm font-semibold text-[#6f737b]">Preparando la encuesta...</p></div></section>
    : step === 'bloqueado' ? <section className="grid min-h-[500px] place-items-center rounded-[24px] border border-[#e7e9ee] bg-white p-8 text-center shadow-[0_20px_60px_rgba(20,24,33,.08)]"><div><div className="mx-auto mb-5 grid h-20 w-20 place-items-center rounded-full bg-[#eaf7f2] text-[#1f8a63]"><ShieldCheck size={38} /></div><div className="mb-4 text-xs font-extrabold uppercase tracking-[.08em] text-[#ff5a36]">Participación registrada</div><h1 className="text-3xl font-black tracking-[-.03em] sm:text-5xl">Ya registramos tu respuesta</h1><p className="mx-auto mt-5 max-w-2xl text-lg leading-7 text-[#454951]">Gracias por tomarte el tiempo de compartir tu experiencia. Esta encuesta se responde una sola vez y desde este dispositivo ya quedó completada.</p></div></section>
    : step === 'welcome' ? <section className="rounded-[24px] border border-[#e7e9ee] bg-white p-7 shadow-[0_20px_60px_rgba(20,24,33,.08)] sm:p-12">
        <div className="mb-5 inline-flex items-center gap-2 text-xs font-extrabold uppercase tracking-[.08em] text-[#ff5a36]"><ClipboardList size={17} /> Encuesta ISIL</div>
        <h1 className="max-w-4xl text-4xl font-black leading-[.98] tracking-[-.04em] sm:text-6xl">Tu experiencia importa.</h1>
        <p className="mt-5 max-w-3xl text-base leading-7 text-[#454951] sm:text-lg">{survey.description || survey.detail || 'Queremos conocer tu experiencia. Tus respuestas nos ayudarán a reconocer fortalezas y detectar oportunidades de mejora.'}</p>
        <div className="my-8 grid gap-4 rounded-[18px] border border-[#ffd7ca] bg-[#fff1ec] p-5 sm:grid-cols-[auto_1fr]"><div className="grid h-10 w-10 place-items-center rounded-xl bg-white text-[#ff5a36]"><LockKeyhole size={20} /></div><div><strong className="block">Tu participación es confidencial</strong><p className="mt-1 text-sm leading-6 text-[#5c4a45]">No solicitaremos tu nombre. Las respuestas se registran de manera agrupada para conocer mejor la experiencia de los participantes.</p></div></div>
        {isPrivate && <div className="mb-6 max-w-md"><label className="text-sm font-bold">Código de acceso</label><Input className="mt-2 h-11" type="password" value={accessCode} onChange={(event) => setAccessCode(event.target.value)} placeholder="Ingresa el código de la encuesta" /></div>}
        {error && <p className="mb-4 text-sm font-medium text-red-600">{error}</p>}
        <Button onClick={start} disabled={busy} className="h-12 rounded-xl bg-[#191b1f] px-6 font-extrabold hover:bg-[#34363b]">{busy ? 'Verificando...' : 'Continuar'}</Button>
    </section> : step === 'instructions' ? <section className="rounded-[24px] border border-[#e7e9ee] bg-white p-7 shadow-[0_20px_60px_rgba(20,24,33,.08)] sm:p-12">
        <div className="mb-5 text-xs font-extrabold uppercase tracking-[.08em] text-[#ff5a36]">Antes de comenzar</div><h1 className="text-3xl font-black tracking-[-.03em] sm:text-5xl">¿Cómo responder?</h1><p className="mt-4 max-w-3xl text-base leading-7 text-[#454951]">Responde pensando en tu experiencia habitual. Lee cada pregunta y selecciona la opción que mejor represente tu experiencia.</p>
        <div className="my-8 grid gap-3 sm:grid-cols-5">{scaleLabels.map((label, index) => <div key={label} className="rounded-[18px] border border-[#e7e9ee] p-4 text-center"><div className="mx-auto mb-3 grid h-11 w-11 place-items-center rounded-full bg-[#f1f3f6] text-lg font-black">{index + 1}</div><span className="text-xs leading-4 text-[#6f737b]">{label}</span></div>)}</div>
        <div className="mb-8 rounded-[14px] bg-[#f1f3f6] p-4 text-sm leading-6 text-[#4c5058]">No hay respuestas correctas o incorrectas. Nos interesa conocer tu experiencia real.</div>{error && <p className="mb-4 text-sm text-red-600">{error}</p>}<Button onClick={beginQuestions} className="h-12 rounded-xl bg-[#191b1f] px-6 font-extrabold hover:bg-[#34363b]">Comenzar preguntas <ChevronRight size={18} /></Button>
    </section> : step === 'finished' ? <section className="grid min-h-[500px] place-items-center rounded-[24px] border border-[#e7e9ee] bg-white p-8 text-center shadow-[0_20px_60px_rgba(20,24,33,.08)]"><div><div className="mx-auto mb-5 grid h-20 w-20 place-items-center rounded-full bg-[#eaf7f2] text-[#1f8a63]"><Check size={38} /></div><div className="mb-4 text-xs font-extrabold uppercase tracking-[.08em] text-[#ff5a36]">Encuesta completada</div><h1 className="text-3xl font-black tracking-[-.03em] sm:text-5xl">¡Gracias por compartir tu experiencia!</h1><p className="mx-auto mt-5 max-w-2xl text-lg leading-7 text-[#454951]">Tus respuestas fueron guardadas correctamente y nos ayudarán a entender mejor qué estamos haciendo bien y dónde podemos mejorar.</p></div></section> : <section className="rounded-[24px] border border-[#e7e9ee] bg-white p-6 shadow-[0_20px_60px_rgba(20,24,33,.08)] sm:p-10">
        <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><div className="mb-2 text-xs font-extrabold uppercase tracking-[.08em] text-[#ff5a36]">{typeLabels[question.type] || 'Pregunta'}</div><h1 className="text-2xl font-black tracking-[-.03em] sm:text-4xl">Pregunta {current + 1} de {questions.length}</h1></div><span className="rounded-full bg-[#fff1ec] px-3 py-2 text-xs font-extrabold text-[#ff5a36]">{progress}% completado</span></div>
        <div className="mb-8 h-2 overflow-hidden rounded-full bg-[#eceef2]"><div className="h-full rounded-full bg-[#ff5a36] transition-all" style={{ width: `${progress}%` }} /></div><article className="border-t border-[#e7e9ee] pt-7"><h2 className="text-xl font-bold sm:text-2xl">{question.title || 'Pregunta'}</h2>{question.detail && <p className="mt-3 text-sm leading-6 text-[#6f737b]">{question.detail}</p>}<h3 className="mt-6 text-lg font-semibold leading-7">{question.question}{question.requerid === 'yes' && <span className="text-red-600"> *</span>}</h3>{question.detail_2 && <p className="mt-3 text-sm leading-6 text-[#6f737b]">{question.detail_2}</p>}{question.detail_3 && <p className="mt-2 text-sm leading-6 text-[#6f737b]">{question.detail_3}</p>}
        <div className="mt-7">{question.type === 'short_answer' && <Textarea value={answer} onChange={(event) => setAnswer(event.target.value)} rows={5} placeholder="Escribe tu respuesta..." />}{question.type === 'number' && <Input type="number" value={answer} onChange={(event) => setAnswer(event.target.value)} placeholder="Ingresa un número" />}{question.type === 'email' && <Input type="email" value={answer} onChange={(event) => setAnswer(event.target.value)} placeholder="correo@ejemplo.com" />}{question.type === 'date' && <Input type="date" value={answer} onChange={(event) => setAnswer(event.target.value)} />}{question.type === 'file' && <label className="flex cursor-pointer items-center gap-3 rounded-xl border border-dashed border-[#cfd3d9] p-5 text-sm text-[#6f737b]"><FileUp size={20} className="text-[#ff5a36]" />{file ? file.name : 'Selecciona un archivo'}<input type="file" className="hidden" onChange={(event) => setFile(event.target.files?.[0] || null)} /></label>}{question.type === 'multiple_option' && <div className="grid gap-3">{(question.option || []).map((option, index) => <label key={`${option}-${index}`} className={`flex cursor-pointer items-center gap-3 rounded-xl border p-4 transition ${answer === option ? 'border-[#ff5a36] bg-[#fff1ec]' : 'border-[#e7e9ee] hover:border-[#cfd3d9]'}`}><input type="radio" name={`question-${question.id}`} value={option} checked={answer === option} onChange={(event) => setAnswer(event.target.value)} />{option}</label>)}</div>}{question.type === 'selection' && <div className="grid gap-3">{selectionOptions.map((option) => <label key={option.id} className={`flex cursor-pointer items-center gap-3 rounded-xl border p-4 transition ${answer === String(option.id) ? 'border-[#ff5a36] bg-[#fff1ec]' : 'border-[#e7e9ee] hover:border-[#cfd3d9]'}`}><input type="radio" name={`selection-${question.id}`} value={option.id} checked={answer === String(option.id)} onChange={(event) => setAnswer(event.target.value)} />{option.description}</label>)}</div>}</div></article>
        {error && <p className="mt-5 text-sm font-medium text-red-600">{error}</p>}<div className="mt-8 flex justify-between gap-3"><Button variant="outline" onClick={() => setCurrent((value) => Math.max(0, value - 1))} disabled={current === 0 || busy} className="h-11 rounded-xl"><ChevronLeft size={17} /> Atrás</Button><Button onClick={saveAnswer} disabled={busy} className="h-11 rounded-xl bg-[#191b1f] px-5 font-extrabold hover:bg-[#34363b]">{busy ? 'Guardando...' : current === questions.length - 1 ? 'Finalizar' : 'Siguiente'} <ChevronRight size={17} /></Button></div>
    </section>;

    return <div className="min-h-screen w-full overflow-x-hidden bg-[radial-gradient(circle_at_85%_10%,rgba(255,90,54,.08),transparent_22%),#f6f7f9] text-[#17181b]"><Head title={survey.title} /><header className="sticky top-0 z-10 flex h-[76px] w-full items-center justify-between border-b border-black/5 bg-[#f6f7f9]/90 px-5 backdrop-blur sm:px-9"><div className="flex min-w-0 items-center gap-3 font-extrabold"><div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#ff5a36] text-sm text-white">ISIL</div><span className="truncate">Encuesta de Experiencia del Colaborador</span></div><div className="ml-4 flex shrink-0 items-center gap-2 rounded-full border border-[#e7e9ee] bg-white px-3 py-2 text-xs font-semibold text-[#6f737b]"><LockKeyhole size={14} /> <span className="hidden sm:inline">Anónima y confidencial</span></div></header><main className="mx-auto w-full max-w-[1140px] px-3 py-5 pb-24 sm:px-5 sm:py-10">{content}</main>{(step === 'questions') && <footer className="fixed bottom-0 left-0 right-0 z-20 w-full border-t border-[#e7e9ee] bg-white/95 backdrop-blur"><div className="mx-auto flex h-[78px] w-full max-w-[1140px] items-center gap-4 px-3 sm:px-5"><div className="flex-1"><div className="mb-2 flex justify-between text-xs text-[#6f737b]"><span className="truncate">{survey.title}</span><span>{progress}%</span></div><div className="h-2 overflow-hidden rounded-full bg-[#eceef2]"><div className="h-full rounded-full bg-[#ff5a36]" style={{ width: `${progress}%` }} /></div></div><span className="hidden text-xs text-[#6f737b] sm:inline">{current + 1} / {questions.length}</span></div></footer>}</div>;
}
