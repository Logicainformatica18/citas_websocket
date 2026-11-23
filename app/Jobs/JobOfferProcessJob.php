<?php

namespace App\Jobs;

use App\Models\JobOfferProcess;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Base de los procesos en cola del módulo de ofertas (job_offer_processes). Cola por defecto
 * (la que ya atiende el worker del Observatorio: queue:work --timeout=170).
 *
 * Cada Job trabaja como máximo config('job_title_exclusions.slice_seconds') (60 s), guarda el avance en
 * el proceso y, si no terminó, encola el siguiente tramo. Así ningún Job se acerca al --timeout=170 del
 * worker ni al retry_after (90 s) de la cola database.
 */
abstract class JobOfferProcessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 170;

    /** Sin reintentos automáticos: el avance queda guardado y el usuario puede relanzar. */
    public int $tries = 1;

    public function __construct(public int $processId)
    {
    }

    /** Trabaja hasta $deadline; true = terminó. */
    abstract protected function step(JobOfferProcess $process, float $deadline): bool;

    public function handle(): void
    {
        $p = JobOfferProcess::find($this->processId);
        if ($p === null || $p->isFinished()) {
            return;
        }
        if ($p->status === 'queued') {
            $p->status = 'running';
            $p->started_at = now();
            $p->save();
        }

        $deadline = microtime(true) + (int) config('job_title_exclusions.slice_seconds', 60);

        try {
            $done = $this->step($p, $deadline);
            if ($done) {
                $p->status = 'done';
                $p->finished_at = now();
            }
            $p->save();
            if (! $done) {
                static::dispatch($this->processId);
            }
        } catch (\Throwable $e) {
            $this->markFailed($p, $e);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $p = JobOfferProcess::find($this->processId);
        if ($p && ! $p->isFinished()) {
            $this->markFailed($p, $e);
        }
    }

    private function markFailed(JobOfferProcess $p, \Throwable $e): void
    {
        $p->status = 'failed';
        $p->finished_at = now();
        $p->message = mb_substr($e->getMessage(), 0, 2000);
        $p->save();
        Log::error('[JOB_OFFER_PROCESS] Falló el proceso', ['id' => $p->id, 'type' => $p->type, 'error' => $e->getMessage()]);
    }
}
