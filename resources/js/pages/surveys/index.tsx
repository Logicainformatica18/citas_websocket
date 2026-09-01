import AppLayout from '@/layouts/app-layout';
import { Link, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';
import { toast } from 'sonner';
import {
    BarChart3,
    ChevronLeft,
    ChevronRight,
    Eye,
    ExternalLink,
    FileText,
    ListFilter,
    MoreHorizontal,
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
    // Opcionales: si el controlador los manda, se muestran. Si no, guion.
    answers_count?: number | null;
    participants_count?: number | null;
    created_bys?: {
        names?: string | null;
        firstname?: string | null;
        email?: string | null;
    } | null;
};

type Pagination<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page?: number;
    total?: number;
    from?: number | null;
    to?: number | null;
};

type Estado = 'programada' | 'en_curso' | 'finalizada';

const tabs = [
    { key: 'all', label: 'Todas' },
    { key: 'active', label: 'En curso' },
    { key: 'finished', label: 'Finalizada' },
] as const;

const estadoEtiqueta: Record<Estado, string> = {
    programada: 'Programada',
    en_curso: 'En curso',
    finalizada: 'Finalizada',
};

const estadoClase: Record<Estado, string> = {
    programada: 'bg-[#e8eefb] text-[#3457a8] dark:bg-blue-950/50 dark:text-blue-300',
    en_curso: 'bg-[#dff5ea] text-[#1d7c58] dark:bg-emerald-950/50 dark:text-emerald-300',
    finalizada: 'bg-[#eef0f3] text-[#4c5867] dark:bg-neutral-800 dark:text-neutral-300',
};

// El campo `state` de la base vale 'public' / 'private': es visibilidad, no
// ciclo de vida. El estado real sale de la ventana de fechas.
function estadoDe(survey: Survey): Estado {
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);

    const inicio = survey.date_start ? new Date(survey.date_start) : null;
    const fin = survey.date_end ? new Date(survey.date_end) : null;

    if (fin && fin < hoy) return 'finalizada';
    if (inicio && inicio > hoy) return 'programada';
    return 'en_curso';
}

