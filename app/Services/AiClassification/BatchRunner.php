<?php

namespace App\Services\AiClassification;

use Illuminate\Support\Facades\DB;

/**
 * Histórico con la Batch API (ai:classify-history). Lo que se envía queda en_proceso con el id del lote (no se
 * envía dos veces); al recoger, se aplican las respuestas y lo que no volvió regresa a pendiente.
 *
 * Orden del envío: ofertas sin LinkedIn (pasada por título y pasada por oferta), tendencias y, cuando no queda
 * nada pendiente del histórico sin LinkedIn, LinkedIn (decisión 12). Como máximo $maxInFlight lotes en curso a
 * la vez (límite de tokens en cola de la cuenta): --collect, en jobs:run-all, sigue enviando lo que falte, incluida
 * la segunda pasada (ofertas cuyo título quedó dudoso o con d = 1 y que tienen descripción).
 */
class BatchRunner
{
    public function __construct(private OpenAiClient $ai, private OfferClassifier $offers, private TrendClassifier $trends)
    {
    }

    public static function inFlight(): int
    {
        return DB::table('ai_batches')->where('status', 'enviado')->count();
    }

    /* =================== envío =================== */

    public function submitHistory(RunLog $run, int $maxInFlight, int $maxRequests, bool $dry, ?int $limit = null, array $kinds = ['offers', 'trends']): array
    {
        $budget = $dry ? PHP_INT_MAX : $maxInFlight - self::inFlight();
        $out = ['batches_in_flight_before' => self::inFlight()];
        $vo = Prompts::active('offers');
        $vt = Prompts::active('trends');

        if (in_array('offers', $kinds, true)) {
            $out['offers'] = $this->submitOfferScope(false, $vo, $run, $budget, $maxRequests, $dry, $limit);
        }
        if (in_array('trends', $kinds, true)) {
            $out['trends'] = $this->submitTrendScope($vt, $run, $budget, $maxRequests, $dry, $limit);
        }
        if (in_array('offers', $kinds, true)) {
            $out['offers_linkedin'] = ! $dry && $this->historyLeft(false) > 0
                ? 'espera: queda histórico sin LinkedIn por clasificar'
                : $this->submitOfferScope(true, $vo, $run, $budget, $maxRequests, $dry, $limit);
        }

        return $out;
    }

    private function submitOfferScope(bool $linkedin, object $vo, RunLog $run, int &$budget, int $maxRequests, bool $dry, ?int $limit): array
    {
        $scope = OfferClassifier::scopeHistory($linkedin);
        if (! $dry) {
            $this->offers->fillTitleKeys($scope);
            $run->add('inherited', $n = $this->offers->inherit($scope));
            $run->add('processed', $n);
        }
        $plan = $this->offers->plan($scope, $limit);
        $out = ['pending_considered' => $plan['considered'], 'inheritable' => $plan['inherit'],
            'titles' => count($plan['title']), 'title_offers' => array_sum(array_column($plan['title'], 1)),
            'offers_with_description' => count($plan['offer']), 'generic_without_description' => count($plan['rule']),
            'waiting_batch' => $plan['busy']];
        if ($dry) {
            $out['estimate'] = $this->offers->estimate($plan, $vo, true);

            return $out;
        }
        $rule = $this->offers->applyGenericRule($plan['rule'], $run->id);
        $run->add('by_rule', $rule);
        $run->add('processed', $rule);
        $out['batches'] = array_merge(
            $this->submitOffersTitle($plan['title'], $vo, $run, $budget, $maxRequests),
            $this->submitIds('offers', 'offer', $plan['offer'], $vo, $run, $budget, $maxRequests),
        );

        return $out;
    }

