<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CourseCCTCIndicatorController extends Controller
{
    /* =====================================================
       INDEX
    ===================================================== */
    public function index(Request $request)
    {
        [
            $careerId,
            $year,
            $period,
            $quarter,
            $range
        ] = $this->resolveParams($request);

        $availableCareers = $this->getAvailableCareers();
        $meta = $this->getGlobalMeta($year, $period);

        if (!$careerId) {
            return $this->renderEmpty(
                $availableCareers,
                $meta,
                $year,
                $period
            );
        }

        $courses = $this->getCoursesCCTC(
            $careerId,
            $year,
            $quarter
        );

        return Inertia::render(
            'DashboardCourseAlignment/CourseAlignmentIndicatorPage',
            [
                'filters' => [
                    'career_id' => $careerId,
                    'year' => $year,
                    'period' => $period,
                ],
                'availableCareers' => $availableCareers,
                'courses' => $courses,
                'meta' => $meta,
            ]
        );
    }

    /* =====================================================
       CORE CCTC
    ===================================================== */
    private function getCoursesCCTC(
        int $careerId,
        int $year,
        int $quarter
    )
    {
        $rows = DB::table('career_course as cc')
            ->join('courses as c', 'c.id', '=', 'cc.course_id')

            ->leftJoin('competency_course as compc',
                'compc.course_id', '=', 'c.id')

            ->leftJoin('competency_market_entity as cme',
                'cme.competency_id', '=', 'compc.competency_id')

            ->leftJoin('market_entities as me', function ($join) {
                $join->on('me.id', '=', 'cme.market_entity_id')
                     ->whereIn('me.entity_type',
                        ['technology','language','methodology']);
            })

            ->leftJoin('entity_trends as et', function ($join) use ($year, $quarter) {
                $join->on('et.market_entity_id', '=', 'me.id')
                     ->where('et.year', $year)
                     ->where('et.quarter', $quarter);
            })

            ->where('cc.career_id', $careerId)

            ->groupBy('c.id', 'c.name')

            ->select(
                'c.id',
                'c.name',
                DB::raw('COUNT(DISTINCT CASE WHEN et.id IS NOT NULL THEN me.entity_type END) as cctc')
            )
            ->get();

        return $rows->map(function ($row) {

            $level = match (true) {
                $row->cctc == 0 => 'not_aligned',
                $row->cctc == 1 => 'aligned',
                $row->cctc == 2 => 'highly_aligned',
                $row->cctc >= 3 => 'strategically_aligned',
            };

            return [
                'id' => $row->id,
                'name' => $row->name,
                'cctc' => (int) $row->cctc,
                'level' => $level,
            ];
        })
        ->sortByDesc('cctc')
        ->values();
    }

    /* =====================================================
       HELPERS (MISMA LÓGICA QUE TU OTRO INDICADOR)
    ===================================================== */
    private function resolveParams(Request $request): array
    {
        $careerId = (int) $request->get('career_id');
        $year     = (int) $request->get('year', 2026);
        $period   = $request->get('period', 's1');
        $quarter  = $period === 's1' ? 1 : 4;

        return [
            $careerId,
            $year,
            $period,
            $quarter,
            null
        ];
    }

    private function getAvailableCareers()
    {
        return DB::table('careers')
            ->where('active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    private function getGlobalMeta(int $year, string $period): array
    {
        return [
            'year' => $year,
            'period' => $period,
            'periodo_label' => $period === 's1'
                ? "Semestre 1 – Enero a Junio {$year}"
                : "Semestre 2 – Julio a Diciembre {$year}",
            'reportes_analizados' => DB::table('entity_trends')
                ->where('year', $year)
                ->where('quarter', $period === 's1' ? 1 : 4)
                ->count(),
            'actualizado' => now()->toDateTimeString(),
        ];
    }

    private function renderEmpty($careers, $meta, $year, $period)
    {
        return Inertia::render(
            'DashboardCourseAlignment/CourseAlignmentIndicatorPage',
            [
                'filters' => [
                    'career_id' => null,
                    'year' => $year,
                    'period' => $period,
                ],
                'availableCareers' => $careers,
                'courses' => [],
                'meta' => $meta,
            ]
        );
    }
}
