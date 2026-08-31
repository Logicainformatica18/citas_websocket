import { colores, pct, type Metricas } from './tipos';

/**
 * Barra apilada al 100% · favorable / neutral / desfavorable.
 *
 * Son tres divs con width porcentual dentro de un flex. No se usa una
 * librería de gráficos a propósito: para una barra al 100% el ancho ES el
 * dato, y con `width: X%` sale exacto y accesible, sin pelear con el
 * dimensionado de un ResponsiveContainer ni cargar una dependencia.
 *
 * Los tres porcentajes vienen ya calculados y redondeados del servidor.
 * Suman ~100 salvo por el redondeo (cada uno se redondea a un decimal por
 * separado), así que NO se normalizan acá: mostrar 99.9% o 100.1% es
 * preferible a inventar un ajuste que haga que el número de la barra no
 * coincida con el número de la leyenda.
 */
export default function BarraApilada({ fila, alto = 'h-7' }: { fila: Metricas; alto?: string }) {
    if (fila.suprimido) {
        return (
            <div
                className={`${alto} w-full overflow-hidden rounded-md border border-dashed border-slate-300 bg-slate-50 dark:border-slate-600 dark:bg-slate-800`}
                role="img"
                aria-label="Resultados no mostrados para proteger el anonimato"
            />
        );
    }

    const bandas = [
        { clave: 'favorable' as const, valor: fila.favorable ?? 0, etiqueta: 'Favorable' },
        { clave: 'neutral' as const, valor: fila.neutral ?? 0, etiqueta: 'Neutral' },
        { clave: 'desfavorable' as const, valor: fila.desfavorable ?? 0, etiqueta: 'Desfavorable' },
    ];

    return (
        <div
            className={`flex ${alto} w-full overflow-hidden rounded-md bg-slate-100 dark:bg-slate-800`}
            role="img"
            aria-label={bandas.map((b) => `${b.etiqueta} ${pct(b.valor)}`).join(', ')}
        >
            {bandas.map((banda) => (
                <div
                    key={banda.clave}
                    className={`${colores[banda.clave].barra} flex items-center justify-center transition-all`}
                    style={{ width: `${banda.valor}%` }}
                    title={`${banda.etiqueta}: ${pct(banda.valor)}`}
                >
                    {/*
                        La etiqueta solo se dibuja si la banda es lo bastante
                        ancha para contenerla. Con menos de 12% el texto se
                        desborda sobre las bandas vecinas y queda ilegible.
                    */}
                    {banda.valor >= 12 && (
                        <span className="px-1 text-[11px] font-bold text-white tabular-nums">
                            {banda.valor.toFixed(0)}%
                        </span>
                    )}
                </div>
            ))}
        </div>
    );
}

/** Leyenda de las tres bandas. Se dibuja una sola vez por sección. */
export function LeyendaBandas() {
    const items = [
        { clave: 'favorable' as const, etiqueta: 'Favorable (4-5)' },
        { clave: 'neutral' as const, etiqueta: 'Neutral (3)' },
        { clave: 'desfavorable' as const, etiqueta: 'Desfavorable (1-2)' },
    ];

    return (
        <div className="flex flex-wrap items-center gap-x-5 gap-y-2">
            {items.map((item) => (
                <span key={item.clave} className="inline-flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                    <span className={`h-2.5 w-2.5 rounded-full ${colores[item.clave].punto}`} />
                    {item.etiqueta}
                </span>
            ))}
        </div>
    );
}
