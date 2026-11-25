<?php

namespace App\Http\Controllers\Dashboard;

use AppServicesIndicatorsIndicatorScope;
use App\Services\Indicators\IndicatorScope;
use App\Exports\EntityEvolutionExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Concerns\RefreshesEntityRanking;
use App\Http\Controllers\Dashboard\JobMarketStatusController;
use App\Models\Prueba;
use App\Services\CertificationRankingService;
use App\Services\Ranking\EntityRankingService;
use App\Services\ScrapingStatusService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class RankingCertificacionesController extends Controller
{
    use RefreshesEntityRanking;

public function run(Request $request)
{
    try {

        $limit = (int) $request->get('limit', 10);
        $sleep = (int) $request->get('sleep', 5);

        Artisan::call('certifications:discover-gaps', [
            '--limit' => $limit,
            '--sleep' => $sleep,
        ]);

        $output = Artisan::output();

        return response()->json([
            'success' => true,
            'message' => 'Descubrimiento de certificaciones ejecutado correctamente',
            'output'  => $output,
        ]);

    } catch (\Throwable $e) {

        Log::error('RUN_CERT_GAP_ERROR', [
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error ejecutando el comando de certificaciones',
            'error'   => $e->getMessage(),
        ], 500);
    }
}
    public function storeWeights(Request $request)
    {
        /* ==================================================
           1. Validación
        ================================================== */
        $data = $request->validate([
            'labor_weight' => 'required|numeric|min:0|max:1',
            'trend_weight' => 'required|numeric|min:0|max:1',
        ]);

        if (round($data['labor_weight'] + $data['trend_weight'], 2) !== 1.00) {
            return response()->json([
                'message' => 'Las ponderaciones deben sumar 1.00',
            ], 422);
        }

        /* ==================================================
           2. Transacción segura
        ================================================== */
        DB::transaction(function () use ($data) {

            // 🔹 Desactivar ponderación activa actual (contexto certifications)
            Prueba::where('context', 'certifications')
                ->where('is_active', 1)
                ->update(['is_active' => 0]);

            // 🔹 Crear nueva ponderación activa
            Prueba::create([
                'labor_weight' => $data['labor_weight'],
                'trend_weight' => $data['trend_weight'],
                'context' => 'certifications',
                'is_active' => 1,
                'applied_at' => now(),
                'updated_by' => auth()->id(),
            ]);
        });

        /* ==================================================
           3. Respuesta
        ================================================== */
        return redirect()->back();


    }

/* ==================================================
   RANKING (página): lee certification_rankings con las ponderaciones vigentes.
   Lo llena `ranking:refresh-certifications` (CertificationRankingService). Antes se
   calculaba en vivo en cada carga, con otra fórmula de tendencias (menciones/total de
   reportes) y un rango de fechas que perdía el último día del semestre.
================================================== */
public function index(Request $request)
{
    $rankingService = $this->rankingService();

    $year   = (int) $request->get('year', 2026);
    $period = $request->get('period', 's1') === 's2' ? 's2' : 's1';
    $search = $request->get('search');
    $areas  = array_values(array_filter((array) $request->get('area', [])));

    $careers = $request->filled('career')
        ? array_values(array_filter((array) $request->career))
        : [];

    [$laborWeight, $trendWeight] = $rankingService->activeWeights();

    $meta = $rankingService->meta($year, $period);

    // 4 tarjetas por página, como antes. Sin cálculo previo: paginador vacío y aviso en el frontend.
    $ranking = $meta
        ? $rankingService->paginate($year, $period, $laborWeight, $trendWeight, $careers, $search, 4, $areas)
        : new LengthAwarePaginator([], 0, 4);

    // Las tarjetas muestran proveedor, nivel y área (están en market_entities, no en la tabla resumen)
    $extra = DB::table('market_entities')
        ->whereIn('id', collect($ranking->items())->pluck('id'))
        ->get(['id', 'vendor', 'level', 'category'])
        ->keyBy('id');
    $ranking->setCollection($ranking->getCollection()->map(function ($row) use ($extra) {
        $e = $extra[$row->id] ?? null;
        $row->vendor = $e->vendor ?? null;
        $row->level = $e->level ?? null;
        $row->category = $e->category ?? null;

        return $row;
    }));

    $calculatedAt = $meta
        ? Carbon::parse($meta->calculated_at)->timezone('America/Lima')
        : null;

    $categories = DB::table('market_entities')
        ->where('entity_type', 'certification')
        ->where('is_active', 1)
        ->whereNotNull('category')
        ->distinct()
        ->orderBy('category')
        ->pluck('category');

    $scrapingStatus = array_merge([
        'status'            => null,
        'started_at'        => null,
        'finished_at'       => null,
        'last_finished_at'  => null,
        'last_run_human'    => null,
        'source'            => null,
    ], ScrapingStatusService::getByEntity('certifications') ?? []);

    return Inertia::render(
        'DashboardRankingCertificaciones/RankingCertificacionesPage',
        [
            'ranking' => $ranking->withQueryString(),

            'filters' => [
                'year'   => $year,
                'search' => $search,
                'period' => $period,
                'area'   => $areas,
                'career' => $careers,
            ],

            'availableAreas' => $categories,
            'availableTrendCategories' => $categories,
            'availableCareers' => DB::table('careers')
                ->where('active', 1)
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),

            'weights' => [
                'laborWeight'  => round($laborWeight * 100, 1),
                'trendsWeight' => round($trendWeight * 100, 1),
            ],

            'jobMarketStatus' => JobMarketStatusController::get($year, $period),
            'scrapingStatus'  => $scrapingStatus,
            'canRefreshRanking' => (bool) $request->user()?->can('administrar'),

            'meta' => [
                'year'   => $year,
                'period' => $period,
                'periodo_label' =>
                    $period === 's1'
                        ? "Semestre 1 – Enero a Junio {$year}"
                        : "Semestre 2 – Julio a Diciembre {$year}",
                'vacantes_analizadas' => (int) ($meta->vacantes_analizadas ?? 0),
                'reportes_analizados' => (int) ($meta->reportes_analizados ?? 0),
                'has_data' => $meta !== null,
                // Fecha del último cálculo, en hora de Perú
                'calculated_at' => $calculatedAt?->toIso8601String(),
                'calculated_at_label' => $calculatedAt?->locale('es')->translatedFormat('d/m/Y h:i a'),
                'actualizado' => $calculatedAt?->toDateTimeString(),
            ],
        ]
    );
}

