<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SeniorityEvolutionExport implements FromCollection, WithHeadings
{
    protected $range;

    public function __construct($range)
    {
        $this->range = $range;
    }

    public function collection()
    {
        return DB::table('job_offers as jo')
            ->whereBetween('jo.created_at', [
                $this->range['start'],
                $this->range['end']
            ])
            ->whereIn(DB::raw('LOWER(TRIM(jo.seniority))'), ['junior','mid','senior'])
            ->select(
                DB::raw("DATE(jo.created_at) as fecha"),
                DB::raw("UPPER(jo.seniority) as nivel"),
                DB::raw("COUNT(*) as vacantes")
            )
            ->groupBy('fecha', 'nivel')
            ->orderBy('fecha')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Nivel',
            'Vacantes',
        ];
    }
}