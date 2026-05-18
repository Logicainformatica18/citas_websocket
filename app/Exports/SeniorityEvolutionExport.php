<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SeniorityEvolutionExport implements FromCollection, WithHeadings
{
    protected array $range;

    protected string $filter;

    public function __construct(
        array $range,
        string $filter = 'weekly'
    ) {

        $this->range = $range;

        $this->filter = $filter;
    }

    public function collection()
    {
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
        🔵 QUERY
        ==================================================
        */

        $rows = DB::table('job_offers as jo')

            ->where(function ($q) {

                $q->whereBetween(
                    'jo.published_at',
                    [
                        $this->range['start'],
                        $this->range['end'],
                    ]
                )

                ->orWhere(function ($q2) {

                    $q2->whereNull(
                        'jo.published_at'
                    )

                    ->whereBetween(
                        'jo.created_at',
                        [
                            $this->range['start'],
                            $this->range['end'],
                        ]
                    );
                });
            })

            ->whereIn(
                DB::raw(
                    'LOWER(TRIM(jo.seniority))'
                ),
                [
                    'junior',
                    'mid',
                    'senior',
                ]
            )

            ->groupBy(
                DB::raw($group),
                DB::raw(
                    'UPPER(TRIM(jo.seniority))'
                )
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

                DB::raw("
                    UPPER(
                        TRIM(jo.seniority)
                    ) as nivel
                "),

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

            ->where(function ($q) {

                $q->whereBetween(
                    'jo.published_at',
                    [
                        $this->range['start'],
                        $this->range['end'],
                    ]
                )

                ->orWhere(function ($q2) {

                    $q2->whereNull(
                        'jo.published_at'
                    )

                    ->whereBetween(
                        'jo.created_at',
                        [
                            $this->range['start'],
                            $this->range['end'],
                        ]
                    );
                });
            })

            ->whereIn(
                DB::raw(
                    'LOWER(TRIM(jo.seniority))'
                ),
                [
                    'junior',
                    'mid',
                    'senior',
                ]
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

                    'Nivel' =>
                        $row->nivel,

                    'Vacantes por Nivel' =>
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

            'Nivel',

            'Vacantes por Nivel',
        ];
    }
}