    private function submitTrendScope(object $vt, RunLog $run, int &$budget, int $maxRequests, bool $dry, ?int $limit): array
    {
        $ts = TrendClassifier::scopeHistory();
        $rules = $this->trends->applyRules($ts, $run->id, $dry);
        $ids = $this->trends->pendingIds($ts, $limit, $dry);
        $out = ['by_rule' => $rules, 'to_ai' => count($ids)];
        if ($dry) {
            $out['estimate'] = $this->trends->estimate(count($ids), $vt, true);

            return $out;
        }
        $run->add('by_rule', $rules);
        $run->add('processed', $rules);
        $out['batches'] = $this->submitIds('trends', 'trend', $ids, $vt, $run, $budget, $maxRequests);

        return $out;
    }

    /** Pendientes o en proceso del histórico de ofertas (sin LinkedIn o solo LinkedIn). */
    public function historyLeft(bool $linkedin): int
    {
        [$sql, $b] = OfferClassifier::scopeHistory($linkedin);

        return (int) DB::selectOne("SELECT COUNT(*) n FROM job_offers jo WHERE jo.ti_status IN ('pendiente', 'en_proceso') AND {$sql}", $b)->n;
    }

    /** @param  array<string, array{0: string, 1: int}>  $titles */
    private function submitOffersTitle(array $titles, object $version, RunLog $run, int &$budget, int $maxRequests): array
    {
        $per = (int) config('ai_classification.per_request.title', 50);
        $ids = [];
        foreach (array_chunk($titles, $per * $maxRequests, true) as $batchTitles) {
            if ($budget <= 0) {
                break;
            }
            $requests = [];
            foreach (array_chunk($batchTitles, $per, true) as $chunk) {
                [$map, $lines] = $this->offers->titleLines($chunk);
                $requests[] = [array_map(fn ($k) => [$k, $chunk[$k][0]], $map), $lines];
            }
            $keys = array_keys($batchTitles);
            $bid = $this->create('offers', 'title', $requests, $version, $run,
                fn ($bid) => $this->markOffers($bid, 'title_key', $keys));
            if ($bid) {
                $ids[] = $bid;
                $budget--;
            }
        }

        return $ids;
    }

    /** Pasada por oferta (ofertas) o tendencias: elementos por id. */
    private function submitIds(string $kind, string $pass, array $items, object $version, RunLog $run, int &$budget, int $maxRequests): array
    {
        $per = (int) config("ai_classification.per_request.{$pass}", 50);
        $ids = [];
        foreach (array_chunk($items, $per * $maxRequests) as $batchItems) {
            if ($budget <= 0) {
                break;
            }
            $requests = [];
            foreach (array_chunk($batchItems, $per) as $chunk) {
                $requests[] = $kind === 'offers' ? $this->offers->offerLines($chunk) : $this->trends->lines($chunk);
            }
            $bid = $this->create($kind, $pass, $requests, $version, $run, $kind === 'offers'
                ? fn ($bid) => $this->markOffers($bid, 'id', $batchItems)
                : fn ($bid) => $this->markTrends($bid, $batchItems));
            if ($bid) {
                $ids[] = $bid;
                $budget--;
            }
        }

        return $ids;
    }

