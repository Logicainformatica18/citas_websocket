<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TechnologyEvolutionExport implements FromCollection, WithHeadings
{
    protected $year;

    protected $filter;

    public function __construct(
        $year,
        $filter = 'weekly'
    ) {

        $this->year = $year;

        $this->filter = $filter;
    }

    public function collection()
    {
        /*
        ==============================================
        🔍 BASE QUERY
        ==============================================
        */

        $rows = DB::table('technology_job as tj')

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
            )

            ->select(

                DB::raw("
                    MONTH(j.published_at)
                    as month_number
                "),

                DB::raw("
                    DAY(j.published_at)
                    as day_number
                "),

                'me.name as tecnologia',

                'tj.job_offer_id'
            )

            ->get();

        /*
        ==============================================
        📅 WEEKLY
        ==============================================
        */

        if ($this->filter === 'weekly') {

            return $this->buildWeekly(
                $rows
            );
        }

        /*
        ==============================================
        📅 BIWEEKLY
        ==============================================
        */

        if ($this->filter === 'biweekly') {

            return $this->buildBiweekly(
                $rows
            );
        }

        /*
        ==============================================
        📅 MONTHLY
        ==============================================
        */

        return $this->buildMonthly(
            $rows
        );
    }

    /*
    ==================================================
    📅 WEEKLY
    ==================================================
    */

    private function buildWeekly($rows)
    {
        $grouped = $rows

            ->groupBy(function ($row) {

                $week =
                    floor(
                        ($row->day_number - 1) / 7
                    ) + 1;

                return
                    $row->month_number .
                    '-' .
                    $week .
                    '-' .
                    $row->tecnologia;
            });

        $export = collect();

        foreach ($grouped as $group) {

            $first = $group->first();

            $month = $first->month_number;

            $week =
                floor(
                    ($first->day_number - 1) / 7
                ) + 1;

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

                'Tecnología' =>
                    $first->tecnologia,

                'Vacantes' =>
                    $group
                        ->pluck('job_offer_id')
                        ->unique()
                        ->count(),
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

    private function buildBiweekly($rows)
    {
        $grouped = $rows

            ->groupBy(function ($row) {

                $q =
                    $row->day_number <= 15
                        ? 1
                        : 2;

                return
                    $row->month_number .
                    '-' .
                    $q .
                    '-' .
                    $row->tecnologia;
            });

        $export = collect();

        foreach ($grouped as $group) {

            $first = $group->first();

            $month = $first->month_number;

            $q =
                $first->day_number <= 15
                    ? 1
                    : 2;

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

                'Tecnología' =>
                    $first->tecnologia,

                'Vacantes' =>
                    $group
                        ->pluck('job_offer_id')
                        ->unique()
                        ->count(),
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

    private function buildMonthly($rows)
    {
        $grouped = $rows

            ->groupBy(function ($row) {

                return
                    $row->month_number .
                    '-' .
                    $row->tecnologia;
            });

        $export = collect();

        foreach ($grouped as $group) {

            $first = $group->first();

            $month = $first->month_number;

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

                'Tecnología' =>
                    $first->tecnologia,

                'Vacantes' =>
                    $group
                        ->pluck('job_offer_id')
                        ->unique()
                        ->count(),
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

            'Tecnología',

            'Vacantes',
        ];
    }
}
