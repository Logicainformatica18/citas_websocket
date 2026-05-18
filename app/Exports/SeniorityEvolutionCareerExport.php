<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SeniorityEvolutionCareerExport implements FromCollection, WithHeadings
{
    protected int $year;

    protected string $period;

    protected string $filter;

    protected array $careers;

    public function __construct(
        int $year,
        string $period,
        string $filter = 'weekly',
        array $careers = []
    ) {

        $this->year = $year;

        $this->period = $period;

        $this->filter = $filter;

        $this->careers = $careers;
    }

    private function getRange()
    {
        return $this->period === 's1'

            ? [
                'start' => "{$this->year}-01-01",
                'end'   => "{$this->year}-06-30",
            ]

            : [
                'start' => "{$this->year}-07-01",
                'end'   => "{$this->year}-12-31",
            ];
    }

    public function collection()
    {
        $range = $this->getRange();

        /*
        ==================================================
        📅 GROUPING
        ==================================================
        */

        switch ($this->filter) {

            case 'monthly':

                $group = "
                    DATE_FORMAT(
                        COALESCE(
                            jo.published_at,
                            jo.created_at
                        ),
                        '%Y-%m'
                    )
                ";

                $label = "
                    DATE_FORMAT(
                        MIN(
                            COALESCE(
                                jo.published_at,
                                jo.created_at
                            )
                        ),
                        '%M %Y'
                    )
                ";

                $startDate = "
                    DATE_FORMAT(
                        MIN(
                            COALESCE(
                                jo.published_at,
                                jo.created_at
                            )
                        ),
                        '%Y-%m-01'
                    )
                ";

                $endDate = "
                    LAST_DAY(
                        MIN(
                            COALESCE(
                                jo.published_at,
                                jo.created_at
                            )
                        )
                    )
                ";

                break;

            case 'biweekly':

                $group = "
                    CONCAT(
                        YEAR(
                            COALESCE(
                                jo.published_at,
                                jo.created_at
                            )
                        ),
                        '-',

                        LPAD(
                            MONTH(
                                COALESCE(
                                    jo.published_at,
                                    jo.created_at
                                )
                            ),
                            2,
                            '0'
                        ),

                        '-',

                        IF(
                            DAY(
                                COALESCE(
                                    jo.published_at,
                                    jo.created_at
                                )
                            ) <= 15,
                            1,
                            2
                        )
                    )
                ";

                $label = "
                    CASE

                        WHEN DAY(
                            MIN(
                                COALESCE(
                                    jo.published_at,
                                    jo.created_at
                                )
                            )
                        ) <= 15

                        THEN CONCAT(
                            'Primera quincena de ',
                            DATE_FORMAT(
                                MIN(
                                    COALESCE(
                                        jo.published_at,
                                        jo.created_at
                                    )
                                ),
                                '%M'
                            )
                        )

                        ELSE CONCAT(
                            'Segunda quincena de ',
                            DATE_FORMAT(
                                MIN(
                                    COALESCE(
                                        jo.published_at,
                                        jo.created_at
                                    )
                                ),
                                '%M'
                            )
                        )
                    END
                ";

                $startDate = "
                    CASE

                        WHEN DAY(
                            MIN(
                                COALESCE(
                                    jo.published_at,
                                    jo.created_at
                                )
                            )
                        ) <= 15

                        THEN DATE_FORMAT(
                            MIN(
                                COALESCE(
                                    jo.published_at,
                                    jo.created_at
                                )
                            ),
                            '%Y-%m-01'
                        )

                        ELSE DATE_FORMAT(
                            MIN(
                                COALESCE(
                                    jo.published_at,
                                    jo.created_at
                                )
                            ),
                            '%Y-%m-16'
                        )
                    END
                ";

                $endDate = "
                    CASE

                        WHEN DAY(
                            MIN(
                                COALESCE(
                                    jo.published_at,
                                    jo.created_at
                                )
                            )
                        ) <= 15

                        THEN DATE_FORMAT(
                            MIN(
                                COALESCE(
                                    jo.published_at,
                                    jo.created_at
                                )
                            ),
                            '%Y-%m-15'
                        )

                        ELSE LAST_DAY(
                            MIN(
                                COALESCE(
                                    jo.published_at,
                                    jo.created_at
                                )
                            )
                        )
                    END
                ";

                break;

            default:

                $group = "
                    YEARWEEK(
                        COALESCE(
                            jo.published_at,
                            jo.created_at
                        ),
                        1
                    )
                ";

                $label = "
                    CONCAT(
                        'Semana ',
                        CEIL(
                            DAY(
                                MIN(
                                    COALESCE(
                                        jo.published_at,
                                        jo.created_at
                                    )
                                )
                            ) / 7
                        ),
                        ' de ',
                        DATE_FORMAT(
                            MIN(
                                COALESCE(
                                    jo.published_at,
                                    jo.created_at
                                )
                            ),
                            '%M'
                        )
                    )
                ";

                $startDate = "
                    DATE_SUB(
                        MIN(
                            DATE(
                                COALESCE(
                                    jo.published_at,
                                    jo.created_at
                                )
                            )
                        ),
                        INTERVAL WEEKDAY(
                            MIN(
                                DATE(
                                    COALESCE(
                                        jo.published_at,
                                        jo.created_at
                                    )
                                )
                            )
                        ) DAY
                    )
                ";

                $endDate = "
                    DATE_ADD(
                        DATE_SUB(
                            MIN(
                                DATE(
                                    COALESCE(
                                        jo.published_at,
                                        jo.created_at
                                    )
                                )
                            ),
                            INTERVAL WEEKDAY(
                                MIN(
                                    DATE(
                                        COALESCE(
                                            jo.published_at,
                                            jo.created_at
                                        )
                                    )
                                )
                            ) DAY
                        ),
                        INTERVAL 6 DAY
                    )
                ";

                break;
        }

        /*
        ==================================================
        🔵 DISTRIBUCIÓN CARRERAS
        ==================================================
        */

        $rows = DB::table('job_offers as jo')

            ->join(
                'technology_job as tj',
                'tj.job_offer_id',
                '=',
                'jo.id'
            )

            ->join(
                'course_technology as ct',
                'ct.technology_id',
                '=',
                'tj.technology_id'
            )

            ->join(
                'career_course as cc',
                'cc.course_id',
                '=',
                'ct.course_id'
            )

            ->join(
                'careers as c',
                'c.id',
                '=',
                'cc.career_id'
            )

            ->where(function ($q) use ($range) {

                $q->whereBetween(
                    'jo.published_at',
                    [
                        $range['start'],
                        $range['end'],
                    ]
                )

                ->orWhere(function ($q2) use ($range) {

                    $q2->whereNull(
                        'jo.published_at'
                    )

                    ->whereBetween(
                        'jo.created_at',
                        [
                            $range['start'],
                            $range['end'],
                        ]
                    );
                });
            })

            ->when(
                !empty($this->careers),

                function ($q) {

                    $q->whereIn(
                        'c.slug',
                        $this->careers
                    );
                }
            )

            ->groupBy(
                DB::raw($group),
                'c.name'
            )

            ->select(

                DB::raw("
                    {$group}
                    as period
                "),

                DB::raw("
                    {$label}
                    as periodo
                "),

                DB::raw("
                    {$startDate}
                    as fecha_inicio
                "),

                DB::raw("
                    {$endDate}
                    as fecha_fin
                "),

                'c.name as carrera',

                DB::raw("
                    COUNT(DISTINCT jo.id)
                    as vacantes
                ")
            )

            ->get();

        /*
        ==================================================
        🟢 TOTALES REALES
        ==================================================
        */

        $realTotals = DB::table('job_offers as jo')

            ->join(
                'technology_job as tj',
                'tj.job_offer_id',
                '=',
                'jo.id'
            )

            ->join(
                'course_technology as ct',
                'ct.technology_id',
                '=',
                'tj.technology_id'
            )

            ->join(
                'career_course as cc',
                'cc.course_id',
                '=',
                'ct.course_id'
            )

            ->join(
                'careers as c',
                'c.id',
                '=',
                'cc.career_id'
            )

            ->where(function ($q) use ($range) {

                $q->whereBetween(
                    'jo.published_at',
                    [
                        $range['start'],
                        $range['end'],
                    ]
                )

                ->orWhere(function ($q2) use ($range) {

                    $q2->whereNull(
                        'jo.published_at'
                    )

                    ->whereBetween(
                        'jo.created_at',
                        [
                            $range['start'],
                            $range['end'],
                        ]
                    );
                });
            })

            ->when(
                !empty($this->careers),

                function ($q) {

                    $q->whereIn(
                        'c.slug',
                        $this->careers
                    );
                }
            )

            ->groupBy(
                DB::raw($group)
            )

            ->select(

                DB::raw("
                    {$group}
                    as period
                "),

                DB::raw("
                    COUNT(DISTINCT jo.id)
                    as total_unique
                ")
            )

            ->pluck(
                'total_unique',
                'period'
            );

        /*
        ==================================================
        📦 EXPORT
        ==================================================
        */

        return $rows

            ->map(function ($row) use (
                $realTotals
            ) {

                return [

                    'Periodo' =>
                        $row->periodo,

                    'Fecha Inicio' =>
                        $row->fecha_inicio,

                    'Fecha Fin' =>
                        $row->fecha_fin,

                    'Vacantes Únicas Reales' =>
                        $realTotals[
                            $row->period
                        ] ?? 0,

                    'Carrera' =>
                        $row->carrera,

                    'Vacantes por Carrera' =>
                        $row->vacantes,
                ];
            })

            ->sortBy('Fecha Inicio')

            ->values();
    }

    public function headings(): array
    {
        return [

            'Periodo',

            'Fecha Inicio',

            'Fecha Fin',

            'Vacantes Únicas Reales',

            'Carrera',

            'Vacantes por Carrera',
        ];
    }
}