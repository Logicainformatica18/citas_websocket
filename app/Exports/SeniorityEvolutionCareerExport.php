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
        $this->filter = $filter; // Opciones: 'weekly', 'biweekly', 'monthly'
        $this->careers = $careers;
    }

    /**
     * Define los rangos de fecha del semestre para restringir la consulta de la caché
     */
    private function getRange(): array
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

        $rows = DB::table('career_evolution_cache')
            ->where('year', $this->year)
            ->where('period_type', $this->filter)
            ->whereBetween('start_date', [$range['start'], $range['end']])
            // Filtro dinámico opcional si se seleccionaron carreras específicas por su Slug
            ->when(!empty($this->careers), function ($q) {
                $q->whereIn('career_slug', $this->careers);
            })
            // Ordenamos cronológicamente por la fecha de corte y por relevancia de vacantes
            ->orderBy('start_date', 'asc')
            ->orderBy('jobs', 'desc')
            ->get();

        // Mapeamos todos los campos disponibles en la estructura de la caché
        return $rows->map(function ($row) {
            return [
                'Año'                  => $row->year,
                'Tipo de Periodo'      => ucfirst($row->period_type),
                'Etiqueta'             => $row->period_label,
                'Fecha Inicio'         => $row->start_date,
                'Fecha Fin'            => $row->end_date,
                'ID Carrera'           => $row->career_id,
                'Carrera'              => $row->career_name,
                'Slug Carrera'         => $row->career_slug,
                'Vacantes por Carrera' => $row->jobs,
                'Mercado Global Total' => $row->total_market_jobs,
                'Porcentaje (%)'       => number_format($row->percentage, 2) . '%',
            ];
        });
    }

    /**
     * Encabezados de las columnas del archivo Excel
     */
    public function headings(): array
    {
        return [
            'Año',
            'Tipo de Periodo',
            'Etiqueta',
            'Fecha Inicio',
            'Fecha Fin',
            'ID Carrera',
            'Carrera',
            'Slug Carrera',
            'Vacantes por Carrera',
            'Mercado Global Total',
            'Porcentaje (%)',
        ];
    }
}
