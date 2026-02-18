<?php

namespace App\Services\Indicators;

use Illuminate\Support\Facades\DB;

class PeAlignmentService
{
    /* =====================================================
       DETAILED COMPETENCIES
    ===================================================== */

    public function getDetailedCompetencies(
        int $careerId,
        int $year,
        int $quarter,
        array $range
    ) {

        $competencies = DB::table('competencies')
            ->where('career_id', $careerId)
            ->get(['id', 'name']);

        if ($competencies->isEmpty()) {
            return collect();
        }

        $competencyIds = $competencies->pluck('id');

        /* ===============================
           JOB COUNTS (AGREGADO)
        =============================== */

        $jobCounts = DB::table('competency_job_offer as cjo')
            ->join('job_offers as j', 'j.id', '=', 'cjo.job_offer_id')
            ->whereIn('cjo.competency_id', $competencyIds)
            ->whereBetween('j.published_at', [$range['start'], $range['end']])
            ->select('cjo.competency_id', DB::raw('COUNT(DISTINCT j.id) as total'))
            ->groupBy('cjo.competency_id')
            ->pluck('total', 'competency_id');

        /* ===============================
           TREND COUNTS (AGREGADO)
        =============================== */

        $trendCounts = DB::table('competency_trends')
            ->whereIn('competency_id', $competencyIds)
            ->where('year', $year)
            ->where('quarter', $quarter)
            ->select('competency_id', DB::raw('COUNT(*) as total'))
            ->groupBy('competency_id')
            ->pluck('total', 'competency_id');

        /* ===============================
           BUILD RESPONSE
        =============================== */

        return $competencies->map(function ($c) use ($jobCounts, $trendCounts) {

            $jobCount   = $jobCounts[$c->id] ?? 0;
            $trendCount = $trendCounts[$c->id] ?? 0;

            $marketMatch = $jobCount > 0;
            $trendMatch  = $trendCount > 0;

            $status = match (true) {
                $marketMatch && $trendMatch => 'aligned',
                $marketMatch || $trendMatch => 'partial',
                default => 'gap',
            };

            return [
                'id' => $c->id,
                'name' => $c->name,
                'job_count' => $jobCount,
                'trend_count' => $trendCount,
                'market_match' => $marketMatch,
                'trend_match' => $trendMatch,
                'status' => $status,
            ];
        })
        ->sortByDesc('job_count')
        ->values();
    }

    /* =====================================================
       CAREER SUMMARY
    ===================================================== */

    public function getCareerSummary(
        int $careerId,
        int $year,
        int $quarter,
        array $range,
        float $laborWeight,
        float $trendWeight
    ): array {

        $totalCompetencies = DB::table('competencies')
            ->where('career_id', $careerId)
            ->count();

        if ($totalCompetencies === 0) {
            return [
                'total_competencies' => 0,
                'market' => ['matched' => 0, 'percentage' => 0],
                'prospective' => ['matched' => 0, 'percentage' => 0],
                'final_index' => 0,
            ];
        }

        /* ===============================
           MARKET MATCHED
        =============================== */

        $marketMatched = DB::table('competencies as c')
            ->join('competency_job_offer as cjo', 'cjo.competency_id', '=', 'c.id')
            ->join('job_offers as j', 'j.id', '=', 'cjo.job_offer_id')
            ->where('c.career_id', $careerId)
            ->whereBetween('j.published_at', [$range['start'], $range['end']])
            ->distinct('c.id')
            ->count('c.id');

        $marketPct = round(($marketMatched / $totalCompetencies) * 100, 2);

        /* ===============================
           TREND MATCHED
        =============================== */

        $trendMatched = DB::table('competencies as c')
            ->join('competency_trends as ct', function ($join) use ($year, $quarter) {
                $join->on('ct.competency_id', '=', 'c.id')
                     ->where('ct.year', $year)
                     ->where('ct.quarter', $quarter);
            })
            ->where('c.career_id', $careerId)
            ->distinct('c.id')
            ->count('c.id');

        $trendPct = round(($trendMatched / $totalCompetencies) * 100, 2);

        /* ===============================
           FINAL INDEX
        =============================== */

        $finalIndex = round(
            ($laborWeight * $marketPct) +
            ($trendWeight * $trendPct),
            2
        );

        return [
            'total_competencies' => $totalCompetencies,
            'market' => [
                'matched' => $marketMatched,
                'percentage' => $marketPct,
            ],
            'prospective' => [
                'matched' => $trendMatched,
                'percentage' => $trendPct,
            ],
            'final_index' => $finalIndex,
        ];
    }
}
