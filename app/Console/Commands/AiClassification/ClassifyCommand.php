<?php

namespace App\Console\Commands\AiClassification;

use App\Services\AiClassification\AiSettings;
use App\Services\AiClassification\OfferClassifier;
use App\Services\AiClassification\PromptTester;
use App\Services\AiClassification\Prompts;
use App\Services\AiClassification\RunLog;
use App\Services\AiClassification\TrendClassifier;
use App\Services\Indicators\IndicatorScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clasifica con IA lo NUEVO (id posterior al corte del histórico) en estado pendiente, con llamadas directas.
 * Corre en jobs:run-all después de job-offers:apply-title-exclusions y antes de ranking:refresh-*. También
 * ejecuta las pruebas de versión que el módulo dejó pendientes. Ver PLAN_RANKINGS.md, Bloque 10.
 */
class ClassifyCommand extends Command
{
    protected $signature = 'ai:classify
        {--kind=all : offers | trends | all}
        {--limit= : elementos por tipo (por defecto config ai_classification.sync_max_items)}
        {--reclassify-version= : número de versión del prompt: lo clasificado por la IA con esa versión vuelve a pendiente (requiere --kind)}
        {--dry-run : solo cuenta y estima el costo, sin llamar a la IA ni cambiar nada}';

    protected $description = 'Clasificación con IA de ofertas (TI / no TI) y tendencias (relevante / ruido) nuevas';

    public function handle(OfferClassifier $offers, TrendClassifier $trends, PromptTester $tester): int
    {
        if (! Schema::hasTable(AiSettings::TABLE)) {
            $this->warn('Faltan las tablas de la clasificación con IA (migraciones 2026_11_25_000004-6): no se hace nada.');

            return self::SUCCESS;
        }
        $kinds = $this->kinds();
        if ($kinds === null) {
            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry-run');
        $limit = (int) ($this->option('limit') ?: config('ai_classification.sync_max_items', 5000));
        $run = new RunLog('ai:classify', implode(',', $kinds), $this->options(), $dry);
        $enabled = AiSettings::enabled();
        if (! $enabled) {
            $this->warn('Interruptor de emergencia: clasificación con IA APAGADA (solo reglas y herencia por título, sin llamadas).');
        }

        try {
            if (! $dry && $enabled) {
                foreach (DB::table('ai_prompt_tests')->where('status', 'pendiente')->orderBy('id')->get() as $test) {
                    $this->line("Prueba de versión #{$test->id} ({$test->kind}, {$test->sample_size} elementos)…");
                    $tester->run($test, $run);
                }
            }

            foreach ($kinds as $kind) {
                $version = Prompts::active($kind);
                $scope = $kind === 'offers' ? OfferClassifier::scopeNew() : TrendClassifier::scopeNew();
                $classifier = $kind === 'offers' ? $offers : $trends;
                if ($n = $this->option('reclassify-version')) {
                    $reset = $classifier->resetVersion(Prompts::versionId($kind, (int) $n), $scope, $dry);
                    $this->info(($dry ? 'Volverían' : 'Vuelven') . " a pendiente {$reset} elementos de {$kind} clasificados con la versión {$n}");
                    $run->summary[$kind]['reclassify_reset'] = $reset;
                }
                $run->summary[$kind] = ($run->summary[$kind] ?? []) + $this->runKind($kind, $classifier, $scope, $limit, $version, $run, $dry, $enabled);
            }
        } catch (\Throwable $e) {
            $run->error($e->getMessage());
            $run->finish('error');
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $dry && ($run->c['processed'] > 0)) {
            IndicatorScope::bumpCacheVersion();
        }
        $run->finish();
        $this->table(['Procesados', 'Heredados', 'Por regla', 'Llamadas', 'Tokens entrada', 'Tokens salida', 'Costo USD'],
            [[$run->c['processed'], $run->c['inherited'], $run->c['by_rule'], $run->c['calls'], $run->c['tokens_in'], $run->c['tokens_out'], round($run->c['cost_usd'], 4)]]);
        $this->line(json_encode($run->summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        foreach ($run->errors as $err) {
            $this->warn($err);
        }

        return self::SUCCESS;
    }

    private function runKind(string $kind, $classifier, array $scope, int $limit, object $version, RunLog $run, bool $dry, bool $enabled): array
    {
        if ($dry) {
            if ($kind === 'offers') {
                $plan = $classifier->plan($scope, $limit);

                return ['pending_considered' => $plan['considered'], 'inheritable' => $plan['inherit'], 'titles' => count($plan['title']),
                    'offers_with_description' => count($plan['offer']), 'generic_without_description' => count($plan['rule']),
                    'estimate' => $classifier->estimate($plan, $version, false)];
            }
            $rules = $classifier->applyRules($scope, null, true);
            $ids = $classifier->pendingIds($scope, $limit, true);

            return ['by_rule' => $rules, 'to_ai' => count($ids), 'estimate' => $classifier->estimate(count($ids), $version, false)];
        }
        if (! $enabled) {
            if ($kind === 'offers') {
                $classifier->fillTitleKeys($scope);
                $n = $classifier->inherit($scope);
                $run->add('inherited', $n);
                $run->add('processed', $n);

                return ['inherited' => $n];
            }
            $n = $classifier->applyRules($scope, $run->id);
            $run->add('by_rule', $n);
            $run->add('processed', $n);

            return ['by_rule' => $n];
        }

        return $classifier->runSync($scope, $limit, $version, $run);
    }

    private function kinds(): ?array
    {
        $kind = (string) $this->option('kind');
        $kinds = $kind === 'all' ? Prompts::KINDS : [$kind];
        if (array_diff($kinds, Prompts::KINDS)) {
            $this->error('--kind debe ser offers, trends o all');

            return null;
        }
        if ($this->option('reclassify-version') && count($kinds) !== 1) {
            $this->error('--reclassify-version requiere --kind=offers o --kind=trends (cada tipo tiene sus versiones)');

            return null;
        }

        return $kinds;
    }
}
