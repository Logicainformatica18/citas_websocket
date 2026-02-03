<?php

namespace App\Services;

use App\Models\ScraperRun;

class ScraperRunService
{
    public static function start(
        string $command,
        string $source,
        ?string $entity = null
    ): ScraperRun {
        return ScraperRun::create([
            'command'    => $command,
            'source'     => $source,
            'entity'     => $entity,
            'started_at' => now(),
            'status'     => 'running',
        ]);
    }

    public static function success(
        ScraperRun $run,
        int $found,
        int $inserted,
        int $skipped
    ): void {
        $run->update([
            'status'            => 'success',
            'finished_at'       => now(),
            'records_found'     => $found,
            'records_inserted'  => $inserted,
            'records_skipped'   => $skipped,
        ]);
    }

    public static function failed(
        ScraperRun $run,
        \Throwable $e
    ): void {
        $run->update([
            'status'        => 'failed',
            'finished_at'   => now(),
            'error_message' => $e->getMessage(),
        ]);
    }
}
