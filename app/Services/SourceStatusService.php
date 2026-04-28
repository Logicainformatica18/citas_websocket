<?php

namespace App\Services;

use App\Models\SourceStatus;
use Illuminate\Support\Facades\DB;

class SourceStatusService
{
    /**
     * 🔵 Inicia ejecución
     */
    public static function start(string $source, ?int $runId = null, array $config = [], ?string $apiUrl = null): void
    {
        SourceStatus::updateOrCreate(
            ['source' => $source],
            [
                'last_run_id' => $runId,
                'last_status' => 'running',
                'last_started_at' => now(),
                'last_finished_at' => null,
                'last_duration_seconds' => null,
                'last_error' => null,

                'last_records_found' => 0,
                'last_records_inserted' => 0,
                'last_records_skipped' => 0,

                'connection_status' => 'unknown',
                'last_connection_check' => now(),

                'api_url' => $apiUrl,
                'app_id' => $source,

                // opcional (si agregas columna JSON)
                'updated_at' => now(),
            ]
        );
    }

    /**
     * 🟢 Éxito
     */
    public static function success(
    string $source,
    ?int $runId,
    int $found,
    int $inserted,
    int $skipped,
    int $durationSeconds,
    array $extra = []
): void {
    SourceStatus::where('source', $source)->update([

        // 🔹 RUN INFO
        'last_run_id' => $runId,
        'last_status' => 'success',
        'last_finished_at' => now(),
        'last_duration_seconds' => $durationSeconds,

        // 🔹 LAST RUN METRICS
        'last_records_found' => $found,
        'last_records_inserted' => $inserted,
        'last_records_skipped' => $skipped,

        // 🔥 🔥 🔥 ACUMULADO (AQUÍ ESTÁ LA MAGIA)
        'total_records_found' => DB::raw("COALESCE(total_records_found,0) + $found"),
        'total_records_inserted' => DB::raw("COALESCE(total_records_inserted,0) + $inserted"),
        'total_records_skipped' => DB::raw("COALESCE(total_records_skipped,0) + $skipped"),

        'last_success_at' => now(),
        'last_error' => null,

        'success_count' => DB::raw('COALESCE(success_count,0) + 1'),

        'updated_at' => now(),
    ]);
}

    /**
     * 🔴 Fallo
     */
    public static function failed(
        string $source,
        ?int $runId,
        \Throwable $e,
        ?int $durationSeconds = null
    ): void {
        SourceStatus::where('source', $source)->update([
            'last_run_id' => $runId,
            'last_status' => 'failed',
            'last_finished_at' => now(),
            'last_duration_seconds' => $durationSeconds,

            'last_error' => substr($e->getMessage(), 0, 2000),
            'last_failed_at' => now(),

           'fail_count' => DB::raw('COALESCE(fail_count,0) + 1'),

            'updated_at' => now(),
        ]);
    }

    /**
     * 🌐 Estado de conexión API
     */
    public static function connectionOk(string $source): void
    {
        SourceStatus::where('source', $source)->update([
            'connection_status' => 'ok',
            'last_connection_check' => now(),
            'connection_error' => null,
        ]);
    }

    public static function connectionFailed(string $source, string $error): void
    {
        SourceStatus::where('source', $source)->update([
            'connection_status' => 'failed',
            'last_connection_check' => now(),
            'connection_error' => substr($error, 0, 2000),
        ]);
    }

    /**
     * 🧠 Update parcial (para progreso en tiempo real)
     */
    public static function progress(
        string $source,
        int $found = 0,
        int $inserted = 0,
        int $skipped = 0
    ): void {
        SourceStatus::where('source', $source)->update([
            'last_records_found' => $found,
            'last_records_inserted' => $inserted,
            'last_records_skipped' => $skipped,
        ]);
    }
}