/* ==================================================
   BOTÓN "RECALCULAR RANKING": refresh() y refreshStatus() vienen del trait
   RefreshesEntityRanking (compartido con Lenguajes y Tecnologías).
================================================== */
protected function rankingService(): EntityRankingService
{
    return app(CertificationRankingService::class);
}

/* ==================================================
   JOBS POR CERTIFICACIÓN (LABORAL)
================================================== */
public function jobsByCertification(Request $request, int $marketEntityId)
{
    $perPage = min((int) $request->get('per_page', 10), 50);
    $page    = (int) $request->get('page', 1);


       // Mismo criterio que el ranking: sin ofertas excluidas por título ni asociaciones excluidas
       $jobs = IndicatorScope::associations(IndicatorScope::offers(DB::table('certification_job as cj')
    ->join('job_offers as j', 'j.id', '=', 'cj.job_offer_id'), 'j'), 'certification_job', 'cj')
    ->where('cj.market_entity_id', $marketEntityId)
    ->select(
        'j.id',
        'j.title',
        'j.company',
        'j.city',
        'j.country',
        'j.url'
    )
    ->orderByDesc('j.published_at')
    ->paginate($perPage, ['*'], 'page', $page);



    // 🔥 ESTO ES LO QUE TU FRONTEND ESPERA
    return response()->json([
        'data' => $jobs,
    ]);
}