    /**
     * Arma el JSONL, marca los elementos en_proceso, sube el archivo y crea el lote. Si algo falla, los
     * elementos vuelven a pendiente y el lote queda fallido.
     *
     * @param  array<int, array{0: array, 1: array}>  $requests  [mapa índice → elemento, líneas]
     */
    private function create(string $kind, string $pass, array $requests, object $version, RunLog $run, callable $mark): ?int
    {
        $items = array_sum(array_map(fn ($r) => count($r[1]), $requests));
        $bid = DB::table('ai_batches')->insertGetId([
            'kind' => $kind, 'pass' => $pass, 'purpose' => 'history', 'status' => 'preparando', 'requests' => count($requests),
            'items' => $items, 'prompt_version_id' => $version->id, 'model' => $version->model, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $dir = storage_path('app/ai_batches');
        @mkdir($dir, 0775, true);
        $path = "{$dir}/{$bid}_input.jsonl";
        $fh = fopen($path, 'w');
        $rows = [];
        foreach ($requests as $n => [$map, $lines]) {
            $custom = "b{$bid}-" . ($n + 1);
            fwrite($fh, json_encode(['custom_id' => $custom, 'method' => 'POST', 'url' => '/v1/chat/completions',
                'body' => OpenAiClient::body($version->model, $version->body, implode("\n", $lines))], JSON_UNESCAPED_UNICODE) . "\n");
            $rows[] = ['batch_id' => $bid, 'custom_id' => $custom, 'items' => json_encode($map, JSON_UNESCAPED_UNICODE)];
        }
        fclose($fh);
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('ai_batch_requests')->insert($chunk);
        }

        $mark($bid);
        try {
            $fileId = $this->ai->uploadBatchFile($path);
            $b = $this->ai->createBatch($fileId, ['observatorio' => "ai_batches.{$bid}", 'pass' => $pass]);
            DB::table('ai_batches')->where('id', $bid)->update([
                'status' => 'enviado', 'provider_status' => $b['status'] ?? null, 'provider_batch_id' => $b['id'] ?? null,
                'input_file_id' => $fileId, 'submitted_at' => now(), 'updated_at' => now(),
            ]);
            $run->summary['submitted'][] = ['batch' => $bid, 'kind' => $kind, 'pass' => $pass, 'requests' => count($requests), 'items' => $items];

            return $bid;
        } catch (\Throwable $e) {
            $this->release($kind, $bid);
            DB::table('ai_batches')->where('id', $bid)->update(['status' => 'fallido', 'error' => mb_substr($e->getMessage(), 0, 2000), 'updated_at' => now()]);
            $run->error("Lote {$bid} ({$kind}/{$pass}): " . $e->getMessage());

            return null;
        }
    }

    private function markOffers(int $bid, string $column, array $values): void
    {
        foreach (array_chunk($values, 1000) as $chunk) {
            DB::table('job_offers')->whereIn($column, $chunk)->where('ti_status', 'pendiente')->whereNull('ti_origin')
                ->update(['ti_status' => 'en_proceso', 'ti_batch_id' => $bid]);
        }
    }

    private function markTrends(int $bid, array $ids): void
    {
        foreach (array_chunk($ids, 1000) as $chunk) {
            DB::table('entity_trends')->whereIn('id', $chunk)->where('relevance_status', 'pendiente')->whereNull('relevance_origin')
                ->update(['relevance_status' => 'en_proceso', 'ai_batch_id' => $bid]);
        }
    }

    /** Lo que sigue en_proceso con este lote vuelve a pendiente. */
    private function release(string $kind, int $bid): int
    {
        return $kind === 'offers'
            ? DB::table('job_offers')->where('ti_batch_id', $bid)->where('ti_status', 'en_proceso')->update(['ti_status' => 'pendiente', 'ti_batch_id' => null])
            : DB::table('entity_trends')->where('ai_batch_id', $bid)->where('relevance_status', 'en_proceso')->update(['relevance_status' => 'pendiente', 'ai_batch_id' => null]);
    }

    /* =================== recolección =================== */

    public function collect(RunLog $run): array
    {
        $out = [];
        foreach (DB::table('ai_batches')->where('status', 'enviado')->orderBy('id')->get() as $row) {
            try {
                $b = $this->ai->batch($row->provider_batch_id);
            } catch (\Throwable $e) {
                $run->error("Lote {$row->id}: " . $e->getMessage());
                continue;
            }
            $status = $b['status'] ?? 'desconocido';
            DB::table('ai_batches')->where('id', $row->id)->update(['provider_status' => $status, 'updated_at' => now()]);
            if ($status === 'completed') {
                $out[$row->id] = $this->apply($row, $b, $run);
            } elseif (in_array($status, ['failed', 'expired', 'cancelled'], true)) {
                $released = $this->release($row->kind, $row->id);
                DB::table('ai_batches')->where('id', $row->id)->update([
                    'status' => ['failed' => 'fallido', 'expired' => 'expirado', 'cancelled' => 'cancelado'][$status],
                    'error' => mb_substr(json_encode($b['errors'] ?? null, JSON_UNESCAPED_UNICODE), 0, 2000), 'updated_at' => now(),
                ]);
                $run->error("Lote {$row->id}: {$status}; {$released} elementos vuelven a pendiente");
                $out[$row->id] = $status;
            } else {
                $out[$row->id] = $status;
            }
        }

        return $out;
    }

    private function apply(object $row, array $b, RunLog $run): array
    {
        $version = Prompts::version((int) $row->prompt_version_id);
        $requests = DB::table('ai_batch_requests')->where('batch_id', $row->id)->pluck('items', 'custom_id')
            ->map(fn ($j) => json_decode($j, true))->all();
        $path = storage_path("app/ai_batches/{$row->id}_output.jsonl");
        $tokens = ['in' => 0, 'cached' => 0, 'out' => 0];
        $applied = 0;
        $lines = 0;
        if (! empty($b['output_file_id'])) {
            $this->ai->download($b['output_file_id'], $path);
            $fh = fopen($path, 'r');
            while (($line = fgets($fh)) !== false) {
                $j = json_decode($line, true);
                $map = $requests[$j['custom_id'] ?? ''] ?? null;
                if (! $map || (int) data_get($j, 'response.status_code') !== 200) {
                    continue;
                }
                $lines++;
                $res = OpenAiClient::parseCompletion(data_get($j, 'response.body', []));
                foreach (['in', 'cached', 'out'] as $k) {
                    $tokens[$k] += $res[$k];
                }
                DB::transaction(function () use ($res, $map, $row, $version, $run, &$applied) {
                    foreach ($res['rows'] as $x) {
                        $item = $map[(int) ($x['id'] ?? 0)] ?? null;
                        if ($item === null) {
                            continue;
                        }
                        if ($row->pass === 'title') {
                            $this->offers->saveTitleDecision($item[0], $item[1], $x, $version, $row->id, $run->id);
                            $applied++;
                        } elseif ($row->pass === 'offer') {
                            $applied += (int) $this->offers->saveOfferDecision((int) $item, $x, $version, $row->id, $run->id);
                        } else {
                            $applied += (int) $this->trends->saveDecision((int) $item, $x, $version, $row->id, $run->id);
                        }
                    }
                });
                DB::table('ai_batch_requests')->where('batch_id', $row->id)->where('custom_id', $j['custom_id'])->update(['status' => 'aplicado']);
            }
            fclose($fh);
        }
        $released = $this->release($row->kind, $row->id);
        $inherited = 0;
        if ($row->pass === 'title') {
            // Las ofertas de los títulos decididos heredan ahora (vuelven a pendiente y la herencia las toma)
            $inherited = $this->offers->inherit(['1 = 1', []]);
            $run->add('inherited', $inherited);
        }
        $cost = Cost::usd($row->model, $tokens['in'], $tokens['cached'], $tokens['out'], true);
        $run->usage($row->model, $tokens['in'], $tokens['cached'], $tokens['out'], true, $lines);
        $run->add('processed', $applied + $inherited);
        DB::table('ai_batches')->where('id', $row->id)->update([
            'status' => 'aplicado', 'output_file_id' => $b['output_file_id'] ?? null, 'error_file_id' => $b['error_file_id'] ?? null,
            'tokens_in' => $tokens['in'], 'tokens_out' => $tokens['out'], 'cost_usd' => round($cost, 5),
            'completed_at' => now(), 'applied_at' => now(), 'updated_at' => now(),
        ]);

        return ['responses' => $lines, 'of_requests' => count($requests), 'applied' => $applied, 'inherited' => $inherited,
            'back_to_pending' => $released, 'cost_usd' => round($cost, 4)];
    }
}
