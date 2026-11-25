<?php

namespace App\Console\Commands\AiClassification;

use App\Services\AiClassification\AiSettings;
use App\Services\AiClassification\BatchRunner;
use App\Services\AiClassification\OfferClassifier;
use App\Services\AiClassification\Prompts;
use App\Services\AiClassification\RunLog;
use App\Services\AiClassification\TrendClassifier;
use App\Services\Indicators\IndicatorScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico (id hasta el corte) con la Batch API de OpenAI (−50 %, respuesta en menos de 24 h):
 *   --submit   lo ejecuta el operador UNA vez: envía lo pendiente (ofertas sin LinkedIn, tendencias y, al final,
 *              LinkedIn) en lotes, con un tope de lotes en curso. Lo enviado queda en_proceso.
 *   --collect  en jobs:run-all: recoge los lotes terminados y aplica los resultados; después, si ya se hizo el
 *              --submit, envía lo que falte (incluida la segunda pasada con la descripción).
 *   --dry-run  cuenta y estima el costo sin enviar nada.
 */
class ClassifyHistoryCommand extends Command
{
    protected $signature = 'ai:classify-history
        {--submit : envía el histórico pendiente a la Batch API}
        {--collect : recoge los lotes terminados, aplica y continúa el envío}
        {--kind=all : offers | trends | all}
        {--limit= : elementos por tipo a considerar en el envío}
        {--max-in-flight=3 : lotes en curso a la vez}
        {--max-requests= : peticiones por lote (por defecto config ai_classification.batch_max_requests)}
        {--reclassify-version= : con --submit: lo clasificado por la IA con esa versión vuelve a pendiente (requiere --kind)}
        {--dry-run : solo cuenta y estima el costo}';

    protected $description = 'Clasificación con IA del histórico con la Batch API (enviar / recoger)';

    public function handle(BatchRunner $runner, OfferClassifier $offers, TrendClassifier $trends): int
    {
        if (! Schema::hasTable(AiSettings::TABLE)) {
            $this->warn('Faltan las tablas de la clasificación con IA (migraciones 2026_11_25_000004-6): no se hace nada.');

            return self::SUCCESS;
        }
        $dry = (bool) $this->option('dry-run');
        $submit = (bool) $this->option('submit');
        $collect = (bool) $this->option('collect');
        if (! $submit && ! $collect && ! $dry) {
            $this->error('Indica --submit, --collect o --dry-run');

            return self::FAILURE;
        }
        $kind = (string) $this->option('kind');
        $kinds = $kind === 'all' ? Prompts::KINDS : [$kind];
        if (array_diff($kinds, Prompts::KINDS) || ($this->option('reclassify-version') && count($kinds) !== 1)) {
            $this->error('--kind debe ser offers, trends o all (uno solo con --reclassify-version)');

            return self::FAILURE;
        }
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $maxInFlight = max(1, (int) $this->option('max-in-flight'));
        $maxRequests = max(1, (int) ($this->option('max-requests') ?: config('ai_classification.batch_max_requests', 2000)));
        $run = new RunLog('ai:classify-history', implode(',', $kinds), $this->options(), $dry);

        try {
            if ($collect && ! $dry) {
                $run->summary['collect'] = $runner->collect($run);
            }
            if ($n = $this->option('reclassify-version')) {
                $k = $kinds[0];
                $scope = $k === 'offers' ? ['jo.id <= ?', [AiSettings::historyMaxId('offers')]] : TrendClassifier::scopeHistory();
                $run->summary['reclassify_reset'] = ($k === 'offers' ? $offers : $trends)->resetVersion(Prompts::versionId($k, (int) $n), $scope, $dry || ! $submit);
            }
            $continue = $collect && AiSettings::historySubmittedAt() !== null;
            if ($dry || $submit || $continue) {
                if (! $dry && ! AiSettings::enabled()) {
                    $this->warn('Interruptor de emergencia: clasificación con IA APAGADA; no se envía nada.');
                } else {
                    if ($submit && ! $dry && AiSettings::historySubmittedAt() === null) {
                        AiSettings::update(['history_submitted_at' => now()], 'consola');
                    }
                    $run->summary['submit'] = $runner->submitHistory($run, $maxInFlight, $maxRequests, $dry, $limit, $kinds);
                }
            }
        } catch (\Throwable $e) {
            $run->error($e->getMessage());
            $run->finish('error');
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $dry && $run->c['processed'] > 0) {
            IndicatorScope::bumpCacheVersion();
        }
        $run->finish();
        $this->table(['Procesados', 'Heredados', 'Por regla', 'Respuestas', 'Tokens entrada', 'Tokens salida', 'Costo USD'],
            [[$run->c['processed'], $run->c['inherited'], $run->c['by_rule'], $run->c['calls'], $run->c['tokens_in'], $run->c['tokens_out'], round($run->c['cost_usd'], 4)]]);
        $this->line(json_encode($run->summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line('Lotes en curso: ' . DB::table('ai_batches')->where('status', 'enviado')->count());
        foreach ($run->errors as $err) {
            $this->warn($err);
        }

        return self::SUCCESS;
    }
}
