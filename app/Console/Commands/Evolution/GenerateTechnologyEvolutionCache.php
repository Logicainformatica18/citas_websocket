<?php

namespace App\Console\Commands\Evolution;

use Carbon\Carbon;
use App\Models\Prueba;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateTechnologyEvolutionCache extends Command
{
    /*
    ==================================================
    SIGNATURE
    ==================================================
    */

    protected $signature =
        'technology:evolution-cache';

    /*
    ==================================================
    DESCRIPTION
    ==================================================
    */

    protected $description =
        'Generate technology evolution cache';

    /*
    ==================================================
    HANDLE
    ==================================================
    */

    public function handle()
    {
        try {

            $this->info(
                'Generating technology evolution cache...'
            );

            /*
            ==============================================
            CONFIG
            ==============================================
            */

            $year = now()->year;

            /*
            ==============================================
            PESOS
            ==============================================
            */

            try {

                $weights = Prueba::getActive(
                    'technologies'
                );

            } catch (\Throwable $e) {

                $weights = null;
            }

            $laborWeight = (float) (
                $weights?->labor_weight ?? 0.7
            );

            $trendWeight = (float) (
                $weights?->trend_weight ?? 0.3
            );

            /*
            ==============================================
            JOBS DEL AÑO
            ==============================================
            */

            $jobs = DB::table(
                'technology_job as tj'
            )

            ->join(
                'job_offers as j',
                'j.id',
                '=',
                'tj.job_offer_id'
            )

            ->join(
                'market_entities as me',
                'me.id',
                '=',
                'tj.market_entity_id'
            )

            ->whereYear(
                'j.published_at',
                $year
            )

            ->where(
                'me.entity_type',
                'technology'
            )

            ->select(

                'me.id',

                'me.name',

                'j.id as job_offer_id',

                'j.published_at'
            )

            ->get();

            /*
            ==============================================
            TRENDS
            ==============================================
            */

            $trends = DB::table(
                'entity_trends as et'
            )

            ->join(
                'market_entities as me',
                'me.id',
                '=',
                'et.market_entity_id'
            )

            ->where(
                'et.year',
                $year
            )

            ->where(
                'me.entity_type',
                'technology'
            )

            ->select(

                'me.id',

                'et.created_at'
            )

            ->get();

            /*
            ==============================================
            PERIOD TYPES
            ==============================================
            */

            foreach ([
                'weekly',
                'biweekly',
                'monthly',
            ] as $type) {

                $this->line(
                    "Processing {$type}..."
                );

                $periods = $this->buildPeriods(
                    $year,
                    $type
                );

                foreach ($periods as $period) {

                    /*
                    ======================================
                    SKIP IF EXISTS
                    ======================================
                    */

                    $exists = DB::table(
                        'technology_evolution_cache'
                    )

                    ->where(
                        'period_type',
                        $type
                    )

                    ->whereDate(
                        'start_date',
                        $period['start']
                    )

                    ->whereDate(
                        'end_date',
                        $period['end']
                    )

                    ->exists();

                    if ($exists) {

                        $this->line(
                            "Skipping {$period['label']}"
                        );

                        continue;
                    }

                    /*
                    ======================================
                    JOBS FILTRADOS
                    ======================================
                    */

                    $periodJobs = $jobs

                        ->filter(function ($job)
                            use ($period) {

                            return Carbon::parse(
                                $job->published_at
                            )

                            ->between(
                                $period['start'],
                                $period['end']
                            );
                        });

                    /*
                    ======================================
                    TRENDS FILTRADOS
                    ======================================
                    */

                    $periodTrends = $trends

                        ->filter(function ($trend)
                            use ($period) {

                            return Carbon::parse(
                                $trend->created_at
                            )

                            ->between(
                                $period['start'],
                                $period['end']
                            );
                        });

                    /*
                    ======================================
                    GROUP JOBS
                    ======================================
                    */

                    $jobCounts = $periodJobs

                        ->groupBy('id')

                        ->map(function ($items) {

                            return collect($items)

                                ->pluck(
                                    'job_offer_id'
                                )

                                ->unique()

                                ->count();
                        });

                    /*
                    ======================================
                    GROUP TRENDS
                    ======================================
                    */

                    $trendCounts = $periodTrends

                        ->groupBy('id')

                        ->map(function ($items) {

                            return $items->count();
                        });

                    /*
                    ======================================
                    MAX VALUES
                    ======================================
                    */

                    $maxLabor = max(
                        $jobCounts->max() ?? 0,
                        1
                    );

                    $maxTrend = max(
                        $trendCounts->max() ?? 0,
                        1
                    );

                    /*
                    ======================================
                    TECHNOLOGIES
                    ======================================
                    */

                    $technologies = $periodJobs

                        ->groupBy('id')

                        ->map(function ($items, $techId)
                            use (
                                $jobCounts,
                                $trendCounts,
                                $maxLabor,
                                $maxTrend,
                                $laborWeight,
                                $trendWeight
                            ) {

                            $first = $items->first();

                            $jobs =
                                $jobCounts[$techId]
                                ?? 0;

                            $trendReports =
                                $trendCounts[$techId]
                                ?? 0;

                            /*
                            LABOR SCORE
                            */

                            $laborScore = round(

                                (
                                    log(
                                        $jobs + 1
                                    )
                                    /
                                    log(
                                        $maxLabor + 1
                                    )
                                ) * 100,

                                1
                            );

                            /*
                            TREND SCORE
                            */

                            $trendScore = round(

                                (
                                    log(
                                        $trendReports + 1
                                    )
                                    /
                                    log(
                                        $maxTrend + 1
                                    )
                                ) * 100,

                                1
                            );

                            /*
                            FINAL SCORE
                            */

                            $finalScore = round(

                                (
                                    (
                                        $laborScore
                                        *
                                        $laborWeight
                                    )

                                    +

                                    (
                                        $trendScore
                                        *
                                        $trendWeight
                                    )
                                ),

                                1
                            );

                            return [

                                'technology_id' =>
                                    $techId,

                                'technology_name' =>
                                    $first->name,

                                'jobs' =>
                                    $jobs,

                                'trend_reports' =>
                                    $trendReports,

                                'labor_score' =>
                                    $laborScore,

                                'trend_score' =>
                                    $trendScore,

                                'final_score' =>
                                    $finalScore,
                            ];
                        })

                        ->sortByDesc(
                            'final_score'
                        )

                        ->values();

                    /*
                    ======================================
                    INSERT CACHE
                    ======================================
                    */

                    foreach (
                        $technologies as $index => $row
                    ) {

                        DB::table(
                            'technology_evolution_cache'
                        )

                        ->insert([

                            'year' =>
                                $year,

                            'period_type' =>
                                $type,

                            'period_label' =>
                                $period['label'],

                            'start_date' =>
                                $period['start']
                                    ->format('Y-m-d'),

                            'end_date' =>
                                $period['end']
                                    ->format('Y-m-d'),

                            'market_entity_id' =>
                                $row['technology_id'],

                            'jobs' =>
                                $row['jobs'],

                            'trend_reports' =>
                                $row['trend_reports'],

                            'labor_score' =>
                                $row['labor_score'],

                            'trend_score' =>
                                $row['trend_score'],

                            'final_score' =>
                                $row['final_score'],

                            'ranking_position' =>
                                $index + 1,

                            'created_at' =>
                                now(),

                            'updated_at' =>
                                now(),
                        ]);
                    }

                    $this->info(
                        "Generated {$period['label']}"
                    );
                }
            }

            $this->info(
                'Technology evolution cache generated successfully.'
            );

            return self::SUCCESS;

        } catch (\Throwable $e) {

            Log::error(
                '[TECH_EVOLUTION_CACHE_ERROR]',
                [

                    'message' =>
                        $e->getMessage(),

                    'line' =>
                        $e->getLine(),

                    'file' =>
                        $e->getFile(),

                    'trace' =>
                        $e->getTraceAsString(),
                ]
            );

            $this->error(
                $e->getMessage()
            );

            return self::FAILURE;
        }
    }

    /*
    ==================================================
    BUILD PERIODS
    ==================================================
    */

    private function buildPeriods(
        int $year,
        string $type
    ): array {

        $periods = [];

        /*
        ==============================================
        WEEKLY
        ==============================================
        */

        if ($type === 'weekly') {

            $startOfYear = Carbon::create(
                $year,
                1,
                1
            )->startOfWeek();

            $endOfYear = Carbon::create(
                $year,
                12,
                31
            )->endOfWeek();

            $current = $startOfYear->copy();

            while ($current <= $endOfYear) {

                $start = $current
                    ->copy()
                    ->startOfWeek();

                $end = $current
                    ->copy()
                    ->endOfWeek();

                $periods[] = [

                    'label' =>
                        'Semana ' .
                        $start->weekOfYear,

                    'start' => $start,

                    'end' => $end,
                ];

                $current->addWeek();
            }
        }

        /*
        ==============================================
        BIWEEKLY
        ==============================================
        */

        elseif ($type === 'biweekly') {

            for ($month = 1; $month <= 12; $month++) {

                $daysInMonth = Carbon::create(
                    $year,
                    $month,
                    1
                )->daysInMonth;

                $monthName = ucfirst(

                    Carbon::create()
                        ->month($month)
                        ->translatedFormat('F')
                );

                /*
                QUINCENA 1
                */

                $periods[] = [

                    'label' =>
                        'Quincena 1 de ' .
                        $monthName,

                    'start' => Carbon::create(
                        $year,
                        $month,
                        1
                    )->startOfDay(),

                    'end' => Carbon::create(
                        $year,
                        $month,
                        15
                    )->endOfDay(),
                ];

                /*
                QUINCENA 2
                */

                $periods[] = [

                    'label' =>
                        'Quincena 2 de ' .
                        $monthName,

                    'start' => Carbon::create(
                        $year,
                        $month,
                        16
                    )->startOfDay(),

                    'end' => Carbon::create(
                        $year,
                        $month,
                        $daysInMonth
                    )->endOfDay(),
                ];
            }
        }

        /*
        ==============================================
        MONTHLY
        ==============================================
        */

        else {

            for ($month = 1; $month <= 12; $month++) {

                $daysInMonth = Carbon::create(
                    $year,
                    $month,
                    1
                )->daysInMonth;

                $start = Carbon::create(
                    $year,
                    $month,
                    1
                )->startOfDay();

                $end = Carbon::create(
                    $year,
                    $month,
                    $daysInMonth
                )->endOfDay();

                $periods[] = [

                    'label' =>
                        ucfirst(
                            $start
                                ->translatedFormat(
                                    'F'
                                )
                        ),

                    'start' => $start,

                    'end' => $end,
                ];
            }
        }

        return $periods;
    }
}