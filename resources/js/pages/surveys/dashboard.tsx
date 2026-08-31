import AppLayout from '@/layouts/app-layout';
import { Head, usePage } from '@inertiajs/react';
import { FileSpreadsheet } from 'lucide-react';
import Dimensiones from './dashboard/dimensiones';
import Destacados from './dashboard/destacados';
import FiltrosDemograficos from './dashboard/filtros-demograficos';
import RespuestasAbiertas from './dashboard/respuestas-abiertas';
import TarjetasEjecutivas from './dashboard/tarjetas-ejecutivas';
import { NotaAnonimato, Suprimido } from './dashboard/confidencialidad';
import type { DashboardProps } from './dashboard/tipos';

/**
 * Dashboard de resultados de una encuesta.
 *
 * Esta página solo orquesta: cada sección vive en su propio archivo dentro
 * de ./dashboard/. Los datos llegan ya agregados desde
 * SurveyDashboardController — acá no se calcula ninguna métrica, ni se
 * recorre ninguna respuesta.
 *
 * ------------------------------------------------------------------
 * LO QUE NO SE MUESTRA, Y POR QUÉ
 * ------------------------------------------------------------------
 *
 * · No hay porcentaje de participación. Se muestra la cantidad de
 *   encuestas terminadas y nada más, porque no existe en ninguna parte el
 *   dato del universo convocado. Un "48 de 63 (76%)" sería un número
 *   inventado con apariencia de dato.
 *
 * · Los filtros demográficos se dibujan vacíos y deshabilitados. La tabla
 *   `clients` no guarda gerencia, área, jerarquía ni modalidad.
 *
 * · Cuando un corte tiene menos de `minimo` participantes, los números NO
 *   llegan al navegador: el servidor los manda en null. Esta página solo
 *   dibuja la explicación.
 */
export default function SurveyDashboard() {
    const {
        survey,
        participantes,
        minimo,
        suprimido,
        descartadas,
        resumen,
        dimensiones,
        preguntas,
        abiertas,
    } = usePage<DashboardProps>().props;

    const breadcrumbs = [
        { title: 'Encuestas', href: '/surveys' },
        { title: 'Dashboard', href: `/surveys/${survey.id}/dashboard` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Dashboard · ${survey.title}`} />

            <div className="space-y-5 p-5 sm:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                        <h1 className="text-xl font-bold text-slate-900 dark:text-slate-50 sm:text-2xl">
                            {survey.title}
                        </h1>

                        <p className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500 dark:text-slate-400">
                            {/*
                                La edición sale de surveys.edition tal como está
                                en la base ('2026-1'). No se reformatea: si el
                                día de mañana alguien carga '2026-2', tiene que
                                verse eso y no una versión maquillada.
                            */}
                            {survey.edition && (
                                <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                    Edición {survey.edition}
                                </span>
                            )}
                            {survey.description && <span>{survey.description}</span>}
                        </p>
                    </div>

                    <a
                        href={`/surveys/${survey.id}/report`}
                        className="inline-flex shrink-0 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        <FileSpreadsheet size={15} />
                        Ver reporte detallado
                    </a>
                </header>

                <FiltrosDemograficos />

                {/*
                    Corte global. Si el universo entero está por debajo del
                    umbral no hay nada que mostrar: cualquier subconjunto es
                    todavía más chico que el total, así que ninguna sección
                    podría dibujar un número.
                */}
                {suprimido || !resumen ? (
                    <Suprimido participantes={participantes} minimo={minimo} />
                ) : (
                    <>
                        <TarjetasEjecutivas participantes={participantes} resumen={resumen} />

                        <Dimensiones dimensiones={dimensiones} preguntas={preguntas} minimo={minimo} />

                        <Destacados fortalezas={resumen.fortalezas} prioridades={resumen.prioridades} />

                        <RespuestasAbiertas surveyId={survey.id} abiertas={abiertas} minimo={minimo} />
                    </>
                )}

                <NotaAnonimato minimo={minimo} descartadas={descartadas} />
            </div>
        </AppLayout>
    );
}
