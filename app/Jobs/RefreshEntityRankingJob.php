<?php

namespace App\Jobs;

use App\Services\Ranking\EntityRankingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Recalcula un ranking desde el botón "Recalcular ranking". Usa el mismo servicio
 * (y el mismo lock) que el comando ranking:refresh-{tipo}.
 * Con $year/$period recalcula solo ese semestre (ranking + sus fotos de evolución).
 */
class RefreshEntityRankingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Igual al --timeout del worker de producción (170 s). El lock evita ejecuciones solapadas. */
    public int $timeout = 170;

    /** Sin reintentos: si falla, el usuario puede volver a pulsar el botón. */
    public int $tries = 1;

    /** @param  string  $rankingType  clave de config/entity_rankings.php */
    public function __construct(
        public string $rankingType,
        public ?int $year = null,
        public ?string $period = null,
    ) {
    }

    public function handle(): void
    {
        // null = otro recálculo tiene el lock; no es un error.
        EntityRankingService::for($this->rankingType)->refreshWithLock($this->year, $this->period);
    }
}
