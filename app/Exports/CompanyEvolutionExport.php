<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CompanyEvolutionExport implements FromCollection, WithHeadings
{
    protected $year;
    protected $period;
    protected $filter;
    protected $type;

    public function __construct(
        $year,
        $period,
        $filter = 'weekly',
        $type = 'national'
    ) {
        $this->year   = $year;
        $this->period = $period;
        $this->filter = $filter;
        $this->type   = $type;
    }

    /*
    ==================================================
    📅 RANGE
    ==================================================
    */

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

    /*
    ==================================================
    📦 COLLECTION
    ==================================================
    */

    public function collection()
    {
        $range = $this->getRange();

        /*
        ==================================================
        🔥 MISMA LÓGICA QUE EL MODAL
        ==================================================
        */

        switch ($this->filter) {

            /*
            ==================================================
            📅 MONTHLY
            ==================================================
            */

            case 'monthly':

                $group = "
                    DATE_FORMAT(
                        COALESCE(published_at, created_at),
                        '%Y-%m'
                    )
                ";

                $label = "
                    DATE_FORMAT(
                        MIN(
                            COALESCE(
                                published_at,
                                created_at
                            )
                        ),
                        '%M %Y'
                    )
                ";

                $startDate = "
                    DATE_FORMAT(
                        MIN(
                            COALESCE(
                                published_at,
                                created_at
                            )
                        ),
                        '%Y-%m-01'
                    )
                ";

                $endDate = "
                    LAST_DAY(
                        MIN(
                            COALESCE(
                                published_at,
                                created_at
                            )
                        )
                    )
                ";

                break;

            /*
            ==================================================
            📅 BIWEEKLY
            ==================================================
            */

            case 'biweekly':

                $group = "
                    CONCAT(
                        YEAR(
                            COALESCE(
                                published_at,
                                created_at
                            )
                        ),
                        '-',
                        LPAD(
                            MONTH(
                                COALESCE(
                                    published_at,
                                    created_at
                                )
                            ),
                            2,
                            '0'
                        ),
                        '-',
                        IF(
                            DAY(
                                COALESCE(
                                    published_at,
                                    created_at
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
                                    published_at,
                                    created_at
                                )
                            )
                        ) <= 15

                        THEN CONCAT(
                            'Primera quincena de ',
                            DATE_FORMAT(
                                MIN(
                                    COALESCE(
                                        published_at,
                                        created_at
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
                                        published_at,
                                        created_at
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
                                    published_at,
                                    created_at
                                )
                            )
                        ) <= 15

                        THEN DATE_FORMAT(
                            MIN(
                                COALESCE(
                                    published_at,
                                    created_at
                                )
                            ),
                            '%Y-%m-01'
                        )

                        ELSE DATE_FORMAT(
                            MIN(
                                COALESCE(
                                    published_at,
                                    created_at
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
                                    published_at,
                                    created_at
                                )
                            )
                        ) <= 15

                        THEN DATE_FORMAT(
                            MIN(
                                COALESCE(
                                    published_at,
                                    created_at
                                )
                            ),
                            '%Y-%m-15'
                        )

                        ELSE LAST_DAY(
                            MIN(
                                COALESCE(
                                    published_at,
                                    created_at
                                )
                            )
                        )

                    END
                ";

                break;

            /*
            ==================================================
            📅 WEEKLY
            ==================================================
            */

            default:

                $group = "
                    YEARWEEK(
                        COALESCE(
                            published_at,
                            created_at
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
                                        published_at,
                                        created_at
                                    )
                                )
                            ) / 7
                        ),
                        ' de ',
                        DATE_FORMAT(
                            MIN(
                                COALESCE(
                                    published_at,
                                    created_at
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
                                    published_at,
                                    created_at
                                )
                            )
                        ),
                        INTERVAL WEEKDAY(
                            MIN(
                                DATE(
                                    COALESCE(
                                        published_at,
                                        created_at
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
                                        published_at,
                                        created_at
                                    )
                                )
                            ),
                            INTERVAL WEEKDAY(
                                MIN(
                                    DATE(
                                        COALESCE(
                                            published_at,
                                            created_at
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
        🔥 QUERY BASE
        ==================================================
        */

        $query = DB::table('job_offers')

            ->whereNotNull('company')

            ->where(function ($q) use ($range) {

                $q->whereBetween('published_at', [
                    $range['start'],
                    $range['end'],
                ])

                ->orWhere(function ($q2) use ($range) {

                    $q2->whereNull('published_at')

                       ->whereBetween('created_at', [
                           $range['start'],
                           $range['end'],
                       ]);
                });
            });

        /*
        ==================================================
        🌎 FILTRO PAÍS
        ==================================================
        */

        if ($this->type === 'national') {

            $query->where('country', 'Peru');

        } else {

            $query->where('country', '!=', 'Peru');
        }

        /*
        ==================================================
        🔥 QUERY FINAL
        ==================================================
        */

        $rows = $query

            ->select(

                DB::raw("$group as period"),

                DB::raw("$label as periodo"),

                DB::raw("$startDate as fecha_inicio"),

                DB::raw("$endDate as fecha_fin"),

                DB::raw("
                    UPPER(
                        TRIM(company)
                    ) as empresa
                "),

                DB::raw("COUNT(*) as vacantes")
            )

            ->groupBy(
                DB::raw($group),
                DB::raw("UPPER(TRIM(company))")
            )

            ->get();

        /*
        ==================================================
        🔥 TRANSFORMACIÓN
        ==================================================
        */

        return $rows

            ->groupBy('period')

            ->flatMap(function ($items) {

                $total = $items->sum('vacantes');

                return $items

                    ->sortByDesc('vacantes')

                    ->take(5)

                    ->values()

                    ->map(function ($item) use ($total) {

                        return [

                            'Periodo' =>
                                $item->periodo,

                            'Fecha Inicio' =>
                                $item->fecha_inicio,

                            'Fecha Fin' =>
                                $item->fecha_fin,

                            'Empresa' =>
                                $item->empresa,

                            'Vacantes' =>
                                $item->vacantes,

                            'Porcentaje (%)' =>
                                $total > 0
                                    ? round(
                                        ($item->vacantes / $total) * 100,
                                        1
                                    )
                                    : 0,
                        ];
                    });
            })

            ->values();
    }

    /*
    ==================================================
    📄 HEADERS
    ==================================================
    */

    public function headings(): array
    {
        return [
            'Periodo',
            'Fecha Inicio',
            'Fecha Fin',
            'Empresa',
            'Vacantes',
            'Porcentaje (%)',
        ];
    }
}