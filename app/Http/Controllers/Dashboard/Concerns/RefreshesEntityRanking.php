<?php

namespace App\Http\Controllers\Dashboard\Concerns;

use App\Jobs\RefreshEntityRankingJob;
use App\Services\Ranking\EntityRankingService;
use Illuminate\Http\Request;

/**
 * Botón "Recalcular ranking": encola RefreshEntityRankingJob (QUEUE_CONNECTION=database,
 * con worker en producción) y responde de inmediato; el frontend consulta refreshStatus().
 * Rutas protegidas con permission:administrar y throttle.
 *
 * Recalcula el SEMESTRE QUE SE ESTÁ VIENDO (su ranking y sus fotos de evolución): en
 * Tecnologías el recálculo completo (6 semestres) tarda ~100 s, más que el retry_after
 * de la cola (90 s); un semestre tarda ~25 s. El recálculo completo lo hace el comando
 * ranking:refresh-* (07:00 y al final de jobs:run-all).
 */
trait RefreshesEntityRanking
{
    abstract protected function rankingService(): EntityRankingService;

    public function refresh(Request $request)
    {
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'min:' . EntityRankingService::FIRST_YEAR, 'max:' . (now()->year + 1)],
            'period' => ['nullable', 'in:s1,s2'],
        ]);

        $service = $this->rankingService();

        if ($service->isBusy()) {
            return response()->json([
                'status'  => 'busy',
                'message' => 'Ya hay un recálculo del ranking en curso.',
            ], 409);
        }

        // Sin parámetros: el semestre actual.
        $year = (int) ($data['year'] ?? now()->year);
        $period = $data['period'] ?? (now()->month <= 6 ? 's1' : 's2');

        $service->markQueued();
        RefreshEntityRankingJob::dispatch($service->type(), $year, $period);

        return response()->json([
            'status'  => 'queued',
            'message' => 'Recálculo iniciado.',
            'year'    => $year,
            'period'  => $period,
        ], 202);
    }

    public function refreshStatus()
    {
        return response()->json($this->rankingService()->status());
    }
}
