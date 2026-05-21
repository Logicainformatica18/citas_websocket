<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Carbon\Carbon;

class SeniorityEvolutionExport implements FromCollection, WithHeadings
{
    protected array $range;
    protected string $filter;

    /**
     * Constructor del Export.
     * * @param array $range ['start' => 'YYYY-MM-DD', 'end' => 'YYYY-MM-DD']
     * @param string $filter 'weekly', 'biweekly' o 'monthly'
     */
    public function __construct(array $range, string $filter = 'weekly')
    {
        $this->range = $range;
        $this->filter = $filter;
    }

    /**
     * Recupera y formatea la colección de datos desde la tabla de caché.
     */
    public function collection()
    {
        // 1. Extraer los años límites del rango para aprovechar el índice compuesto (idx_seniority_cache_lookup)
        $startYear = Carbon::parse($this->range['start'])->year;
        $endYear = Carbon::parse($this->range['end'])->year;

        // 2. Ejecutar la consulta apuntando directamente a la tabla intermedia
        $cachedRecords = DB::table('seniority_evolution_cache')
            ->whereNull('career_id') // Filtrar por el universo GLOBAL
            ->where('period_type', $this->filter)
            ->whereBetween('year', [$startYear, $endYear])
            ->whereBetween('start_date', [$this->range['start'], $this->range['end']])
            ->orderBy('start_date', 'asc')
            ->get();

        // 3. Mapear cada registro respetando el esquema real de la base de datos
        return $cachedRecords->map(function ($row) {
            return [
                'id'           => (int) $row->id,
                // 'career_id'    => $row->career_id ?? 'NULL (Global)',
                // 'career_slug'  => $row->career_slug,
                'year'         => (int) $row->year,
                'period_type'  => $row->period_type,
                'period_label' => $row->period_label,
                'start_date'   => $row->start_date,
                'end_date'     => $row->end_date,
                'total_jobs'   => (int) $row->total_jobs,
                'junior_count' => (int) $row->junior_count,
                'mid_count'    => (int) $row->mid_count,
                'senior_count' => (int) $row->senior_count,
                'junior_pct'   => number_format($row->junior_pct, 2) . '%',
                'mid_pct'      => number_format($row->mid_pct, 2) . '%',
                'senior_pct'   => number_format($row->senior_pct, 2) . '%',
                'created_at'   => $row->created_at,
                'updated_at'   => $row->updated_at,
            ];
        });
    }

    /**
     * Encabezados limpios y profesionales para las columnas del Excel.
     */
    public function headings(): array
    {
        return [
            'ID',
            'Año',
            'Tipo de Periodo',
            'Etiqueta de Periodo',
            'Fecha de Inicio',
            'Fecha de Fin',
            'Total Vacantes (Acumulado)',
            'Cantidad Junior',
            'Cantidad Mid',
            'Cantidad Senior',
            'Porcentaje Junior',
            'Porcentaje Mid',
            'Porcentaje Senior',
            'Fecha de Creación',
            'Última Actualización',
        ];
    }
}