function fecha(valor?: string | null) {
    if (!valor) return '—';
    return new Date(valor).toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function numero(valor?: number | null) {
    if (valor === null || valor === undefined) return '—';
    return valor.toLocaleString('es-PE');
}

function publicSurveyUrl(survey: Survey) {
    const slug = survey.url?.trim();
    const path = slug || String(survey.id);
    return `${window.location.origin}/survey/${path}`;
}

export default function Surveys() {
    const { surveys: initial } = usePage<{ surveys: Pagination<Survey> }>().props;

    const [surveys, setSurveys] = useState<Survey[]>(initial?.data ?? []);
    const [pagination, setPagination] = useState<Pagination<Survey> | undefined>(initial);
    const [edit, setEdit] = useState<Survey | null>(null);
    const [open, setOpen] = useState(false);
    const [selectedTab, setSelectedTab] = useState<'all' | 'active' | 'finished'>('all');
    const [busqueda, setBusqueda] = useState('');
    const [cargando, setCargando] = useState(false);
    const [eliminando, setEliminando] = useState<number | null>(null);

    // Menú de acciones: un solo abierto a la vez, anclado en coordenadas de
    // viewport porque el contenedor de la tabla tiene overflow-hidden y un
    // absolute quedaría recortado en las últimas filas.
    const [menu, setMenu] = useState<{ id: number; x: number; y: number; arriba: boolean } | null>(null);
    const menuRef = useRef<HTMLDivElement | null>(null);

    const cerrarMenu = useCallback(() => setMenu(null), []);

    useEffect(() => {
        if (!menu) return;

        const alClic = (event: globalThis.MouseEvent) => {
            const destino = event.target as Node;
            if (menuRef.current && !menuRef.current.contains(destino)) cerrarMenu();
        };

        const alTeclado = (event: globalThis.KeyboardEvent) => {
            if (event.key === 'Escape') cerrarMenu();
        };

        document.addEventListener('mousedown', alClic);
        document.addEventListener('keydown', alTeclado);
        window.addEventListener('resize', cerrarMenu);
        window.addEventListener('scroll', cerrarMenu, true);

        return () => {
            document.removeEventListener('mousedown', alClic);
            document.removeEventListener('keydown', alTeclado);
            window.removeEventListener('resize', cerrarMenu);
            window.removeEventListener('scroll', cerrarMenu, true);
        };
    }, [menu, cerrarMenu]);

    const alternarMenu = (event: React.MouseEvent<HTMLButtonElement>, id: number) => {
        if (menu?.id === id) return cerrarMenu();

        const rect = event.currentTarget.getBoundingClientRect();
        const alto = 240;
        const arriba = rect.bottom + alto > window.innerHeight;

        setMenu({
            id,
            x: rect.right,
            y: arriba ? rect.top - 6 : rect.bottom + 6,
            arriba,
        });
    };

    const filteredSurveys = useMemo(() => {
        const texto = busqueda.trim().toLowerCase();

        return surveys.filter((survey) => {
            const estado = estadoDe(survey);

            if (selectedTab === 'active' && estado === 'finalizada') return false;
            if (selectedTab === 'finished' && estado !== 'finalizada') return false;
            if (texto && !survey.title.toLowerCase().includes(texto)) return false;

            return true;
        });
    }, [selectedTab, surveys, busqueda]);

    const enCurso = useMemo(() => surveys.filter((s) => estadoDe(s) === 'en_curso').length, [surveys]);

    const refresh = useCallback(async (url = '/surveys/fetch') => {
        setCargando(true);
        cerrarMenu();

        try {
            const response = await axios.get(url);
            setSurveys(response.data.surveys.data);
            setPagination(response.data.surveys);
        } catch {
            toast.error('No se pudieron cargar las encuestas.');
        } finally {
            setCargando(false);
        }
    }, [cerrarMenu]);

    const irAPagina = (page: number) => {
        if (!pagination) return;
        if (page < 1 || page > pagination.last_page || page === pagination.current_page) return;
        refresh(`/surveys/fetch?page=${page}`);
    };

    const saved = (survey: Survey) => {
        setSurveys((current) =>
            current.some((item) => item.id === survey.id)
                ? current.map((item) => (item.id === survey.id ? survey : item))
                : [survey, ...current],
        );
    };

    const eliminar = async (survey: Survey) => {
        if (!confirm(`¿Eliminar la encuesta "${survey.title}"? Se borran también sus preguntas y respuestas.`)) return;

        setEliminando(survey.id);
        cerrarMenu();

        try {
            const response = await axios.delete(`/surveys/${survey.id}`);
            setSurveys((current) => current.filter((item) => item.id !== survey.id));
            toast.success(response.data?.message ?? 'Encuesta eliminada.');
        } catch (error) {
            const mensaje =
                axios.isAxiosError(error) && error.response?.data?.message
                    ? error.response.data.message
                    : 'No se pudo eliminar la encuesta.';
            toast.error(mensaje);
        } finally {
            setEliminando(null);
        }
    };

    const total = pagination?.total ?? surveys.length;
    const desde = pagination?.from ?? (surveys.length ? 1 : 0);
    const hasta = pagination?.to ?? surveys.length;

    const surveyDelMenu = menu ? surveys.find((s) => s.id === menu.id) ?? null : null;

    return (
        <AppLayout breadcrumbs={[{ title: 'Encuestas', href: '/surveys' }]}>
            <div className="w-full min-w-0 flex-1 bg-[#edf2f5] p-4 md:p-6 dark:bg-neutral-950">
                <div className="mb-4 flex min-w-0 flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div className="min-w-0">
                        <p className="text-[15px] text-[#6a788b] dark:text-neutral-400">
                            Organiza y monitorea tus formularios institucionales.
                        </p>
                        <h2 className="mt-2 text-[28px] font-black leading-none tracking-[-0.05em] text-[#202c36] md:text-[36px] dark:text-neutral-50">
                            Encuestas
                        </h2>
                        <p className="mt-3 text-[15px] text-[#67788c] dark:text-neutral-400">
                            {total} {total === 1 ? 'encuesta registrada' : 'encuestas registradas'} · {enCurso}{' '}
                            {enCurso === 1 ? 'en curso' : 'en curso'}
                        </p>
                    </div>

                    <button
                        onClick={() => {
                            setEdit(null);
                            setOpen(true);
                        }}
                        className="inline-flex items-center gap-2 rounded-xl bg-[#00ADEF] px-5 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-[#0093cc] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#00ADEF] focus-visible:ring-offset-2"
                    >
                        <Plus className="h-4 w-4" />
                        Nueva encuesta
                    </button>
                </div>

                <div className="min-w-0 rounded-[20px] border border-[#dfe6eb] bg-[#f7f9fb] p-4 shadow-[0_1px_0_rgba(15,23,42,0.02)] dark:border-neutral-800 dark:bg-neutral-900">
                    <div className="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div className="flex flex-wrap gap-2">
                            {tabs.map((tab) => (
                                <button
                                    key={tab.key}
                                    onClick={() => setSelectedTab(tab.key)}
                                    aria-pressed={selectedTab === tab.key}
                                    className={`border-b-2 px-3 pb-2 text-[15px] font-semibold transition ${
                                        selectedTab === tab.key
                                            ? 'border-[#00ADEF] text-[#0093cc] dark:text-[#48c7f5]'
                                            : 'border-transparent text-[#4f5d6d] hover:text-[#223248] dark:text-neutral-400 dark:hover:text-neutral-200'
                                    }`}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>

                        <div className="relative w-full max-w-md">
                            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#75839a]" />
                            <input
                                type="search"
                                value={busqueda}
                                onChange={(event) => setBusqueda(event.target.value)}
                                placeholder="Buscar por nombre..."
                                aria-label="Buscar encuestas por nombre"
                                className="w-full rounded-xl border border-[#dfe6eb] bg-white py-2.5 pl-9 pr-3 text-[15px] text-[#273548] outline-none placeholder:text-[#7c8aa0] focus:border-[#00ADEF] dark:border-neutral-800 dark:bg-neutral-950 dark:text-neutral-100"
                            />
                        </div>
                    </div>

                    <div className="min-w-0 overflow-x-auto rounded-xl border border-[#dfe6eb] bg-white dark:border-neutral-800 dark:bg-neutral-950">
                        <table className="min-w-full text-left text-[13px]">
                            <thead>
                                <tr className="bg-[#f0f3f6] text-[11px] font-semibold uppercase tracking-[0.12em] text-[#64768b] dark:bg-neutral-900 dark:text-neutral-400">
                                    <th scope="col" className="px-4 py-3">Encuesta</th>
                                    <th scope="col" className="px-4 py-3">Estado</th>
                                    <th scope="col" className="px-4 py-3">Actividad</th>
                                    <th scope="col" className="px-4 py-3">Periodo</th>
                                    <th scope="col" className="px-4 py-3">Responsable</th>
                                    <th scope="col" className="px-4 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className={cargando ? 'opacity-50 transition-opacity' : 'transition-opacity'}>
                                {filteredSurveys.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-10 text-center text-[#6d7c8b] dark:text-neutral-400">
                                            {busqueda || selectedTab !== 'all'
                                                ? 'No hay encuestas para este filtro.'
                                                : 'Todavía no hay encuestas registradas.'}
                                        </td>
                                    </tr>
                                ) : (
                                    filteredSurveys.map((survey) => {
                                        const estado = estadoDe(survey);
                                        const creator = survey.created_bys;
                                        const responsable = creator
                                            ? `${creator.names ?? ''} ${creator.firstname ?? ''}`.trim() || '—'
                                            : '—';

                                        return (
                                            <tr
                                                key={survey.id}
                                                className={`border-t border-[#edf1f4] align-middle transition dark:border-neutral-800 ${
                                                    eliminando === survey.id ? 'opacity-40' : 'hover:bg-[#f7f9fb] dark:hover:bg-neutral-900'
                                                }`}
                                            >
                                                <td className="px-4 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#daf3fb] text-[#00ADEF] dark:bg-sky-950/60">
                                                            <ListFilter className="h-4 w-4" />
                                                        </div>
                                                        <div className="min-w-0">
                                                            <div className="truncate text-[16px] font-bold text-[#223248] dark:text-neutral-100">
                                                                {survey.title}
                                                            </div>
                                                            <div className="mt-1 text-[11px] text-[#6d7c8b] dark:text-neutral-500">
                                                                ID {survey.id}
                                                                {survey.type ? ` · ${survey.type}` : ''}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>

                                                <td className="px-4 py-4">
                                                    <span
                                                        className={`inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ${estadoClase[estado]}`}
                                                    >
                                                        {estadoEtiqueta[estado]}
                                                    </span>
                                                </td>

                                                <td className="px-4 py-4 text-[#46586d] dark:text-neutral-300">
                                                    <div className="text-[13px] font-medium">
                                                        {numero(survey.answers_count)} respuestas
                                                    </div>
                                                    <div className="mt-1 text-[11px] text-[#6d7c8b] dark:text-neutral-500">
                                                        {numero(survey.participants_count)} participantes
                                                    </div>
                                                </td>

                                                <td className="px-4 py-4 text-[#46586d] dark:text-neutral-300">
                                                    <div className="text-[12px] font-medium">{fecha(survey.date_start)}</div>
                                                    <div className="mt-1 text-[11px] text-[#6d7c8b] dark:text-neutral-500">
                                                        hasta {fecha(survey.date_end)}
                                                    </div>
                                                </td>

                                                <td className="px-4 py-4 text-[#46586d] dark:text-neutral-300">
                                                    <div className="text-[12px] font-medium">{responsable}</div>
                                                    <div className="mt-1 text-[11px] text-[#6d7c8b] dark:text-neutral-500">
                                                        {creator?.email || 'Sin email'}
                                                    </div>
                                                </td>

                                                <td className="px-4 py-4">
                                                    <div className="flex justify-end">
                                                        <button
                                                            type="button"
                                                            onClick={(event) => alternarMenu(event, survey.id)}
                                                            aria-haspopup="menu"
                                                            aria-expanded={menu?.id === survey.id}
                                                            aria-label={`Acciones de ${survey.title}`}
                                                            disabled={eliminando === survey.id}
                                                            className={`inline-flex h-9 w-9 items-center justify-center rounded-lg border transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#00ADEF] focus-visible:ring-offset-1 ${
                                                                menu?.id === survey.id
                                                                    ? 'border-[#00ADEF] bg-[#00ADEF] text-white'
                                                                    : 'border-[#cfe9f6] bg-[#f0faff] text-[#0093cc] hover:border-[#00ADEF] hover:bg-[#daf3fb] dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-300'
                                                            }`}
                                                        >
                                                            <MoreHorizontal className="h-4 w-4" />
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

                    <div className="mt-4 flex flex-col items-center justify-between gap-3 rounded-xl bg-[#f0f3f6] px-4 py-3 sm:flex-row dark:bg-neutral-900">
                        <span className="text-[14px] text-[#5d6d7e] dark:text-neutral-400">
                            Mostrando {desde}–{hasta} de {total} {total === 1 ? 'encuesta' : 'encuestas'}
                            {(busqueda || selectedTab !== 'all') && filteredSurveys.length !== surveys.length && (
                                <span className="text-[#7c8aa0] dark:text-neutral-500">
                                    {' '}
                                    · {filteredSurveys.length} en pantalla tras filtrar
                                </span>
                            )}
                        </span>

                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => irAPagina((pagination?.current_page ?? 1) - 1)}
                                disabled={cargando || (pagination?.current_page ?? 1) <= 1}
                                aria-label="Página anterior"
                                className="flex h-8 w-8 items-center justify-center rounded-lg border border-[#dfe6eb] bg-white text-[#697b8b] transition hover:bg-[#f5f7f9] disabled:cursor-not-allowed disabled:opacity-40 dark:border-neutral-800 dark:bg-neutral-950 dark:text-neutral-400"
                            >
                                <ChevronLeft className="h-4 w-4" />
                            </button>

                            <span className="flex h-8 min-w-8 items-center justify-center rounded-lg bg-[#00ADEF] px-2 text-sm font-semibold text-white">
                                {pagination?.current_page ?? 1}
                            </span>

                            <span className="text-[13px] text-[#7c8aa0] dark:text-neutral-500">
                                de {pagination?.last_page ?? 1}
                            </span>

                            <button
                                type="button"
                                onClick={() => irAPagina((pagination?.current_page ?? 1) + 1)}
                                disabled={cargando || (pagination?.current_page ?? 1) >= (pagination?.last_page ?? 1)}
                                aria-label="Página siguiente"
                                className="flex h-8 w-8 items-center justify-center rounded-lg border border-[#dfe6eb] bg-white text-[#697b8b] transition hover:bg-[#f5f7f9] disabled:cursor-not-allowed disabled:opacity-40 dark:border-neutral-800 dark:bg-neutral-950 dark:text-neutral-400"
                            >
                                <ChevronRight className="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {/* Menú fijo al viewport: dentro de la tabla lo recortaría el
                overflow del contenedor en las últimas filas. */}
            {menu && surveyDelMenu && (
                <div
                    ref={menuRef}
                    role="menu"
                    aria-label={`Acciones de ${surveyDelMenu.title}`}
                    style={{ left: menu.x, top: menu.y }}
                    className={`fixed z-50 w-52 -translate-x-full rounded-lg border border-[#dfe6eb] bg-white p-1 shadow-lg dark:border-neutral-800 dark:bg-neutral-900 ${
                        menu.arriba ? '-translate-y-full' : ''
                    }`}
                >
                    <Link
                        href={`/surveys/${surveyDelMenu.id}/dashboard`}
                        role="menuitem"
                        onClick={cerrarMenu}
                        className="flex items-center gap-2 rounded-md px-3 py-2 text-[13px] text-[#516077] transition hover:bg-blue-50 hover:text-blue-600 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    >
                        <BarChart3 className="h-4 w-4" />
                        <span>Dashboard</span>
                    </Link>

                    <Link
                        href={`/surveys/${surveyDelMenu.id}/report`}
                        role="menuitem"
                        onClick={cerrarMenu}
                        className="flex items-center gap-2 rounded-md px-3 py-2 text-[13px] text-[#516077] transition hover:bg-orange-50 hover:text-orange-600 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    >
                        <FileText className="h-4 w-4" />
                        <span>Reporte</span>
                    </Link>

                    <Link
                        href={`/surveys/${surveyDelMenu.id}/questions`}
                        role="menuitem"
                        onClick={cerrarMenu}
                        className="flex items-center gap-2 rounded-md px-3 py-2 text-[13px] text-[#516077] transition hover:bg-emerald-50 hover:text-emerald-600 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    >
                        <Eye className="h-4 w-4" />
                        <span>Ver preguntas</span>
                    </Link>

                    <a
                        href={publicSurveyUrl(surveyDelMenu)}
                        target="_blank"
                        rel="noopener noreferrer"
                        role="menuitem"
                        onClick={cerrarMenu}
                        className="flex items-center gap-2 rounded-md px-3 py-2 text-[13px] text-[#516077] transition hover:bg-indigo-50 hover:text-indigo-600 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    >
                        <ExternalLink className="h-4 w-4" />
                        <span>Ver encuesta</span>
                    </a>

                    <button
                        type="button"
                        role="menuitem"
                        onClick={() => {
                            setEdit(surveyDelMenu);
                            setOpen(true);
                            cerrarMenu();
                        }}
                        className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-[13px] text-[#516077] transition hover:bg-amber-50 hover:text-amber-600 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    >
                        <Paintbrush className="h-4 w-4" />
                        <span>Editar</span>
                    </button>

                    <button
                        type="button"
                        role="menuitem"
                        onClick={() => eliminar(surveyDelMenu)}
                        className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-[13px] text-red-600 transition hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/40"
                    >
                        <Trash2 className="h-4 w-4" />
                        <span>Eliminar</span>
                    </button>
                </div>
            )}

            <SurveyModal open={open} surveyToEdit={edit} onClose={() => setOpen(false)} onSaved={saved} />
        </AppLayout>
    );
}
