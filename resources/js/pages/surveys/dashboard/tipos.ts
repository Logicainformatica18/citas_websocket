/**
 * Tipos compartidos del dashboard de resultados.
 *
 * Viven en un archivo aparte y no en dashboard.tsx porque los usan siete
 * componentes: duplicarlos en cada uno garantiza que en algún momento se
 * desincronicen del JSON que manda SurveyDashboardController.
 */

export type Survey = {
    id: number;
    title: string;
    description?: string | null;
    detail?: string | null;
    edition?: string | null;
    date_start?: string | null;
    date_end?: string | null;
};

/**
 * Una fila de métricas, que puede ser una dimensión o una pregunta.
 *
 * OJO CON LOS null: cuando `suprimido` es true, el servidor NO manda los
 * números — no es que el front los esconda, es que nunca salieron del
 * backend. Por eso todo lo numérico es `| null` y hay que chequear
 * `suprimido` antes de leerlos.
 */
export type Metricas = {
    id: number | null;
    category: string;
    title: string;
    question: string | null;
    orden: number;
    participantes: number;
    suprimido: boolean;
    respuestas: number | null;
    favorable: number | null;
    neutral: number | null;
    desfavorable: number | null;
    promedio: number | null;
};

export type Resumen = {
    favorableGeneral: number | null;
    dimensionFuerte: Metricas | null;
    dimensionDebil: Metricas | null;
    preguntaMejor: Metricas | null;
    preguntaPeor: Metricas | null;
    fortalezas: Metricas[];
    prioridades: Metricas[];
};

export type Abierta = {
    id: number;
    orden: number;
    title: string;
    question: string;
    total: number;
    suprimido: boolean;
};

export type Paginacion<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
};

export type RespuestaAbierta = { answer: string };

export type DashboardProps = {
    survey: Survey;
    participantes: number;
    minimo: number;
    suprimido: boolean;
    descartadas: number;
    resumen: Resumen | null;
    dimensiones: Metricas[];
    preguntas: Metricas[];
    abiertas: Abierta[];
};

/** Paleta única de las tres bandas, para que no se desincronicen. */
export const colores = {
    favorable: { barra: 'bg-emerald-500', punto: 'bg-emerald-500', texto: 'text-emerald-700 dark:text-emerald-300' },
    neutral: { barra: 'bg-amber-400', punto: 'bg-amber-400', texto: 'text-amber-700 dark:text-amber-300' },
    desfavorable: { barra: 'bg-rose-500', punto: 'bg-rose-500', texto: 'text-rose-700 dark:text-rose-300' },
} as const;

/** Formatea un porcentaje que puede venir suprimido. */
export const pct = (valor: number | null): string => (valor === null ? '—' : `${valor.toFixed(1)}%`);

/** Formatea el promedio sobre 5. */
export const prom = (valor: number | null): string => (valor === null ? '—' : `${valor.toFixed(2)} / 5`);
