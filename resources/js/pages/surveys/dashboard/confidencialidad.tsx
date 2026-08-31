import { ShieldAlert, ShieldCheck } from 'lucide-react';

/**
 * Regla de confidencialidad y nota de anonimato.
 *
 * ------------------------------------------------------------------
 * DÓNDE VIVE DE VERDAD LA REGLA
 * ------------------------------------------------------------------
 *
 * En el servidor, no acá. SurveyDashboardController devuelve las métricas
 * en null cuando hay menos de MINIMO_CONFIDENCIAL participantes distintos.
 * Este componente solo DIBUJA la explicación de por qué no hay números.
 *
 * Es importante entender el orden: si la regla viviera únicamente en el
 * front, los números igual viajarían en el JSON de Inertia y se leerían
 * con F12. Esconderlos en el render sería decorativo — el dato ya estaría
 * en la máquina de quien mira. Por eso el backend los anula antes de
 * enviarlos y el front nunca los recibe.
 *
 * Se cuentan PARTICIPANTES DISTINTOS, no respuestas: 4 personas
 * contestando 6 preguntas son 24 respuestas pero siguen siendo 4
 * personas, y lo que hay que proteger es la identificación de la persona.
 */

/** Bloque que reemplaza a los números cuando el corte es demasiado chico. */
export function Suprimido({
    participantes,
    minimo,
    compacto = false,
}: {
    participantes: number;
    minimo: number;
    compacto?: boolean;
}) {
    if (compacto) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                <ShieldAlert size={13} className="shrink-0" />
                Resultados no mostrados
            </span>
        );
    }

    return (
        <div className="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900">
            <ShieldAlert size={18} className="mt-0.5 shrink-0 text-slate-500" />
            <div>
                <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                    Resultados no mostrados para proteger el anonimato
                </p>
                <p className="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-400">
                    Este corte tiene {participantes}{' '}
                    {participantes === 1 ? 'participante' : 'participantes'}. Solo se muestran resultados
                    a partir de {minimo}, porque con menos personas sería posible deducir quién respondió
                    qué.
                </p>
            </div>
        </div>
    );
}

/** Nota fija al pie del dashboard. */
export function NotaAnonimato({ minimo, descartadas }: { minimo: number; descartadas: number }) {
    return (
        <footer className="mt-10 space-y-3">
            <div className="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
                <ShieldCheck size={18} className="mt-0.5 shrink-0 text-emerald-600" />
                <div className="text-sm leading-6 text-slate-600 dark:text-slate-400">
                    <p className="font-semibold text-slate-800 dark:text-slate-100">
                        Esta encuesta es anónima
                    </p>
                    <p className="mt-1">
                        No se registra nombre ni correo de los participantes: la tabla de encuestados
                        solo guarda un identificador sin datos personales. Los resultados se presentan
                        siempre de forma agrupada, y ningún conjunto con menos de {minimo} respuestas
                        muestra sus números.
                    </p>
                    <p className="mt-2">
                        Solo se incluyen encuestas <strong>terminadas</strong>. Las sesiones que quedaron
                        a mitad de camino no entran en ningún cálculo.
                    </p>
                </div>
            </div>

            {/*
                Este aviso solo aparece si hay respuestas fuera de formato.
                Existe para que un dato corrupto se VEA en vez de bajar el
                promedio en silencio: una respuesta sin el prefijo "N-"
                castea a 0 en SQL y hunde el promedio sin lanzar ningún error.
            */}
            {descartadas > 0 && (
                <div className="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200">
                    <strong>Atención:</strong> {descartadas}{' '}
                    {descartadas === 1 ? 'respuesta quedó' : 'respuestas quedaron'} fuera del cálculo por
                    no tener el formato esperado (<code>N-Texto</code>). Los porcentajes de arriba no las
                    incluyen. Conviene revisar cómo se guardaron antes de tomar decisiones sobre estos
                    números.
                </div>
            )}
        </footer>
    );
}
