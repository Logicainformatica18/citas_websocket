<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TechnologyEvolutionExport implements FromCollection, WithHeadings
{
    protected int $year;
    protected string $filter;

    public function __construct(int $year, string $filter = 'weekly')
    {
        $this->year = $year;
        $this->filter = $filter; 
    }

    public function collection(): Collection
    {
        // 1. Consultar la tabla de caché incluyendo absolutamente todos los campos relevantes
        $rows = DB::table('technology_evolution_cache as tec')
            ->join('market_entities as me', 'me.id', '=', 'tec.market_entity_id')
            ->where('tec.year', $this->year)
            ->where('tec.period_type', $this->filter)
            ->select([
                'tec.period_label as periodo',
                'tec.start_date as fecha_inicio',
                'tec.end_date as fecha_fin',
                'me.name as tecnologia',
                'tec.jobs as vacantes_tecnologia',
                'tec.trend_reports as reportes_tendencia',
                'tec.labor_score as puntaje_laboral',
                'tec.trend_score as puntaje_tendencia',
                'tec.final_score as puntaje_final',
                'tec.ranking_position as posicion_ranking'
            ])
            // Ordenamos cronológicamente por la fecha del corte y por el puesto en el ranking
            ->orderBy('tec.start_date', 'asc')
            ->orderBy('tec.ranking_position', 'asc')
            ->get();

        /*
        ==================================================
        🟢 TOTALES REUNIDOS POR CORTE
        ==================================================
        Agrupamos por la fecha de inicio del periodo para calcular el total 
        de vacantes reales únicas en ese corte exacto (sumando 'vacantes_tecnologia').
        */
        $totalsPerPeriod = $rows->groupBy('fecha_inicio')->map(function ($group) {
            return $group->sum('vacantes_tecnologia');
        });

        // 2. Construir la colección formateada para Laravel Excel con todos los campos reales
        $export = collect();

        foreach ($rows as $row) {
            $export->push([
                'Periodo'               => $row->periodo,
                'Fecha Inicio'          => $row->fecha_inicio,
                'Fecha Fin'             => $row->fecha_fin,
                'Total Vacantes Reales' => $totalsPerPeriod[$row->fecha_inicio] ?? 0,
                'Tecnología'            => $row->tecnologia,
                'Vacantes Tecnología'   => $row->vacantes_tecnologia,
                'Reportes Tendencia'    => $row->reportes_tendencia,
                'Puntaje Laboral'       => $row->puntaje_laboral,
                'Puntaje Tendencia'     => $row->puntaje_tendencia,
                'Puntaje Final'         => $row->puntaje_final,
                'Posición Ranking'      => $row->posicion_ranking,
            ]);
        }

        return $export;
    }

    public function headings(): array
    {
        // Encabezados en español organizados perfectamente para el reporte Excel de Tecnologías
        return [
            'Periodo',
            'Fecha Inicio',
            'Fecha Fin',
            'Total Vacantes Reales',
            'Tecnología',
            'Vacantes Tecnología',
            'Reportes Tendencia',
            'Puntaje Laboral',
            'Puntaje Tendencia',
            'Puntaje Final',
            'Posición Ranking',
        ];
    }
}