<?php
namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TechnologyEvolutionExport implements FromCollection, WithHeadings
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
           AGRUPADOR (igual que weeklyScores)
        ========================= */
        switch ($this->filter) {

            case 'biweekly':
                $group = "
                    CONCAT(
                        YEAR(j.published_at), '-',
                        LPAD(MONTH(j.published_at),2,'0'), '-',
                        IF(DAY(j.published_at) <= 15, 1, 2)
                    )
                ";
                break;

            case 'monthly':
                $group = "DATE_FORMAT(j.published_at, '%Y-%m')";
                break;

            default:
                $group = "YEARWEEK(j.published_at,1)";
                break;
        }

        $rows = DB::table('technology_job as tj')
            ->join('job_offers as j', 'j.id', '=', 'tj.job_offer_id')
            ->join('market_entities as me', 'me.id', '=', 'tj.market_entity_id')
            ->whereYear('j.published_at', $this->year)
            ->select(
                DB::raw("$group as periodo"),
                DB::raw("MIN(DATE(j.published_at)) as fecha_inicio"),
                DB::raw("MAX(DATE(j.published_at)) as fecha_fin"),
                'me.name as tecnologia',
                DB::raw('COUNT(DISTINCT tj.job_offer_id) as vacantes')
            )
            ->groupBy('periodo', 'me.name')
            ->orderBy('fecha_inicio')
            ->get();

        return $rows;
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