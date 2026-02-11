<?php

namespace App\Services\Ranking;

use Illuminate\Support\Facades\DB;

class MacroTrendScoreService
{
    public function getLaborSubquery(array $range, int $year)
    {
        return DB::table('macro_trends as m')
            ->leftJoin('macro_trend_entity_trend as mtet', 'mtet.macro_trend_id', '=', 'm.id')
            ->leftJoin('entity_trends as et', 'et.id', '=', 'mtet.entity_trend_id')

            ->leftJoin('technology_job as tj', 'tj.market_entity_id', '=', 'et.market_entity_id')
            ->leftJoin('certification_job as cj', 'cj.market_entity_id', '=', 'et.market_entity_id')
            ->leftJoin('language_job as lj', 'lj.market_entity_id', '=', 'et.market_entity_id')

            ->leftJoin('job_offers as j', function ($join) use ($range) {
                $join->on('j.id', '=', DB::raw('COALESCE(tj.job_offer_id, cj.job_offer_id, lj.job_offer_id)'))
                     ->whereBetween('j.published_at', [
                         $range['start'],
                         $range['end']
                     ]);
            })

            ->where('m.year', $year)

            ->select(
                'm.id as macro_id',
                DB::raw('COUNT(DISTINCT j.id) as total_jobs')
            )
            ->groupBy('m.id');
    }

    public function getGlobalTotals(array $range, int $year): array
    {
        $laborSub = $this->getLaborSubquery($range, $year);

        $totalJobs = DB::query()
            ->fromSub($laborSub, 'x')
            ->sum('total_jobs');

        $totalReports = DB::table('macro_trend_entity_trend as mtet')
            ->join('entity_trends as et', 'et.id', '=', 'mtet.entity_trend_id')
            ->whereBetween('et.created_at', [
                $range['start'],
                $range['end']
            ])
            ->count('et.id');

        return [
            'jobs' => $totalJobs,
            'reports' => $totalReports,
        ];
    }
}
