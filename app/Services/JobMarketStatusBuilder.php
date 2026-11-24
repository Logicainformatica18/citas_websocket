<?php

namespace App\Services;

use App\Services\Indicators\IndicatorScope;
use App\Support\PerformanceTimer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class JobMarketStatusBuilder
{
    /** Los conteos se cachean 26 h: los recalcula a diario indicators:refresh-all. */
    public const CACHE_TTL = 26 * 3600;

    /**
     * Construye el Datos Generales
     * compatible con el frontend actual
     *
     * @param array $options
     *  - mode: scraper | market
     *  - entity?: languages | technologies | certifications
     *  - year
     *  - period
     */
    public static function build(array $options): array
    {
        $year   = (int) ($options['year'] ?? date('Y'));
        $period = $options['period'] ?? 's2';
        $mode   = $options['mode'] ?? 'market';
        $entity = $options['entity'] ?? null;

        $range = self::getPeriodRange($year, $period);

        return [
            'global'   => PerformanceTimer::section('job_market_status.global', fn () => self::buildGlobal()),
            'period'   => PerformanceTimer::section('job_market_status.period', fn () => self::buildPeriod($range)),
            'scraping' => PerformanceTimer::section('job_market_status.scraping', fn () => $mode === 'scraper'
                ? self::buildFromScraper($entity)
                : self::buildFromMarket()),
        ];
    }

    /* =====================================================
       GLOBAL
    ===================================================== */

    private static function buildGlobal(): array
    {
        // Conteos cacheados (versión de los indicadores en la clave; los recalcula a diario
        // indicators:refresh-all); la antigüedad se calcula en cada lectura.
        [$total, $newMonth] = Cache::remember(
            'job-market-builder:v' . IndicatorScope::cacheVersion() . ':global:' . Carbon::now()->format('Y-m'),
            self::CACHE_TTL,
            fn () => self::globalCounts()
        );

        $first = DB::table('job_offers')
            ->orderBy('created_at')
            ->value('created_at');

        return [
            'offers_total'     => $total,
            'offers_new_month' => $newMonth,
            'history_age'      => $first
                ? Carbon::parse($first)->diffForHumans(null, true)
                : null,
        ];
    }

    /** @return array{0:int, 1:int} [ofertas, ofertas nuevas del mes] sin excluidas por título */
    private static function globalCounts(): array
    {
        return [
            IndicatorScope::offers(DB::table('job_offers'))->count(),
            IndicatorScope::offers(DB::table('job_offers'))->where('created_at', '>=', Carbon::now()->startOfMonth())->count(),
        ];
    }

    /** Recalcula y guarda los conteos cacheados (global y del periodo). */
    public static function warm(int $year, string $period): void
    {
        Cache::put('job-market-builder:v' . IndicatorScope::cacheVersion() . ':global:' . Carbon::now()->format('Y-m'), self::globalCounts(), self::CACHE_TTL);
        $range = self::getPeriodRange($year, $period);
        Cache::put(self::periodKey($range), self::periodOffers($range), self::CACHE_TTL);
    }

    private static function periodKey(array $range): string
    {
        return 'job-market-builder:v' . IndicatorScope::cacheVersion() . ":period:{$range['start']}";
    }

    /* =====================================================
       PERIOD
    ===================================================== */

    private static function buildPeriod(array $range): array
    {
        $offers = Cache::remember(self::periodKey($range), self::CACHE_TTL, fn () => self::periodOffers($range));

        $days = Carbon::parse($range['start'])
            ->diffInDays(Carbon::parse($range['end'])) + 1;

        return [
            'offers_analysed' => $offers,
            'avg_per_day'     => $days > 0 ? round($offers / $days, 2) : 0,
            'days_covered'    => $days,
            'date_range'      => [
                'from' => $range['start'],
                'to'   => $range['end'],
            ],
        ];
    }

    private static function periodOffers(array $range): int
    {
        // DATE(COALESCE(published_at, created_at)) dentro del rango, en dos conteos que usan índices
        // (idx_jo_pub_excl, idx_jo_created_excl): publicadas en el rango + sin fecha de publicación
        // creadas en el rango. Mismo resultado; ~0,6 s en vez de ~2 s recorriendo la tabla.
        $from = $range['start'];
        $to = Carbon::parse($range['end'])->addDay()->toDateString();   // fin exclusivo

        $offers = IndicatorScope::offers(DB::table('job_offers'))
            ->where('published_at', '>=', $from)->where('published_at', '<', $to)
            ->count()
            + IndicatorScope::offers(DB::table('job_offers'))
                ->whereNull('published_at')
                ->where('created_at', '>=', $from)->where('created_at', '<', $to)
                ->count();

        return $offers;
    }

    /* =====================================================
       SCRAPER MODE (rankings)
    ===================================================== */

    private static function buildFromScraper(?string $entity): array
    {
        if (!$entity) {
            return ['exists' => false];
        }

        $run = DB::table('scraper_runs')
            ->where('entity', $entity)
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->first();

        if (!$run) {
            return ['exists' => false];
        }

        return [
            'exists'        => true,
            'mode'          => 'scraper',
            'updated_at'    => $run->finished_at,
            'updated_human' => Carbon::parse($run->finished_at)->diffForHumans(),
            'source'        => $run->source,
        ];
    }

    /* =====================================================
       MARKET MODE (indicadores transversales)
    ===================================================== */

    private static function buildFromMarket(): array
    {
        $job = DB::table('job_offers')
            ->orderByDesc('created_at')
            ->first(['created_at', 'source']);

        if (!$job) {
            return ['exists' => false];
        }

        return [
            'exists'        => true,
            'mode'          => 'market',
            'updated_at'    => $job->created_at,
            'updated_human' => Carbon::parse($job->created_at)->diffForHumans(),
            'source'        => $job->source,
        ];
    }

    /* =====================================================
       Utils
    ===================================================== */

    private static function getPeriodRange(int $year, string $period): array
    {
        return $period === 's1'
            ? ['start' => "$year-01-01", 'end' => "$year-06-30"]
            : ['start' => "$year-07-01", 'end' => "$year-12-31"];
    }
}
