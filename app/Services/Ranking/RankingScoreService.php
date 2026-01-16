<?php

namespace App\Services\Ranking;

class RankingScoreService
{
    public function normalize(float $value, float $max): float
    {
        if ($max <= 0) {
            return 0;
        }

        return round(($value / $max) * 100, 1);
    }

    public function finalScore(
        float $laborScore,
        float $trendScore,
        float $laborWeight,
        float $trendWeight
    ): float {
        return round(
            ($laborScore * $laborWeight) +
            ($trendScore * $trendWeight),
            1
        );
    }
}
