<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ScrapingStatusService
{
    public static function getByEntity(string $entity): array
    {
        Carbon::setLocale('es');
        $now = Carbon::now();

        /* =====================================================
           ÚLTIMO REGISTRO (ESTADO ACTUAL)
        ===================================================== */

        $lastRun = DB::table('scraper_runs')
            ->where('entity', $entity)
            ->orderByDesc('finished_at')
            ->orderByDesc('started_at')
            ->first();

        if (!$lastRun) {
            return [
                'entity' => $entity,
                'exists' => false,
                'status' => 'never_run',
                'message' => 'Nunca se ha ejecutado este scraping',
            ];
        }

        $startedAt  = $lastRun->started_at
            ? Carbon::parse($lastRun->started_at)
            : null;

        $finishedAt = $lastRun->finished_at
            ? Carbon::parse($lastRun->finished_at)
            : null;

        /* =====================================================
           ÚLTIMA EJECUCIÓN FINALIZADA REAL (FALLBACK)
        ===================================================== */

        $lastFinishedRun = DB::table('scraper_runs')
            ->where('entity', $entity)
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->first();

        $lastFinishedAt = $lastFinishedRun?->finished_at
            ? Carbon::parse($lastFinishedRun->finished_at)
            : null;

        $durationSeconds = ($startedAt && $finishedAt)
            ? $startedAt->diffInSeconds($finishedAt)
            : null;

        /* =====================================================
           MÉTRICAS HISTÓRICAS
        ===================================================== */

        $runsStats = DB::table('scraper_runs')
            ->where('entity', $entity)
            ->selectRaw('
                COUNT(*) as total_runs,
                SUM(status = "success") as success_runs,
                SUM(status = "failed") as failed_runs,
                MAX(finished_at) as last_success_at
            ')
            ->first();

        $lastSuccessAt = $runsStats->last_success_at
            ? Carbon::parse($runsStats->last_success_at)
            : null;

        /* =====================================================
           FLAGS DE SALUD
        ===================================================== */

        $isRunning = $lastRun->status === 'running';
        $isFailed  = $lastRun->status === 'failed';

        $isStale = $lastFinishedAt
            ? $lastFinishedAt->diffInHours($now) > 24
            : false;

        $hasImpact = (int) $lastRun->records_inserted > 0;

        /* =====================================================
           RETURN (MISMO CONTRATO + FALLBACK CORRECTO)
        ===================================================== */

        return [
            'entity' => $entity,
            'exists' => true,

            /* -------- Estado -------- */
            'status' => $lastRun->status,
            'is_running' => $isRunning,
            'is_failed' => $isFailed,
            'is_stale' => $isStale,
            'has_impact' => $hasImpact,

            /* -------- Ejecución -------- */
            'command' => $lastRun->command,
            'source'  => $lastRun->source,

            'started_at' => $startedAt?->toDateTimeString(),
            'finished_at' => $finishedAt?->toDateTimeString(),

            // 🔥 ESTE ES EL CAMPO CLAVE PARA EL MODAL
            'last_finished_at' => $lastFinishedAt?->toDateTimeString(),

            // 🔥 HUMANO TAMBIÉN HACE FALLBACK
            'last_run_human' => $finishedAt
                ? $finishedAt->diffForHumans($now, ['parts' => 1])
                : ($lastFinishedAt
                    ? $lastFinishedAt->diffForHumans($now, ['parts' => 1])
                    : $startedAt?->diffForHumans($now, ['parts' => 1])),

            'duration_seconds' => $durationSeconds,
            'duration_human' => $durationSeconds
                ? gmdate('i:s', $durationSeconds)
                : null,

            /* -------- Registros -------- */
            'records' => [
                'found'     => (int) ($lastFinishedRun->records_found ?? 0),
                'inserted'  => (int) ($lastFinishedRun->records_inserted ?? 0),
                'skipped'   => (int) ($lastFinishedRun->records_skipped ?? 0),
            ],

            /* -------- Error -------- */
            'error_message' => $lastRun->error_message,

            /* -------- Historial -------- */
            'history' => [
                'total_runs'   => (int) $runsStats->total_runs,
                'success_runs' => (int) $runsStats->success_runs,
                'failed_runs'  => (int) $runsStats->failed_runs,
                'last_success_human' => $lastSuccessAt
                    ? $lastSuccessAt->diffForHumans($now, ['parts' => 1])
                    : null,
            ],
        ];
    }
}
