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
        $this->period = $period; // Nota: 's1' o 's2' define el rango de fechas en getRange()
        $this->filter = $filter; // 'weekly', 'biweekly', 'monthly'
        // Mapeamos el tipo al formato exacto de tu ENUM ('national', 'international')
        $this->type   = $type === 'national' ? 'national' : 'international';
    }

    /*
    ==================================================
    📅 RANGE (Filtro semestral basado en tus fechas)
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

        // Consultamos directamente tu tabla de caché optimizada
        $rows = DB::table('company_evolution_cache')
            ->where('year', $this->year)
            ->where('period_type', $this->filter)
            ->where('market_type', $this->type)
            ->whereBetween('start_date', [$range['start'], $range['end']])
            ->orderBy('start_date', 'asc')
            ->orderBy('ranking_position', 'asc')
            ->get();

        // Mapeamos exactamente los campos que solicita el reporte usando los datos precalculados
        return $rows->map(function ($item) {
            return [
                'ID'                  => $item->id,
                'Año'                 => $item->year,
                'Tipo Periodo'        => $item->period_type,
                'Mercado'             => $item->market_type,
                'Periodo'             => $item->period_label,
                'Fecha Inicio'        => $item->start_date,
                'Fecha Fin'           => $item->end_date,
                'Empresa Normalizada' => $item->company_normalized,
                'Empresa Original'    => $item->company_original,
                'Vacantes'            => $item->jobs,
                'Puesto Ranking'      => $item->ranking_position,
                'Total Mercado'       => $item->total_market_jobs,
                'Porcentaje (%)'      => $item->total_market_jobs > 0
                    ? round(($item->jobs / $item->total_market_jobs) * 100, 1)
                    : 0,
            ];
        });
    }

    /*
    ==================================================
    📄 HEADERS (Coinciden 1:1 en orden y contenido)
    ==================================================
    */
    public function headings(): array
    {
        return [
            'ID',
            'Año',
            'Tipo Periodo',
            'Mercado',
            'Periodo',
            'Fecha Inicio',
            'Fecha Fin',
            'Empresa Normalizada',
            'Empresa Original',
            'Vacantes',
            'Puesto Ranking',
            'Total Mercado',
            'Porcentaje (%)',
        ];
    }
}
