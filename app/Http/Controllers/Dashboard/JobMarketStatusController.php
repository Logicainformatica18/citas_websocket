<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class JobMarketStatusController
{
    /** Las consultas recorren job_offers completo (~400k filas): se cachean 1 h. */
    public const CACHE_TTL = 3600;

    /**
     * Datos Generales
     * - Métricas globales (siempre)
     * - Métricas por Periodo (si se pasa year + period)
     *
     * Se cachean los valores crudos y los textos relativos ("hace 5 minutos") se
     * generan en cada lectura, para que no se queden congelados durante la hora
     * de caché.
     */
    public static function get(?int $year = null, ?string $period = null): array
    {
        Carbon::setLocale('es');
        $now = Carbon::now();

        $global = Cache::remember(self::globalKey($now), self::CACHE_TTL, fn () => self::computeGlobal($now));

        $periodData = null;

        if ($year && in_array($period, ['s1', 's2'])) {
            $periodData = Cache::remember(
                self::periodKey($year, $period),
                self::CACHE_TTL,
                fn () => self::computePeriod($year, $period)
            );
        }

        return self::format($global, $periodData, $now);
    }

    /**
     * Recalcula y guarda la caché (lo llama ranking:refresh-languages al terminar).
     *
     * @param  array<int, array{0:int,1:string}>  $periods  pares [año, 's1'|'s2']
     */
    public static function warm(array $periods = []): void
    {
        $now = Carbon::now();

        Cache::put(self::globalKey($now), self::computeGlobal($now), self::CACHE_TTL);

        foreach ($periods as [$year, $period]) {
            Cache::put(self::periodKey($year, $period), self::computePeriod($year, $period), self::CACHE_TTL);
        }
    }

    /* =====================================================
       CLAVES DE CACHÉ
    ===================================================== */

    // Incluye el mes para que "ofertas nuevas del mes" no arrastre el mes anterior.
    private static function globalKey(Carbon $now): string
    {
        return 'job-market-status:global:' . $now->format('Y-m');
    }

    private static function periodKey(int $year, string $period): string
    {
        return "job-market-status:period:{$year}:{$period}";
    }

    /* =====================================================
       MÉTRICAS GLOBALES (crudas)
    ===================================================== */

    private static function computeGlobal(Carbon $now): array
    {
        $startOfMonth = $now->copy()->startOfMonth();

        $bySource = DB::table('job_offers')
            ->select(
                'source',
                DB::raw('COUNT(*) as total'),
                DB::raw('MAX(created_at) as last_inserted_at')
            )
            ->groupBy('source')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return [
            'offers_total' => DB::table('job_offers')->count(),
            'first_offer_at' => DB::table('job_offers')->min('created_at'),
            'last_inserted_at' => DB::table('job_offers')->max('created_at'),
            'last_published_at' => DB::table('job_offers')
                ->whereNotNull('published_at')
                ->max('published_at'),
            'offers_new_month' => DB::table('job_offers')
                ->where('created_at', '>=', $startOfMonth)
                ->count(),
            'sources' => $bySource,
            'generated_at' => $now->toDateTimeString(),
        ];
    }

    /* =====================================================
       MÉTRICAS POR PERIODO (crudas)
    ===================================================== */

    private static function computePeriod(int $year, string $period): array
    {
        $periodStart = $period === 's1'
            ? Carbon::create($year, 1, 1)
            : Carbon::create($year, 7, 1);

        $periodEnd = $period === 's1'
            ? Carbon::create($year, 6, 30)->endOfDay()
            : Carbon::create($year, 12, 31)->endOfDay();

        $offersInPeriod = DB::table('job_offers')
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->count();

        $daysCovered = $periodStart->diffInDays($periodEnd) + 1;

        return [
            'year' => $year,
            'period' => $period,
            'date_range' => [
                'from' => $periodStart->toDateString(),
                'to'   => $periodEnd->toDateString(),
            ],
            'offers_analysed' => $offersInPeriod,
            'days_covered' => (int) round($daysCovered),
            'avg_per_day' => $daysCovered > 0
                ? (int) round($offersInPeriod / $daysCovered)
                : 0,
        ];
    }

    /* =====================================================
       RETURN FINAL (LISTO PARA UI) — mismo contrato de siempre
    ===================================================== */

    private static function format(array $global, ?array $periodData, Carbon $now): array
    {
        $firstOfferAtC = $global['first_offer_at'] ? Carbon::parse($global['first_offer_at']) : null;
        $lastInsertedAtC = $global['last_inserted_at'] ? Carbon::parse($global['last_inserted_at']) : null;
        $lastPublishedAtC = $global['last_published_at'] ? Carbon::parse($global['last_published_at']) : null;

        return [
            'global' => [
                'offers_total' => $global['offers_total'],
                'offers_new_month' => $global['offers_new_month'],

                'history_age' => $firstOfferAtC
                    ? $firstOfferAtC->diffForHumans($now, ['parts' => 1, 'short' => true])
                    : null,

                'last_update' => [
                    'at' => $global['last_inserted_at'],
                    'human' => $lastInsertedAtC?->diffForHumans($now, ['parts' => 1]),
                ],

                'last_published' => [
                    'at' => $global['last_published_at'],
                    'human' => $lastPublishedAtC?->diffForHumans($now, ['parts' => 1]),
                ],
            ],

            'period' => $periodData,

            'sources' => array_map(fn ($row) => (object) $row, $global['sources']),

            'generated_at' => $global['generated_at'],
        ];
    }
}
