<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class JobMarketStatusController
{
    /**
     * Estado del mercado laboral
     * - Métricas globales (siempre)
     * - Métricas por período (si se pasa year + period)
     */
    public static function get(?int $year = null, ?string $period = null): array
    {
      Carbon::setLocale('es');
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();

        /* =====================================================
           MÉTRICAS GLOBALES
        ===================================================== */

        $totalOffers = DB::table('job_offers')->count();

        $firstOfferAt = DB::table('job_offers')->min('created_at');
        $lastInsertedAt = DB::table('job_offers')->max('created_at');
        $lastPublishedAt = DB::table('job_offers')
            ->whereNotNull('published_at')
            ->max('published_at');

        $newOffersThisMonth = DB::table('job_offers')
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        /* =====================================================
           FORMATEO HUMANO (GLOBAL)
        ===================================================== */

        $firstOfferAtC = $firstOfferAt ? Carbon::parse($firstOfferAt) : null;
        $lastInsertedAtC = $lastInsertedAt ? Carbon::parse($lastInsertedAt) : null;
        $lastPublishedAtC = $lastPublishedAt ? Carbon::parse($lastPublishedAt) : null;

        /* =====================================================
           MÉTRICAS POR PERÍODO (OPCIONAL)
        ===================================================== */

        $periodData = null;

        if ($year && in_array($period, ['s1', 's2'])) {
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

            $periodData = [
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
           BREAKDOWN POR FUENTE
        ===================================================== */

        $bySource = DB::table('job_offers')
            ->select(
                'source',
                DB::raw('COUNT(*) as total'),
                DB::raw('MAX(created_at) as last_inserted_at')
            )
            ->groupBy('source')
            ->orderByDesc('total')
            ->get();

        /* =====================================================
           RETURN FINAL (LISTO PARA UI)
        ===================================================== */

        return [
            'global' => [
                'offers_total' => $totalOffers,
                'offers_new_month' => $newOffersThisMonth,

                'history_age' => $firstOfferAtC
                    ? $firstOfferAtC->diffForHumans($now, ['parts' => 1, 'short' => true])
                    : null,

                'last_update' => [
                    'at' => $lastInsertedAt,
                    'human' => $lastInsertedAtC?->diffForHumans($now, ['parts' => 1]),
                ],

                'last_published' => [
                    'at' => $lastPublishedAt,
                    'human' => $lastPublishedAtC?->diffForHumans($now, ['parts' => 1]),
                ],
            ],

            'period' => $periodData,

            'sources' => $bySource,

            'generated_at' => $now->toDateTimeString(),
        ];
    }
}
