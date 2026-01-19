<?php

namespace App\Http\Controllers\Trends;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class TrendTechnologyController extends Controller
{
    /**
     * 📊 Detalle completo de una tendencia
     * - Tecnologías asociadas
     * - Métricas de empleo
     */

    public function source(int $trendId)
{
    $trend = DB::table('technology_trends')
        ->where('id', $trendId)
        ->select(
            'id',
            'topic_name',
            'trend_score',
            'raw_data'
        )
        ->first();

    if (!$trend) {
        abort(404);
    }

    $raw = json_decode($trend->raw_data, true);

    if (!$raw || !isset($raw['intent'])) {
        return response()->json([
            'intent' => 'unknown',
            'title'  => $trend->topic_name,
            'score'  => $trend->trend_score,
            'origin' => null,
        ]);
    }

    return match ($raw['intent']) {

        /* =========================
           CERTIFICATION
        ========================= */
        'certification' => $this->mapCertificationSource($trend, $raw),

        /* =========================
           TECHNOLOGY (futuro)
        ========================= */
        'technology' => $this->mapTechnologySource($trend, $raw),

        /* =========================
           SKILL / TOPIC / OTROS
        ========================= */
        default => $this->mapGenericSource($trend, $raw),
    };
}


    public function show(int $trendId)
{
    // ===============================
    // 1. Trend base
    // ===============================
    $trend = DB::table('technology_trends')
        ->where('id', $trendId)
        ->select(
            'id',
            'topic_name',
            'topic_category',
            'year',
            'quarter',
            'trend_score',
            'raw_data'
        )
        ->first();

    if (!$trend) {
        abort(404);
    }

    // ===============================
    // 2. RAW DATA (SEMÁNTICA)
    // ===============================
    $raw     = json_decode($trend->raw_data, true);
    $payload = $raw['payload'] ?? [];

    // ===============================
    // 3. Tecnologías asociadas
    // ===============================
    $technologies = DB::table('technology_trend_technology as ttt')
        ->join('technologies as t', 't.id', '=', 'ttt.technology_id')
        ->where('ttt.technology_trend_id', $trendId)
        ->orderByDesc('ttt.confidence_score')
        ->get([
            't.id',
            't.name',
            'ttt.confidence_score',
            'ttt.source',
        ]);

    // ===============================
    // 4. Ofertas laborales REALES
    // ===============================
    $jobMetrics = DB::table('technology_trend_job as ttj')
        ->join('job_offers as j', 'j.id', '=', 'ttj.job_offer_id')
        ->where('ttj.technology_trend_id', $trendId)
        ->selectRaw('COUNT(DISTINCT j.id) as total_jobs')
        ->first();

    $hasJobs = ($jobMetrics?->total_jobs ?? 0) > 0;

    // ===============================
    // 5. Disponibilidad de TENDENCIA
    // ===============================
    $hasTrend =
        !empty($payload) && (
            !empty($payload['summary']) ||
            !empty($payload['key_drivers']) ||
            !empty($payload['source_links'])
        );

    // ===============================
    // 6. RESPONSE NORMALIZADA
    // ===============================
    return response()->json([
        'availability' => [
            'trend' => $hasTrend,
            'jobs'  => $hasJobs,
        ],

        'trend' => $hasTrend ? [
            'id'        => $trend->id,
            'title'     => $payload['topic_name'] ?? $trend->topic_name,
            'score'     => $payload['trend_score'] ?? $trend->trend_score,
            'timeframe' => ($trend->year && $trend->quarter)
                ? "{$trend->year}-Q{$trend->quarter}"
                : null,
            'regions'     => $payload['regions'] ?? [],
            'summary'     => $payload['summary'] ?? null,
            'key_drivers' => $payload['key_drivers'] ?? [],
            'sources'     => $payload['source_links'] ?? [],
        ] : null,

        'technologies' => $technologies,

        'market' => $hasJobs ? [
            'total_jobs' => (int) $jobMetrics->total_jobs,
        ] : null,
    ]);
}



    /**
     * 💼 Ofertas asociadas a la tendencia (paginadas)
     */
    public function jobs(Request $request, int $trendId)
    {
        $perPage = min((int) $request->get('per_page', 10), 50);

        return DB::table('technology_trend_job as ttj')
            ->join('job_offers as j', 'j.id', '=', 'ttj.job_offer_id')
            ->where('ttj.technology_trend_id', $trendId)
            ->orderByDesc('j.published_at')
            ->paginate($perPage, [
                'j.id',
                'j.title',
                'j.company',
                'j.country',
                'j.location',
                'j.modality',
                'j.salary_min',
                'j.salary_max',
                'j.source',
                'j.published_at',
                'j.url',
                'ttj.match_type',
                'ttj.confidence_score',
            ]);
    }
}