/* ==================================================
   REPORTES / TENDENCIAS POR CERTIFICACIÓN
================================================== */
public function trendDetail(
    Request $request,
    int $marketEntityId
) {

    $year = (int) $request->get(
        'year',
        now()->year
    );

    $period = $request->get(
        'period',
        's1'
    );

    $semester =
        $period === 's1'
            ? 1
            : 2;

    $perPage = min(
        (int) $request->get('per_page', 10),
        50
    );

    $page = (int) $request->get(
        'page',
        1
    );

    /*
    ==================================================
    📦 REPORTES PAGINADOS
    ==================================================
    */

    $trends = IndicatorScope::trends(DB::table('entity_trends'))

        ->where(
            'market_entity_id',
            $marketEntityId
        )

        ->where(
            'year',
            $year
        )

        ->whereIn(
            'quarter',
            $semester === 1
                ? [1, 2]
                : [3, 4]
        )

        ->orderByDesc('trend_score')

        ->paginate(
            $perPage,
            [
                'id',
                'trend_score',
                'source_title',
                'source_url',
                'source_type',

                // 👇 NUEVO
                'discovered_by',

                'created_at',
            ],
            'page',
            $page
        );

    /*
    ==================================================
    📊 STATS GLOBALES
    ==================================================
    */

    $tavilyTotal = IndicatorScope::trends(DB::table('entity_trends'))

        ->where(
            'market_entity_id',
            $marketEntityId
        )

        ->where(
            'year',
            $year
        )

        ->whereIn(
            'quarter',
            $semester === 1
                ? [1, 2]
                : [3, 4]
        )

        ->where(
            'discovered_by',
            'LIKE',
            '%tavily%'
        )

        ->count();

    $gptTotal = IndicatorScope::trends(DB::table('entity_trends'))

        ->where(
            'market_entity_id',
            $marketEntityId
        )

        ->where(
            'year',
            $year
        )

        ->whereIn(
            'quarter',
            $semester === 1
                ? [1, 2]
                : [3, 4]
        )

        ->where(
            'discovered_by',
            'LIKE',
            '%gpt%'
        )

        ->count();

    /*
    ==================================================
    🚀 RESPONSE
    ==================================================
    */

    return response()->json([

        'data' =>
            $trends->items(),

        'stats' => [

            'tavily_total' =>
                $tavilyTotal,

            'gpt_total' =>
                $gptTotal,
        ],

        'pagination' => [

            'current_page' =>
                $trends->currentPage(),

            'last_page' =>
                $trends->lastPage(),

            'per_page' =>
                $trends->perPage(),

            'total' =>
                $trends->total(),

            'prev_page_url' =>
                $trends->previousPageUrl(),

            'next_page_url' =>
                $trends->nextPageUrl(),
        ],
    ]);
}

/* ==================================================
   EVOLUCIÓN (MODAL): fotos del ranking semestral en cada corte.
   Lee SOLO de certification_ranking_weekly(_meta), que llena ranking:refresh-certifications.
================================================== */
public function weeklyScores(Request $request)
{
    try {
        $year = (int) $request->get('year', now()->year);
        $filter = $request->get('filter', 'weekly');
        $page = max(1, (int) $request->get('page', 1));
        $perPage = max(1, min((int) $request->get('per_page', 6), 20));

        if (! in_array($filter, EntityRankingService::WINDOW_TYPES, true)) {
            $filter = 'weekly';
        }

        $result = $this->rankingService()->windowsPage($year, $filter, $page, $perPage);

        return response()->json([
            'success'    => true,
            'filter'     => $filter,
            'year'       => $year,
            'data'       => $result['data'],
            'pagination' => $result['pagination'],
        ]);
    } catch (\Throwable $e) {
        Log::error('[CERTIFICATION_EVOLUTION_ERROR]', [
            'message' => $e->getMessage(),
            'line'    => $e->getLine(),
            'file'    => $e->getFile(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'No se pudo cargar la evolución.',
        ], 500);
    }
}

/* Export en streaming (memoria constante) */
public function exportEvolution(Request $request)
{
    $year = (int) $request->get('year', 2026);
    $filter = $request->get('filter', 'weekly');
    $filter = in_array($filter, EntityRankingService::WINDOW_TYPES, true) ? $filter : 'weekly';

    return (new EntityEvolutionExport($this->rankingService(), $year, $filter, 'Certificación'))
        ->download("certification_evolution_{$filter}.xlsx");
}

}
