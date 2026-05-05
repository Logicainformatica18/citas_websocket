<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SeniorityEvolutionCareerExport implements FromCollection, WithHeadings
{
    protected $year;
    protected $period;
    protected $careers;

    public function __construct($year, $period, $careers = [])
    {
        $this->year = $year;
        $this->period = $period;
        $this->careers = (array) $careers;
    }

    private function getRange()
    {
        return $this->period === 's1'
            ? ['start' => "{$this->year}-01-01", 'end' => "{$this->year}-06-30"]
            : ['start' => "{$this->year}-07-01", 'end' => "{$this->year}-12-31"];
    }

    public function collection()
    {
        $range = $this->getRange();

        $rows = DB::table('job_offers as jo')
            ->join('technology_job as tj', 'tj.job_offer_id', '=', 'jo.id')
            ->join('course_technology as ct', 'ct.technology_id', '=', 'tj.technology_id')
            ->join('career_course as cc', 'cc.course_id', '=', 'ct.course_id')
            ->join('careers as c', 'c.id', '=', 'cc.career_id')
            ->where(function ($q) use ($range) {
                $q->whereBetween('jo.published_at', [$range['start'], $range['end']])
                  ->orWhere(function ($q2) use ($range) {
                      $q2->whereNull('jo.published_at')
                         ->whereBetween('jo.created_at', [$range['start'], $range['end']]);
                  });
            })
            ->when(!empty($this->careers), function ($q) {
                $q->whereIn('c.slug', $this->careers);
            })
            ->select(
                DB::raw("DATE(jo.created_at) as fecha"),
                'c.name as carrera',
                DB::raw('COUNT(DISTINCT jo.id) as vacantes')
            )
            ->groupBy('fecha', 'carrera')
            ->orderBy('fecha')
            ->get();

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Carrera',
            'Vacantes',
        ];
    }
}