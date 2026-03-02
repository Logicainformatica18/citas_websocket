<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class JobMarketStatusBuilder
{
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
            'global'   => self::buildGlobal(),
            'period'   => self::buildPeriod($range),
            'scraping' => $mode === 'scraper'
                ? self::buildFromScraper($entity)
                : self::buildFromMarket(),
        ];
    }

    /* =====================================================
       GLOBAL
    ===================================================== */

    private static function buildGlobal(): array
    {
        $total = DB::table('job_offers')->count();

        $monthStart = Carbon::now()->startOfMonth();

        $newMonth = DB::table('job_offers')
            ->where('created_at', '>=', $monthStart)
            ->count();

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

    /* =====================================================
       PERIOD
    ===================================================== */

    private static function buildPeriod(array $range): array
    {
        $query = DB::table('job_offers')
            ->whereBetween(
                DB::raw('DATE(COALESCE(published_at, created_at))'),
                [$range['start'], $range['end']]
            );

        $offers = $query->count();

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
