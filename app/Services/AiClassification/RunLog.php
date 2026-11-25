<?php

namespace App\Services\AiClassification;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Registro de una corrida de un comando de clasificación (tabla ai_runs + log [AI_CLASSIFY]): elementos
 * procesados, heredados, por regla, llamadas, tokens y costo. El módulo muestra la última corrida y el costo.
 */
final class RunLog
{
    public int $id;

    public array $c = ['processed' => 0, 'inherited' => 0, 'by_rule' => 0, 'calls' => 0, 'tokens_in' => 0, 'tokens_out' => 0, 'cost_usd' => 0.0];

    public array $summary = [];

    public array $errors = [];

    public function __construct(public string $command, public ?string $kind, array $options, public bool $dry)
    {
        if ($kind !== null && str_contains($kind, ',')) {
            $this->kind = $kind = 'all';
        }
        // Solo las opciones usadas (sin las genéricas de Artisan)
        $options = array_filter(array_diff_key($options, array_flip(['help', 'silent', 'quiet', 'verbose', 'version', 'ansi', 'no-interaction', 'env'])),
            fn ($v) => $v !== null && $v !== false);
        $this->id = DB::table('ai_runs')->insertGetId([
            'command' => $command, 'kind' => $kind, 'options' => json_encode($options), 'dry_run' => $dry,
            'status' => 'en_curso', 'started_at' => now(),
        ]);
    }

    public function add(string $key, int|float $n): void
    {
        $this->c[$key] += $n;
    }

    /** Suma una llamada directa (o el uso de un lote) con su costo. */
    public function usage(string $model, int $in, int $cached, int $out, bool $batch = false, int $calls = 1): void
    {
        $this->c['calls'] += $calls;
        $this->c['tokens_in'] += $in;
        $this->c['tokens_out'] += $out;
        $this->c['cost_usd'] += Cost::usd($model, $in, $cached, $out, $batch);
    }

    public function error(string $message): void
    {
        $this->errors[] = $message;
        Log::warning("[AI_CLASSIFY] {$this->command}: {$message}");
    }

    public function finish(?string $status = null): void
    {
        $status ??= $this->errors ? 'error' : 'ok';
        DB::table('ai_runs')->where('id', $this->id)->update($this->c + [
            'cost_usd' => round($this->c['cost_usd'], 5),
            'status' => $status,
            'summary' => json_encode($this->summary, JSON_UNESCAPED_UNICODE),
            'errors' => $this->errors ? mb_substr(implode("\n", $this->errors), 0, 60000) : null,
            'finished_at' => now(),
        ]);
        Log::info("[AI_CLASSIFY] {$this->command}" . ($this->kind ? " {$this->kind}" : '') . ($this->dry ? ' (dry-run)' : ''),
            $this->c + ['summary' => $this->summary, 'errores' => count($this->errors)]);
    }
}
