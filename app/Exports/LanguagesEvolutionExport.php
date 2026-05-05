<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LanguagesEvolutionExport implements FromCollection, WithHeadings
{
    protected $year;
    protected $filter;

    public function __construct($year, $filter = 'weekly')
    {
        $this->year = $year;
        $this->filter = $filter;
    }

    public function collection()
    {
        /* =========================
           AGRUPADOR
        ========================= */
        switch ($this->filter) {

            case 'monthly':
                $group = "DATE_FORMAT(j.published_at, '%Y-%m')";
                $start = "DATE_FORMAT(MIN(j.published_at), '%Y-%m-01')";
                $end   = "LAST_DAY(MIN(j.published_at))";
                break;

            case 'biweekly':
                $group = "
                    CONCAT(
                        YEAR(j.published_at), '-',
                        LPAD(MONTH(j.published_at),2,'0'), '-',
                        IF(DAY(j.published_at)<=15,1,2)
                    )
                ";

                $start = "
                    CASE 
                        WHEN DAY(MIN(j.published_at)) <= 15 
                        THEN DATE_FORMAT(MIN(j.published_at), '%Y-%m-01')
                        ELSE DATE_FORMAT(MIN(j.published_at), '%Y-%m-16')
                    END
                ";

                $end = "
                    CASE 
                        WHEN DAY(MIN(j.published_at)) <= 15 
                        THEN DATE_FORMAT(MIN(j.published_at), '%Y-%m-15')
                        ELSE LAST_DAY(MIN(j.published_at))
                    END
                ";
                break;

            default: // weekly
                $group = "YEARWEEK(j.published_at,1)";

                $start = "
                    DATE_SUB(
                        MIN(DATE(j.published_at)),
                        INTERVAL WEEKDAY(MIN(DATE(j.published_at))) DAY
                    )
                ";

                $end = "
                    DATE_ADD(
                        DATE_SUB(
                            MIN(DATE(j.published_at)),
                            INTERVAL WEEKDAY(MIN(DATE(j.published_at))) DAY
                        ),
                        INTERVAL 6 DAY
                    )
                ";
                break;
        }

        /* =========================
           QUERY
        ========================= */
        $rows = DB::table('language_job as lj')
            ->join('job_offers as j', 'j.id', '=', 'lj.job_offer_id')
            ->join('market_entities as me', 'me.id', '=', 'lj.market_entity_id')
            ->whereYear('j.published_at', $this->year)
            ->groupBy(
                DB::raw($group),
                'me.name'
            )
            ->select(
                DB::raw("$start as fecha_inicio"),
                DB::raw("$end as fecha_fin"),
                'me.name as lenguaje',
                DB::raw('COUNT(DISTINCT lj.job_offer_id) as vacantes')
            )
            ->orderBy('fecha_inicio')
            ->get();

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Fecha Inicio',
            'Fecha Fin',
            'Lenguaje',
            'Vacantes',
        ];
    }
}