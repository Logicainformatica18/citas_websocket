<?php

namespace App\Http\Controllers\Trends;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrendTechnologyController extends Controller
{
    /**
     * ======================================================
     * 📌 SOURCE SEMÁNTICO DE TENDENCIA
     * ======================================================
     */
    public function source(int $trendId)
    {
        $trend = DB::table('technology_trends')
            ->where('id', $trendId)
            ->select('id', 'topic_name', 'trend_score', 'raw_data')
            ->first();

        if (! $trend) {
            abort(404, 'Trend not found');
        }

        $raw = json_decode($trend->raw_data, true);

        if (! $raw || ! isset($raw['intent'])) {
            return response()->json([
                'intent' => 'unknown',
                'title'  => $trend->topic_name,
                'score'  => $trend->trend_score,
                'origin' => null,
            ]);
        }

        return match ($raw['intent']) {
            'certification' => $this->mapCertificationSource($trend, $raw),
            'technology'    => $this->mapTechnologySource($trend, $raw),
            default         => $this->mapGenericSource($trend, $raw),
        };
    }

    /**
     * ======================================================
     * 📊 DETALLE COMPLETO DE TENDENCIA
     * ======================================================
     */
    public function show(int $trendId)
    {
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

        if (! $trend) {
            abort(404, 'Trend not found');
        }

        $raw     = json_decode($trend->raw_data, true);
        $payload = $raw['payload'] ?? [];

        /* ===============================
           Tecnologías asociadas
        =============================== */
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

        /* ===============================
           Métricas laborales (TENDENCIA)
        =============================== */
        $jobMetrics = DB::table('technology_trend_job as ttj')
            ->join('job_offers as j', 'j.id', '=', 'ttj.job_offer_id')
            ->where('ttj.technology_trend_id', $trendId)
            ->selectRaw('COUNT(DISTINCT j.id) as total_jobs')
            ->first();

        $hasJobs = ($jobMetrics?->total_jobs ?? 0) > 0;

        $hasTrend =
            !empty($payload) && (
                !empty($payload['summary']) ||
                !empty($payload['key_drivers']) ||
                !empty($payload['source_links'])
            );

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
     * ======================================================
     * 💼 OFERTAS LABORALES POR TENDENCIA (PAGINADAS)
     * ⚠️ SOLO PARA TENDENCIAS
     * ======================================================
     */
    public function jobs(Request $request, int $trendId)
    {
        $perPage = min((int) $request->get('per_page', 10), 50);

        $exists = DB::table('technology_trends')
            ->where('id', $trendId)
            ->exists();

        if (! $exists) {
            return response()->json([
                'message' => 'Invalid trend id'
            ], 400);
        }

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

    /**
     * ======================================================
     * 🚫 BLOQUEO EXPLÍCITO
     * NO SE PERMITE LABORAL DE CERTIFICACIONES AQUÍ
     * ======================================================
     */
    public function jobsByCertification()
    {
        return response()->json([
            'message' =>
                'This endpoint does not support certification jobs. ' .
                'Use RankingCertificacionesController@jobsByCertification.'
        ], 409);
    }
}
