<?php

namespace App\Services\Ranking;

use Illuminate\Support\Facades\DB;

class RankingWeightService
{
    public function store(
        string $context,
        float $laborWeight,
        float $trendWeight,
        int $userId
    ): void {
        if (round($laborWeight + $trendWeight, 2) !== 1.00) {
            throw new \InvalidArgumentException(
                'Las ponderaciones deben sumar 1.00'
            );
        }

        DB::transaction(function () use ($context, $laborWeight, $trendWeight, $userId) {

            DB::table('ranking_weights')
                ->where('context', $context)
                ->where('is_active', 1)
                ->update(['is_active' => 0]);

            DB::table('ranking_weights')->insert([
                'context'      => $context,
                'labor_weight' => $laborWeight,
                'trend_weight' => $trendWeight,
                'is_active'    => 1,
                'applied_at'   => now(),
                'updated_by'   => $userId,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        });
    }

    public function getActive(string $context): object
    {
        return DB::table('ranking_weights')
            ->where('context', $context)
            ->where('is_active', 1)
            ->orderByDesc('applied_at')
            ->first();
    }
}
