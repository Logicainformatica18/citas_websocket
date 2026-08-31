import { ArrowDownRight, ArrowUpRight, ThumbsDown, ThumbsUp, TrendingUp, Users } from 'lucide-react';
import { pct, prom, type Metricas, type Resumen } from './tipos';

/**
 * Las 6 tarjetas ejecutivas.
 *
 * SOBRE LA TARJETA DE PARTICIPACIÓN
 *
 * Muestra la cantidad de encuestas terminadas y NADA MÁS. No hay
 * porcentaje de participación porque no existe en ninguna parte el dato
 * del universo convocado: la base no tiene una tabla de colaboradores
 * esperados, así que cualquier porcentaje sería un número inventado.
 * Cuando ese dato exista, acá va el denominador.
 */

type Tarjeta = {
    icono: React.ReactNode;
    rotulo: string;
    valor: string;
    detalle?: string | null;
    acento: string;
};

function Tarjeta({ icono, rotulo, valor, detalle, acento }: Tarjeta) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
            <div className="flex items-center gap-2">
                <span className={`grid h-8 w-8 shrink-0 place-items-center rounded-lg ${acento}`}>{icono}</span>
                <span className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {rotulo}
                </span>
            </div>

            <p className="mt-3 text-2xl font-bold leading-tight text-slate-900 tabular-nums dark:text-slate-50">
                {valor}
            </p>

            {detalle && (
                <p className="mt-1.5 line-clamp-3 text-sm leading-5 text-slate-600 dark:text-slate-400">{detalle}</p>
            )}
        </div>
    );
}

/** Texto corto para identificar una pregunta o dimensión en una tarjeta. */
const nombreDe = (fila: Metricas | null): string | null => {
    if (!fila) return null;
    return fila.question ?? fila.title;
};

export default function TarjetasEjecutivas({
    participantes,
    resumen,
}: {
    participantes: number;
    resumen: Resumen;
}) {
    const {
        favorableGeneral,
        dimensionFuerte,
        dimensionDebil,
        preguntaMejor,
        preguntaPeor,
    } = resumen;

    return (
        <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <Tarjeta
                icono={<Users size={16} className="text-sky-700 dark:text-sky-300" />}
                rotulo="Participación"
                valor={`${participantes} ${participantes === 1 ? 'respuesta' : 'respuestas'}`}
                detalle="Encuestas terminadas. Las sesiones abandonadas no se cuentan."
                acento="bg-sky-100 dark:bg-sky-950"
            />

            <Tarjeta
                icono={<TrendingUp size={16} className="text-emerald-700 dark:text-emerald-300" />}
                rotulo="Favorable general"
                valor={pct(favorableGeneral)}
                detalle="Respuestas 4 o 5 sobre el total de respuestas de escala."
                acento="bg-emerald-100 dark:bg-emerald-950"
            />

            <Tarjeta
                icono={<ArrowUpRight size={16} className="text-emerald-700 dark:text-emerald-300" />}
                rotulo="Dimensión más fuerte"
                valor={pct(dimensionFuerte?.favorable ?? null)}
                detalle={nombreDe(dimensionFuerte)}
                acento="bg-emerald-100 dark:bg-emerald-950"
            />

            <Tarjeta
                icono={<ArrowDownRight size={16} className="text-rose-700 dark:text-rose-300" />}
                rotulo="Dimensión más débil"
                valor={pct(dimensionDebil?.favorable ?? null)}
                detalle={nombreDe(dimensionDebil)}
                acento="bg-rose-100 dark:bg-rose-950"
            />

            <Tarjeta
                icono={<ThumbsUp size={16} className="text-emerald-700 dark:text-emerald-300" />}
                rotulo="Pregunta mejor evaluada"
                valor={prom(preguntaMejor?.promedio ?? null)}
                detalle={nombreDe(preguntaMejor)}
                acento="bg-emerald-100 dark:bg-emerald-950"
            />

            <Tarjeta
                icono={<ThumbsDown size={16} className="text-rose-700 dark:text-rose-300" />}
                rotulo="Pregunta peor evaluada"
                valor={prom(preguntaPeor?.promedio ?? null)}
                detalle={nombreDe(preguntaPeor)}
                acento="bg-rose-100 dark:bg-rose-950"
            />
        </section>
    );
}
