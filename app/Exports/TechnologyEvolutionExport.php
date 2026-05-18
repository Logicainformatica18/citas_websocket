<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TechnologyEvolutionExport implements FromCollection, WithHeadings
{
    protected int $year;

    protected string $filter;

    public function __construct(
        int $year,
        string $filter = 'weekly'
    ) {

        $this->year = $year;

        $this->filter = $filter;
    }

    public function collection()
    {
        /*
        ==================================================
        🔍 QUERY BASE
        ==================================================
        */

        $query = DB::table('technology_job as tj')

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
                $this->year
            );

        /*
        ==================================================
        📅 WEEKLY
        ==================================================
        */

        if ($this->filter === 'weekly') {

            return $this->buildWeekly($query);
        }

        /*
        ==================================================
        📅 BIWEEKLY
        ==================================================
        */

        if ($this->filter === 'biweekly') {

            return $this->buildBiweekly($query);
        }

        /*
        ==================================================
        📅 MONTHLY
        ==================================================
        */

        return $this->buildMonthly($query);
    }

    /*
    ==================================================
    📅 WEEKLY
    ==================================================
    */

    private function buildWeekly($query)
    {
        $weekExpression = "
            FLOOR((DAY(j.published_at)-1)/7)+1
        ";

        /*
        ==================================================
        🔵 TECNOLOGÍAS
        ==================================================
        */

        $rows = (clone $query)

            ->groupBy(
                DB::raw("MONTH(j.published_at)"),
                DB::raw($weekExpression),
                'me.name'
            )

            ->select(
                DB::raw("
                    MONTH(j.published_at)
                    as month_number
                "),

                DB::raw("
                    {$weekExpression}
                    as week_number
                "),

                'me.name as tecnologia',

                DB::raw("
                    COUNT(DISTINCT tj.job_offer_id)
                    as total
                ")
            )

            ->get();

        /*
        ==================================================
        🟢 TOTALES REALES
        ==================================================
        */

        $realTotals = DB::table('technology_job as tj')

            ->join(
                'job_offers as j',
                'j.id',
                '=',
                'tj.job_offer_id'
            )

            ->whereYear(
                'j.published_at',
                $this->year
            )

            ->select(
                DB::raw("
                    MONTH(j.published_at)
                    as month_number
                "),

                DB::raw("
                    {$weekExpression}
                    as week_number
                "),

                DB::raw("
                    COUNT(DISTINCT tj.job_offer_id)
                    as total_unique
                ")
            )

            ->groupBy(
                DB::raw("MONTH(j.published_at)"),
                DB::raw($weekExpression)
            )

            ->get()

            ->keyBy(function ($item) {

                return
                    $item->month_number .
                    '-' .
                    $item->week_number;
            });

        /*
        ==================================================
        📦 EXPORT
        ==================================================
        */

        $export = collect();

        foreach ($rows as $row) {

            $month = $row->month_number;

            $week = $row->week_number;

            $startDay =
                (($week - 1) * 7) + 1;

            $daysInMonth = Carbon::create(
                $this->year,
                $month,
                1
            )->daysInMonth;

            $endDay = min(
                $startDay + 6,
                $daysInMonth
            );

            $monthName = Carbon::create()
                ->month($month)
                ->translatedFormat('F');

            $key =
                $month .
                '-' .
                $week;

            $export->push([

                'Periodo' =>
                    'Semana ' .
                    $week .
                    ' de ' .
                    ucfirst($monthName),

                'Fecha Inicio' =>
                    Carbon::create(
                        $this->year,
                        $month,
                        $startDay
                    )->format('Y-m-d'),

                'Fecha Fin' =>
                    Carbon::create(
                        $this->year,
                        $month,
                        $endDay
                    )->format('Y-m-d'),

                'Total Vacantes Reales' =>
                    $realTotals[$key]->total_unique ?? 0,

                'Tecnología' =>
                    $row->tecnologia,

                'Vacantes Tecnología' =>
                    $row->total,
            ]);
        }

        return $export
            ->sortBy('Fecha Inicio')
            ->values();
    }

    /*
    ==================================================
    📅 BIWEEKLY
    ==================================================
    */

    private function buildBiweekly($query)
    {
        $expression = "
            CASE
                WHEN DAY(j.published_at) <= 15
                THEN 1
                ELSE 2
            END
        ";

        $rows = (clone $query)

            ->groupBy(
                DB::raw("MONTH(j.published_at)"),
                DB::raw($expression),
                'me.name'
            )

            ->select(
                DB::raw("
                    MONTH(j.published_at)
                    as month_number
                "),

                DB::raw("
                    {$expression}
                    as quincena
                "),

                'me.name as tecnologia',

                DB::raw("
                    COUNT(DISTINCT tj.job_offer_id)
                    as total
                ")
            )

            ->get();

        $realTotals = DB::table('technology_job as tj')

            ->join(
                'job_offers as j',
                'j.id',
                '=',
                'tj.job_offer_id'
            )

            ->whereYear(
                'j.published_at',
                $this->year
            )

            ->select(
                DB::raw("
                    MONTH(j.published_at)
                    as month_number
                "),

                DB::raw("
                    {$expression}
                    as quincena
                "),

                DB::raw("
                    COUNT(DISTINCT tj.job_offer_id)
                    as total_unique
                ")
            )

            ->groupBy(
                DB::raw("MONTH(j.published_at)"),
                DB::raw($expression)
            )

            ->get()

            ->keyBy(function ($item) {

                return
                    $item->month_number .
                    '-' .
                    $item->quincena;
            });

        $export = collect();

        foreach ($rows as $row) {

            $month = $row->month_number;

            $q = $row->quincena;

            $daysInMonth = Carbon::create(
                $this->year,
                $month,
                1
            )->daysInMonth;

            $startDay =
                $q == 1
                    ? 1
                    : 16;

            $endDay =
                $q == 1
                    ? 15
                    : $daysInMonth;

            $monthName = Carbon::create()
                ->month($month)
                ->translatedFormat('F');

            $key =
                $month .
                '-' .
                $q;

            $export->push([

                'Periodo' =>
                    'Quincena ' .
                    $q .
                    ' de ' .
                    ucfirst($monthName),

                'Fecha Inicio' =>
                    Carbon::create(
                        $this->year,
                        $month,
                        $startDay
                    )->format('Y-m-d'),

                'Fecha Fin' =>
                    Carbon::create(
                        $this->year,
                        $month,
                        $endDay
                    )->format('Y-m-d'),

                'Total Vacantes Reales' =>
                    $realTotals[$key]->total_unique ?? 0,

                'Tecnología' =>
                    $row->tecnologia,

                'Vacantes Tecnología' =>
                    $row->total,
            ]);
        }

        return $export
            ->sortBy('Fecha Inicio')
            ->values();
    }

    /*
    ==================================================
    📅 MONTHLY
    ==================================================
    */

    private function buildMonthly($query)
    {
        $rows = (clone $query)

            ->groupBy(
                DB::raw("MONTH(j.published_at)"),
                'me.name'
            )

            ->select(
                DB::raw("
                    MONTH(j.published_at)
                    as month_number
                "),

                'me.name as tecnologia',

                DB::raw("
                    COUNT(DISTINCT tj.job_offer_id)
                    as total
                ")
            )

            ->get();

        $realTotals = DB::table('technology_job as tj')

            ->join(
                'job_offers as j',
                'j.id',
                '=',
                'tj.job_offer_id'
            )

            ->whereYear(
                'j.published_at',
                $this->year
            )

            ->select(
                DB::raw("
                    MONTH(j.published_at)
                    as month_number
                "),

                DB::raw("
                    COUNT(DISTINCT tj.job_offer_id)
                    as total_unique
                ")
            )

            ->groupBy(
                DB::raw("MONTH(j.published_at)")
            )

            ->pluck(
                'total_unique',
                'month_number'
            );

        $export = collect();

        foreach ($rows as $row) {

            $month = $row->month_number;

            $daysInMonth = Carbon::create(
                $this->year,
                $month,
                1
            )->daysInMonth;

            $monthName = Carbon::create()
                ->month($month)
                ->translatedFormat('F');

            $export->push([

                'Periodo' =>
                    ucfirst($monthName),

                'Fecha Inicio' =>
                    Carbon::create(
                        $this->year,
                        $month,
                        1
                    )->format('Y-m-d'),

                'Fecha Fin' =>
                    Carbon::create(
                        $this->year,
                        $month,
                        $daysInMonth
                    )->format('Y-m-d'),

                'Total Vacantes Reales' =>
                    $realTotals[$month] ?? 0,

                'Tecnología' =>
                    $row->tecnologia,

                'Vacantes Tecnología' =>
                    $row->total,
            ]);
        }

        return $export
            ->sortBy('Fecha Inicio')
            ->values();
    }

    public function headings(): array
    {
        return [

            'Periodo',

            'Fecha Inicio',

            'Fecha Fin',

            'Total Vacantes Reales',

            'Tecnología',

            'Vacantes Tecnología',
        ];
    }
}