<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CertificationsWeeklyExport implements FromCollection, WithHeadings
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
        // 1. Consultar la tabla de caché de certificaciones extrayendo todas las métricas analíticas
        $rows = DB::table('certification_evolution_cache as cec')
            ->join('market_entities as me', 'me.id', '=', 'cec.market_entity_id')
            ->where('cec.year', $this->year)
            ->where('cec.period_type', $this->filter)
            ->select([
                'cec.period_label as periodo',
                'cec.start_date as fecha_inicio',
                'cec.end_date as fecha_fin',
                'me.name as certificacion',
                'cec.jobs as vacantes_certificacion',
                'cec.trend_reports as reportes_tendencia',
                'cec.labor_score as puntaje_laboral',
                'cec.trend_score as puntaje_tendencia',
                'cec.final_score as puntaje_final',
                'cec.ranking_position as posicion_ranking'
            ])
            // Ordenamos cronológicamente por la fecha de inicio y luego por el puesto del ranking
            ->orderBy('cec.start_date', 'asc')
            ->orderBy('cec.ranking_position', 'asc')
            ->get();

        /*
        ==================================================
        🟢 TOTALES REUNIDOS POR CORTE
        ==================================================
        Agrupamos por la fecha de inicio del periodo para calcular el acumulado total
        de ofertas reflejadas en ese corte exacto (sumando 'vacantes_certificacion').
        */
        $totalsPerPeriod = $rows->groupBy('fecha_inicio')->map(function ($group) {
            return $group->sum('vacantes_certificacion');
        });

        // 2. Construir la colección mapeada fila por fila para el reporte de Excel
        $export = collect();

        foreach ($rows as $row) {
            $export->push([
                'Periodo'                    => $row->periodo,
                'Fecha Inicio'               => $row->fecha_inicio,
                'Fecha Fin'                  => $row->fecha_fin,
                'Total Vacantes Reales'      => $totalsPerPeriod[$row->fecha_inicio] ?? 0,
                'Certificación'              => $row->certificacion,
                'Vacantes con Certificación' => $row->vacantes_certificacion,
                'Reportes Tendencia'         => $row->reportes_tendencia,
                'Puntaje Laboral'            => $row->puntaje_laboral,
                'Puntaje Tendencia'          => $row->puntaje_tendencia,
                'Puntaje Final'              => $row->puntaje_final,
                'Posición Ranking'           => $row->posicion_ranking,
            ]);
        }

        return $export;
    }

    public function headings(): array
    {
        // Encabezados en español consistentes y ordenados con el resto del ecosistema de analíticas
        return [
            'Periodo',
            'Fecha Inicio',
            'Fecha Fin',
            'Total Vacantes Reales',
            'Certificación',
            'Vacantes con Certificación',
            'Reportes Tendencia',
            'Puntaje Laboral',
            'Puntaje Tendencia',
            'Puntaje Final',
            'Posición Ranking',
        ];
    }
}