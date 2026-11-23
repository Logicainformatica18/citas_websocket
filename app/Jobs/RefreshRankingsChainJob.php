<?php

namespace App\Jobs;

use App\Models\JobOfferProcess;
use App\Services\Ranking\EntityRankingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Recalcula los rankings (ranking:refresh-*) desde la interfaz, UN indicador y semestre por Job
 * (ranking + sus fotos de evolución). La unión de todos los pasos equivale al recálculo completo,
 * pero cada Job dura segundos (el completo de Tecnologías ~2 min superaría el retry_after de 90 s).
 * Cola por defecto. Si otro recálculo tiene el lock (botón o cron de las 07:00), espera 30 s y reintenta.
 *
 * @param  array<int, array{0: string, 1: int, 2: string}>  $steps  [tipo, año, semestre]
 */
class RefreshRankingsChainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 170;

    public int $tries = 1;

    private const MAX_WAITS = 20;

    public function __construct(public ?int $processId, public array $steps, public int $waits = 0)
    {
    }

    public function handle(): void
    {
        if ($this->steps === []) {
            return;
        }
        [$type, $year, $period] = $this->steps[0];
        $rest = array_slice($this->steps, 1);

        $result = EntityRankingService::for($type)->refreshWithLock($year, $period);

        if ($result === null && $this->waits < self::MAX_WAITS) {
            $this->track('waiting', "Esperando a otro recálculo de {$type}");
            self::dispatch($this->processId, $this->steps, $this->waits + 1)->delay(now()->addSeconds(30));

            return;
        }
        if ($result === null) {
            Log::warning('[JOB_OFFER_PROCESS] Recálculo omitido: el lock siguió ocupado', ['type' => $type, 'semester' => "{$year}-{$period}"]);
        }

        $this->track($rest === [] ? 'done' : 'running', "{$type} {$year}-{$period}", true);
        if ($rest !== []) {
            self::dispatch($this->processId, $rest);
        }
    }

    private function track(string $status, string $current, bool $advance = false): void
    {
        $p = $this->processId ? JobOfferProcess::find($this->processId) : null;
        if (! $p) {
            return;
        }
        $result = $p->result ?? [];
        $r = $result['rankings'] ?? ['done' => 0, 'total' => count($this->steps)];
        $r['status'] = $status;
        $r['current'] = $current;
        if ($advance) {
            $r['done'] = ($r['done'] ?? 0) + 1;
        }
        $result['rankings'] = $r;
        $p->result = $result;
        $p->save();
    }
}
