<?php

namespace App\Jobs;

use App\Services\Indicators\IndicatorRefresher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Ejecuta UN paso del recálculo de indicadores (< 60 s) y encola el resto: así ningún Job pasa
 * del --timeout=170 del worker ni del retry_after = 90 de la cola database. Cola por defecto.
 */
class RefreshIndicatorsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 170;

    public int $tries = 1;

    /** @param array<int, array> $steps */
    public function __construct(public array $steps)
    {
    }

    public function handle(): void
    {
        $step = array_shift($this->steps);
        if ($step === null) {
            return;
        }

        $t = microtime(true);
        $summary = IndicatorRefresher::run($step);
        Log::info('[INDICATORS] Paso recalculado', [
            'paso' => IndicatorRefresher::label($step),
            's' => round(microtime(true) - $t, 1),
            'resumen' => $summary,
            'pendientes' => count($this->steps),
        ]);

        if ($this->steps !== []) {
            self::dispatch($this->steps);
        }
    }
}
