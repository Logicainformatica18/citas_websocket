<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use App\Models\Prueba;

class PeAlignmentIndicatorController extends Controller
{

public function competenciesByCareer(Request $request, int $careerId)
{
    $year    = (int) $request->get('year', 2025);
    $period  = $request->get('period', 's2');
    $quarter = $period === 's1' ? 1 : 4;

    $range = $this->getPeriodRange($period, $year);

    /* ==================================================
       COMPETENCIAS BASE
    ================================================== */
    $competencies = DB::table('competencies')
        ->where('career_id', $careerId)
        ->get(['id', 'name']);

    if ($competencies->isEmpty()) {
        return response()->json(['data' => []]);
    }

    $competencyIds = $competencies->pluck('id')->all();

    /* ==================================================
       MATCH MERCADO
    ================================================== */
  $marketLookup = DB::table('competency_job_offer as cjo')
    ->join('job_offers as j', 'j.id', '=', 'cjo.job_offer_id')
    ->whereIn('cjo.competency_id', $competencyIds)
    ->whereBetween('j.published_at', [$range['start'], $range['end']])
    ->distinct()
    ->pluck('cjo.competency_id')
    ->flip();


    /* ==================================================
       MATCH PROSPECTIVO
    ================================================== */
    $prospectiveLookup = collect(DB::select("
        SELECT DISTINCT c.id AS competency_id
        FROM competencies c
        JOIN career_course cc ON cc.career_id = c.career_id
        JOIN courses co ON co.id = cc.course_id

        LEFT JOIN course_technology ct ON ct.course_id = co.id
        LEFT JOIN technologies tech ON tech.id = ct.technology_id
            AND tech.enabled = 1

        LEFT JOIN course_language cl ON cl.course_id = co.id
        LEFT JOIN languages lang ON lang.id = cl.language_id
            AND lang.enabled = 1

        JOIN technology_trends tt
          ON (
            (tech.name IS NOT NULL
             AND LOWER(tt.associated_technologies)
                 LIKE CONCAT('%', LOWER(tech.name), '%'))
            OR
            (lang.name IS NOT NULL
             AND LOWER(tt.topic_name)
                 LIKE CONCAT('%', LOWER(lang.name), '%'))
          )

        WHERE c.career_id = ?
          AND tt.year = ?
          AND tt.quarter = ?
    ", [$careerId, $year, $quarter]))
        ->pluck('competency_id')
        ->map(fn () => true);

    /* ==================================================
       ARMADO FINAL
    ================================================== */
    $data = $competencies->map(function ($c) use ($marketLookup, $prospectiveLookup) {

        $market = isset($marketLookup[$c->id]);
        $prospective = isset($prospectiveLookup[$c->id]);

        return [
            'competency_id'   => (int) $c->id,
            'competency_name' => $c->name ?? '—',

            // 👇 booleans reales
            'market_match' => $market,
            'trend_match'  => $prospective,

            // score normalizado
            'pe_score' => round(
                ($market ? 0.7 : 0) + ($prospective ? 0.3 : 0),
                2
            ),
        ];
    })->values();

    return response()->json([
        'data' => $data,
    ]);
}


    /* =====================================================
       INDEX – INDICADOR PE
    ===================================================== */
public function index(Request $request)
{
    /* ==================================================
       🔰 0️⃣ ENTRADA AL MÉTODO
    ================================================== */
    Log::info('🟢 [PE-0] INDEX ENTRÓ', [
        'career_id' => $request->get('career_id'),
        'year'      => $request->get('year'),
        'period'    => $request->get('period'),
    ]);

    /* ==================================================
       1️⃣ PARÁMETROS BASE
    ================================================== */
    $careerId = (int) $request->get('career_id');

    $year    = (int) $request->get('year', 2025);
    $period  = $request->get('period', 's2');
    $quarter = $period === 's1' ? 1 : 4;

    Log::info('🟢 [PE-1] PARÁMETROS', [
        'careerId' => $careerId,
        'year'     => $year,
        'period'   => $period,
        'quarter'  => $quarter,
    ]);

    $range = $this->getPeriodRange($period, $year);

    Log::info('🟢 [PE-2] RANGO FECHAS', $range);

    /* ==================================================
       2️⃣ PONDERACIONES
    ================================================== */
    try {
        $activeWeights = Prueba::getActive('pe_alignment');
        Log::info('🟢 [PE-3] WEIGHTS OK', [
            'labor' => $activeWeights?->labor_weight,
            'trend' => $activeWeights?->trend_weight,
        ]);
    } catch (\Throwable $e) {
        Log::error('🔴 [PE-3] ERROR WEIGHTS', [
            'error' => $e->getMessage(),
        ]);
        $activeWeights = null;
    }

    $laborWeight = (float) ($activeWeights?->labor_weight ?? 0.70);
    $trendWeight = (float) ($activeWeights?->trend_weight ?? 0.30);

    /* ==================================================
       3️⃣ CARRERAS DISPONIBLES
    ================================================== */
    $availableCareers = DB::table('careers')
        ->where('active', 1)
        ->orderBy('name')
        ->get(['id', 'name', 'slug']);

    Log::info('🟢 [PE-4] CAREERS DISPONIBLES', [
        'count' => $availableCareers->count(),
    ]);

    /* ==================================================
       4️⃣ META GLOBAL
    ================================================== */
    $vacantesAnalizadas = DB::table('job_offers')
        ->whereBetween('published_at', [$range['start'], $range['end']])
        ->count();

    Log::info('🟢 [PE-5] VACANTES ANALIZADAS', [
        'count' => $vacantesAnalizadas,
    ]);

    $reportesAnalizados = $this->getTrendReportsCount($year, $quarter);

    Log::info('🟢 [PE-6] REPORTES ANALIZADOS', [
        'count' => $reportesAnalizados,
    ]);

    /* ==================================================
       5️⃣ SIN CARRERA SELECCIONADA
    ================================================== */
    if (!$careerId) {
        Log::warning('🟡 [PE-7] SIN CAREER_ID');
        return Inertia::render(
            'DashboardAlignCompetence/PeAlignmentIndicatorPage',
            [
                'filters' => [
                    'career_id' => null,
                    'year' => $year,
                    'period' => $period,
                ],
                'availableCareers' => $availableCareers,
                'weights' => [
                    'laborWeight'  => round($laborWeight * 100, 1),
                    'trendsWeight' => round($trendWeight * 100, 1),
                ],
                'summary' => null,
                'meta' => [
                    'year' => $year,
                    'period' => $period,
                    'periodo_label' => $period === 's1'
                        ? "Semestre 1 – Enero a Junio {$year}"
                        : "Semestre 2 – Julio a Diciembre {$year}",
                    'vacantes_analizadas' => $vacantesAnalizadas,
                    'reportes_analizados' => $reportesAnalizados,
                    'actualizado' => now()->toDateTimeString(),
                ],
            ]
        );
    }

    /* ==================================================
       6️⃣ TOTAL COMPETENCIAS
    ================================================== */
    $totalCompetencies = DB::table('competencies')
        ->where('career_id', $careerId)
        ->count();

    Log::info('🟢 [PE-8] TOTAL COMPETENCIAS', [
        'careerId' => $careerId,
        'total'    => $totalCompetencies,
    ]);

    /* ==================================================
       7️⃣ MERCADO
    ================================================== */
    $marketCompetencies = DB::table('competencies as c')
        ->join('competency_job_offer as cjo', 'cjo.competency_id', '=', 'c.id')
        ->join('job_offers as j', 'j.id', '=', 'cjo.job_offer_id')
        ->where('c.career_id', $careerId)
        ->whereBetween('j.published_at', [$range['start'], $range['end']])
        ->distinct('c.id')
        ->count('c.id');

    $marketPct = $totalCompetencies > 0
        ? round(($marketCompetencies / $totalCompetencies) * 100, 2)
        : 0;

    Log::info('🟢 [PE-9] MERCADO', [
        'matched' => $marketCompetencies,
        'pct'     => $marketPct,
    ]);

    /* ==================================================
       8️⃣ PROSPECTIVA (PUNTO CRÍTICO)
    ================================================== */
    Log::info('🔮 [PE-10] PROSPECTIVA START');

    $prospectiveCompetencies = DB::selectOne("
        SELECT COUNT(DISTINCT comp.id) AS total
        FROM competencies comp
        JOIN career_course cc ON cc.career_id = comp.career_id
        JOIN courses co ON co.id = cc.course_id
        JOIN (
            SELECT DISTINCT c.id AS competency_id
            FROM competencies c
            JOIN career_course cc1 ON cc1.career_id = c.career_id
            JOIN courses co1 ON co1.id = cc1.course_id
            JOIN course_technology ct ON ct.course_id = co1.id
            JOIN technologies tech ON tech.id = ct.technology_id
                AND tech.enabled = 1
            JOIN technology_trends tt
                ON LOWER(tt.associated_technologies)
                   LIKE CONCAT('%', LOWER(tech.name), '%')
            WHERE tt.year = ? AND tt.quarter = ?

            UNION

            SELECT DISTINCT c.id AS competency_id
            FROM competencies c
            JOIN career_course cc2 ON cc2.career_id = c.career_id
            JOIN courses co2 ON co2.id = cc2.course_id
            JOIN course_language cl ON cl.course_id = co2.id
            JOIN languages lang ON lang.id = cl.language_id
                AND lang.enabled = 1
            JOIN technology_trends tt2
                ON LOWER(tt2.topic_name)
                   LIKE CONCAT('%', LOWER(lang.name), '%')
            WHERE tt2.year = ? AND tt2.quarter = ?
        ) trends ON trends.competency_id = comp.id
        WHERE comp.career_id = ?
    ", [
        $year, $quarter,
        $year, $quarter,
        $careerId
    ])->total ?? 0;

    Log::info('🔮 [PE-11] PROSPECTIVA END', [
        'matched' => $prospectiveCompetencies,
    ]);

    $prospectivePct = $totalCompetencies > 0
        ? round(($prospectiveCompetencies / $totalCompetencies) * 100, 2)
        : 0;

    /* ==================================================
       9️⃣ ÍNDICE FINAL
    ================================================== */
    $finalIndex = round(
        ($laborWeight * $marketPct) +
        ($trendWeight * $prospectivePct),
        2
    );

    Log::info('🟢 [PE-12] FINAL INDEX', [
        'final' => $finalIndex,
    ]);

    /* ==================================================
       🔟 RENDER FINAL
    ================================================== */
    Log::info('🟢 [PE-13] RENDER INERTIA');

    return Inertia::render(
        'DashboardAlignCompetence/PeAlignmentIndicatorPage',
        [
            'filters' => [
                'career_id' => $careerId,
                'year' => $year,
                'period' => $period,
            ],
            'availableCareers' => $availableCareers,
            'weights' => [
                'laborWeight'  => round($laborWeight * 100, 1),
                'trendsWeight' => round($trendWeight * 100, 1),
            ],
            'summary' => [
                'total_competencies' => $totalCompetencies,
                'market' => [
                    'matched' => $marketCompetencies,
                    'percentage' => $marketPct,
                ],
                'prospective' => [
                    'matched' => $prospectiveCompetencies,
                    'percentage' => $prospectivePct,
                ],
                'final_index' => $finalIndex,
            ],
            'meta' => [
                'year' => $year,
                'period' => $period,
                'periodo_label' => $period === 's1'
                    ? "Semestre 1 – Enero a Junio {$year}"
                    : "Semestre 2 – Julio a Diciembre {$year}",
                'vacantes_analizadas' => $vacantesAnalizadas,
                'reportes_analizados' => $reportesAnalizados,
                'actualizado' => now()->toDateTimeString(),
            ],
        ]
    );
}






    /* =====================================================
       EMPLEOS RELACIONADOS A UNA COMPETENCIA
    ===================================================== */
    public function jobsByCompetency(Request $request, int $competencyId)
    {
        $year   = (int) $request->get('year', 2025);
        $period = $request->get('period', 's2');
        $range  = $this->getPeriodRange($period, $year);

        $jobs = DB::table('competency_job_offer as cjo')
            ->join('job_offers as j', 'j.id', '=', 'cjo.job_offer_id')
            ->where('cjo.competency_id', $competencyId)
            ->whereBetween('j.published_at', [$range['start'], $range['end']])
            ->select(
                'j.id',
                'j.title',
                'j.company',
                'j.location',
                'j.country',
                'j.modality',
                'j.salary_min',
                'j.salary_max',
                'j.source',
                'j.published_at',
                'j.url'
            )
            ->orderByDesc('j.published_at')
            ->paginate(10);

        return response()->json([
            'used_for_score' => true,
            'data' => $jobs,
        ]);
    }

    /* =====================================================
       TENDENCIAS RELACIONADAS A UNA COMPETENCIA
    ===================================================== */
    public function trendsByCompetency(Request $request, int $competencyId)
    {
        $year    = (int) $request->get('year', 2025);
        $period  = $request->get('period', 's2');
        $quarter = $period === 's1' ? 1 : 4;

        $trends = DB::select("
            SELECT DISTINCT
                tt.id,
                tt.topic_name,
                tt.trend_score,
                tt.year,
                tt.quarter,
                tt.source_title,
                tt.source_url,
                tt.source_type
            FROM competencies c
            JOIN career_course cc ON cc.career_id = c.career_id
            JOIN courses co ON co.id = cc.course_id

            LEFT JOIN course_technology ct ON ct.course_id = co.id
            LEFT JOIN technologies tech ON tech.id = ct.technology_id

            LEFT JOIN course_language cl ON cl.course_id = co.id
            LEFT JOIN languages lang ON lang.id = cl.language_id

            JOIN technology_trends tt
              ON (
                (tech.name IS NOT NULL AND JSON_SEARCH(
                    tt.associated_technologies,
                    'one',
                    tech.name
                ) IS NOT NULL)
                OR
                (lang.name IS NOT NULL AND
                 tt.topic_name LIKE CONCAT('%', lang.name, '%'))
              )

            WHERE c.id = ?
              AND tt.year = ?
              AND tt.quarter = ?
        ", [$competencyId, $year, $quarter]);

        return response()->json([
            'data' => $trends,
        ]);
    }

    /* =====================================================
       HELPERS
    ===================================================== */
    private function getTrendReportsCount(int $year, int $quarter): int
    {
        return DB::table('technology_trends')
            ->where('year', $year)
            ->where('quarter', $quarter)
            ->count();
    }

    private function getPeriodRange(string $period, int $year): array
    {
        return $period === 's1'
            ? ['start' => "$year-01-01", 'end' => "$year-06-30"]
            : ['start' => "$year-07-01", 'end' => "$year-12-31"];
    }
}
