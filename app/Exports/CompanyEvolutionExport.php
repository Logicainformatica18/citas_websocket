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

    public function __construct($year, $period, $filter = 'weekly', $type = 'national')
    {
        $this->year   = $year;
        $this->period = $period;
        $this->filter = $filter;
        $this->type   = $type; // 🔥 national | international
    }

    /* ==================================================
       RANGO
    ================================================== */
    private function getRange()
    {
        return $this->period === 's1'
            ? ['start' => "{$this->year}-01-01", 'end' => "{$this->year}-06-30"]
            : ['start' => "{$this->year}-07-01", 'end' => "{$this->year}-12-31"];
    }

    /* ==================================================
       DATA
    ================================================== */
    public function collection()
    {
        $range = $this->getRange();

        /* =========================
           FILTRO PAÍS
        ========================= */
        $query = DB::table('job_offers')
            ->whereNotNull('company')
            ->whereBetween('published_at', [$range['start'], $range['end']]);

        if ($this->type === 'national') {
            $query->where('country', 'Peru');
        } else {
            $query->where('country', '!=', 'Peru');
        }

        /* =========================
           AGRUPADOR
        ========================= */
        switch ($this->filter) {

            case 'monthly':
                $group = "DATE_FORMAT(published_at, '%Y-%m')";
                break;

            case 'biweekly':
                $group = "
                    CONCAT(
                        YEAR(published_at), '-',
                        LPAD(MONTH(published_at),2,'0'), '-',
                        IF(DAY(published_at) <= 15, 1, 2)
                    )
                ";
                break;

            default:
                $group = "YEARWEEK(published_at,1)";
                break;
        }

        /* =========================
           QUERY
        ========================= */
        $rows = $query
            ->select(
                DB::raw("$group as period"),
                DB::raw("MIN(published_at) as start_date"),
                DB::raw("MAX(published_at) as end_date"),
                DB::raw("UPPER(TRIM(company)) as company"),
                DB::raw("COUNT(*) as total")
            )
            ->groupBy(DB::raw($group), DB::raw("UPPER(TRIM(company))"))
            ->get();

        /* =========================
           TRANSFORMACIÓN
        ========================= */
        $result = $rows
            ->groupBy('period')
            ->flatMap(function ($items) {

                $total = $items->sum('total');
                $start = $items->min('start_date');
                $end   = $items->max('end_date');

                return $items
                    ->sortByDesc('total')
                    ->take(5) // 🔥 TOP 5 igual que UI
                    ->map(function ($item) use ($start, $end, $total) {

                        return [
                            'start_date' => $start,
                            'end_date'   => $end,
                            'company'    => $item->company,
                            'jobs'       => $item->total,
                            'percentage' => $total > 0
                                ? round(($item->total / $total) * 100, 1)
                                : 0,
                        ];
                    });

            })
            ->values();

        return $result;
    }

    /* ==================================================
       HEADERS
    ================================================== */
    public function headings(): array
    {
        return [
            'Fecha inicio',
            'Fecha fin',
            'Empresa',
            'Vacantes',
            'Porcentaje (%)',
        ];
    }
}