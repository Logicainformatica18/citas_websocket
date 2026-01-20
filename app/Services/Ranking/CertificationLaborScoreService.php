<?php

namespace App\Services\Ranking;

use Illuminate\Support\Facades\DB;

class CertificationLaborScoreService
{
    public function getPeriodRange(string $period, int $year): array
    {
        return $period === 's1'
            ? ['start' => "$year-01-01", 'end' => "$year-06-30"]
            : ['start' => "$year-07-01", 'end' => "$year-12-31"];
    }

    public function getJobsForCertification(
        int $certificationId,
        int $year,
        string $period
    ) {
        $range = $this->getPeriodRange($period, $year);

        return DB::table('job_offers as j')
            ->join('certification_job as cj', 'cj.job_offer_id', '=', 'j.id')
            ->where('cj.certification_id', $certificationId)
            ->whereBetween('j.published_at', [$range['start'], $range['end']]);
    }

    public function countJobs(
        int $certificationId,
        int $year,
        string $period
    ): int {
        return (clone $this->getJobsForCertification(
            $certificationId,
            $year,
            $period
        ))->distinct('j.id')->count('j.id');
    }
}
