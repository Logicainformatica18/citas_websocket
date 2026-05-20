<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LanguagesEvolutionExport implements FromCollection, WithHeadings
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
        // 1. Consultar la tabla de caché de lenguajes usando exactamente los campos de tu CREATE TABLE
        $rows = DB::table('language_evolution_cache as lec')
            ->join('market_entities as me', 'me.id', '=', 'lec.market_entity_id')
            ->where('lec.year', $this->year)
            ->where('lec.period_type', $this->filter)
            ->select([
                'lec.period_label as periodo',
                'lec.start_date as fecha_inicio',
                'lec.end_date as fecha_fin',
                'me.name as lenguaje',
                'lec.jobs as vacantes_lenguaje',
                'lec.trend_reports as reportes_tendencia',
                'lec.labor_score as puntaje_laboral',
                'lec.trend_score as puntaje_tendencia',
                'lec.final_score as puntaje_final',
                'lec.ranking_position as posicion_ranking'
            ])
            ->orderBy('lec.start_date', 'asc')
            ->orderBy('lec.ranking_position', 'asc')
            ->get();

        /*
        ==================================================
        🟢 TOTALES REUNIDOS POR CORTE
        ==================================================
        Agrupamos por la fecha de inicio del periodo para calcular el total 
        de vacantes reales únicas en ese corte exacto (sumando 'vacantes_lenguaje').
        */
        $totalsPerPeriod = $rows->groupBy('fecha_inicio')->map(function ($group) {
            return $group->sum('vacantes_lenguaje');
        });

        // 2. Construir la colección formateada para el Excel con todas tus columnas reales
        $export = collect();

        foreach ($rows as $row) {
            $export->push([
                'Periodo'                => $row->periodo,
                'Fecha Inicio'           => $row->fecha_inicio,
                'Fecha Fin'              => $row->fecha_fin,
                'Vacantes Únicas Reales' => $totalsPerPeriod[$row->fecha_inicio] ?? 0,
                'Lenguaje'               => $row->lenguaje,
                'Menciones Lenguaje'     => $row->vacantes_lenguaje,
                'Reportes Tendencia'     => $row->reportes_tendencia,
                'Puntaje Laboral'        => $row->puntaje_laboral,
                'Puntaje Tendencia'      => $row->puntaje_tendencia,
                'Puntaje Final'          => $row->puntaje_final,
                'Posición Ranking'       => $row->posicion_ranking,
            ]);
        }

        return $export;
    }

    public function headings(): array
    {
        // Encabezados limpios, profesionales y en español ordenados para las columnas del Excel
        return [
            'Periodo',
            'Fecha Inicio',
            'Fecha Fin',
            'Vacantes Únicas Reales',
            'Lenguaje',
            'Menciones Lenguaje',
            'Reportes Tendencia',
            'Puntaje Laboral',
            'Puntaje Tendencia',
            'Puntaje Final',
            'Posición Ranking',
        ];
    }
}