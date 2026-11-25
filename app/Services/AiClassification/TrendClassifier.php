<?php

namespace App\Services\AiClassification;

use Illuminate\Support\Facades\DB;

/**
 * Clasificación de reportes de tendencia relevante / ruido / dudosa (estado único entity_trends.relevance_status).
 *   1. Reglas sin IA (origen 'regla'): reportes generados por GPT sin búsqueda web (gpt-search, gpt-*-gap) y URL de
 *      cursos, perfiles, empleo, portadas y sitemaps (config/ai_classification.php).
 *   2. IA: 50 reportes por llamada con título, resumen (extracto de Tavily o resumen de search-gpt), URL y tipo.
 * Solo estado 'pendiente'; nunca sobrescribe decisiones manuales (relevance_origin IS NULL al aplicar).
 * Coherencia (decisión 8): "dudosa" solo para la categoría inmediata; una dudosa con otra categoría es ruido.
 */
class TrendClassifier
{
    public function __construct(private OpenAiClient $ai)
    {
    }

    /** @return array{0: string, 1: array} */
    public static function scopeNew(): array
    {
        return ['et.id > ?', [AiSettings::historyMaxId('trends')]];
    }

    public static function scopeHistory(): array
    {
        return ['et.id <= ?', [AiSettings::historyMaxId('trends')]];
    }

    /** Regla que aplica a un reporte (categoría) o null. */
    public static function ruleFor(?string $discoveredBy, ?string $url): ?string
    {
        if (in_array($discoveredBy, config('ai_classification.trend_rule_sources', []), true)) {
            return 'generado_por_ia';
        }
        foreach (config('ai_classification.trend_url_rules', []) as $cat => $re) {
            if (preg_match($re, (string) $url)) {
                return $cat;
            }
        }

        return null;
    }

    /** Aplica las reglas a las pendientes del alcance. Devuelve cuántas marcó (o contaría, con $dry). */
    public function applyRules(array $scope, ?int $runId, bool $dry = false): int
    {
        [$sql, $b] = $scope;
        $cursor = 0;
        $hits = [];
        while (true) {
            $rows = DB::select("SELECT et.id, et.discovered_by, et.source_url FROM entity_trends et
                WHERE et.id > ? AND et.relevance_status = 'pendiente' AND et.relevance_origin IS NULL AND {$sql}
                ORDER BY et.id LIMIT 5000", array_merge([$cursor], $b));
            if (! $rows) {
                break;
            }
            foreach ($rows as $r) {
                if ($cat = self::ruleFor($r->discovered_by, $r->source_url)) {
                    $hits[$cat][] = (int) $r->id;
                }
            }
            $cursor = end($rows)->id;
        }
        $n = 0;
        foreach ($hits as $cat => $ids) {
            $n += count($ids);
            if ($dry) {
                continue;
            }
            $rid = DB::table('ai_classification_results')->insertGetId([
                'kind' => 'trend', 'item_key' => "regla:{$cat}", 'status' => 'ruido', 'category' => $cat,
                'reason' => $cat === 'generado_por_ia' ? 'Generado por GPT sin búsqueda web (fuente no verificable)' : "Regla de URL: {$cat}",
                'origin' => 'regla', 'pass' => 'rule', 'run_id' => $runId, 'items_affected' => count($ids), 'created_at' => now(),
            ]);
            foreach (array_chunk($ids, 1000) as $chunk) {
                DB::table('entity_trends')->whereIn('id', $chunk)->where('relevance_status', 'pendiente')->whereNull('relevance_origin')
                    ->update(['relevance_status' => 'ruido', 'relevance_origin' => 'regla', 'ai_result_id' => $rid]);
            }
        }

        return $n;
    }

    /** ids pendientes del alcance (las más nuevas primero) que van a la IA (las reglas ya se aplicaron). */
    public function pendingIds(array $scope, ?int $limit = null, bool $skipRules = false): array
    {
        [$sql, $b] = $scope;
        $ids = [];
        $q = DB::select("SELECT et.id, et.discovered_by, et.source_url FROM entity_trends et
            WHERE et.relevance_status = 'pendiente' AND et.relevance_origin IS NULL AND {$sql} ORDER BY et.id DESC", $b);
        foreach ($q as $r) {
            if ($skipRules && self::ruleFor($r->discovered_by, $r->source_url) !== null) {
                continue;
            }
            $ids[] = (int) $r->id;
            if ($limit && count($ids) >= $limit) {
                break;
            }
        }

        return $ids;
    }

    /** [índice => id] y líneas "i<TAB>tipo: entidad<TAB>título<TAB>resumen<TAB>URL<TAB>tipo de fuente". */
    public function lines(array $ids): array
    {
        $map = [];
        $lines = [];
        $i = 0;
        foreach (array_chunk($ids, 500) as $chunk) {
            $rows = DB::table('entity_trends as et')->leftJoin('market_entities as me', 'me.id', '=', 'et.market_entity_id')
                ->whereIn('et.id', $chunk)
                ->get(['et.id', 'me.name', 'me.entity_type', 'et.trend_name', 'et.source_title', 'et.source_url', 'et.source_type', 'et.source_snippet'])
                ->keyBy('id');
            foreach ($chunk as $id) {
                if (! isset($rows[$id])) {
                    continue;
                }
                $r = $rows[$id];
                $title = OfferClassifier::clean($r->source_title ?: $r->trend_name, 250);
                $summary = $r->source_snippet ? OfferClassifier::clean($r->source_snippet, 500)
                    : (mb_strtolower(OfferClassifier::clean($r->trend_name, 250)) !== mb_strtolower($title) ? OfferClassifier::clean($r->trend_name, 250) : '');
                $url = mb_substr(preg_replace('#^https?://(www\.)?#', '', (string) $r->source_url), 0, 100);
                $map[++$i] = (int) $id;
                $lines[$i] = $i . "\t" . ($r->entity_type ?? 'entidad') . ': ' . OfferClassifier::clean($r->name, 120) . "\t{$title}\t{$summary}\t"
                    . OfferClassifier::clean($url, 100) . "\t" . OfferClassifier::clean($r->source_type, 40);
            }
        }

        return [$map, $lines];
    }

    public static function cls(?string $c, ?string $cat): string
    {
        $c = strtolower(trim((string) $c));
        if (! in_array($c, ['relevante', 'ruido', 'dudosa'], true)) {
            return 'dudosa';
        }

        return $c === 'dudosa' && $cat !== null && $cat !== 'categoria' ? 'ruido' : $c;
    }

    public function saveDecision(int $id, array $x, object $version, ?int $batchId, ?int $runId): bool
    {
        $cat = OfferClassifier::str($x['cat'] ?? null, 60);
        $status = self::cls($x['c'] ?? null, $cat);
        $rid = DB::table('ai_classification_results')->insertGetId([
            'kind' => 'trend', 'item_key' => (string) $id, 'status' => $status, 'category' => $cat,
            'reason' => OfferClassifier::str($x['m'] ?? null, 300), 'confidence' => OfferClassifier::conf($x['p'] ?? null),
            'prompt_version_id' => $version->id, 'model' => $version->model, 'origin' => 'ia', 'pass' => 'trend',
            'batch_id' => $batchId, 'run_id' => $runId, 'created_at' => now(),
        ]);

        return DB::table('entity_trends')->where('id', $id)->whereIn('relevance_status', ['pendiente', 'en_proceso'])
            ->whereNull('relevance_origin')
            ->update(['relevance_status' => $status, 'relevance_origin' => 'ia', 'ai_result_id' => $rid, 'ai_batch_id' => null]) > 0;
    }

    public function runSync(array $scope, int $limit, object $version, RunLog $run): array
    {
        $rules = $this->applyRules($scope, $run->id);
        $run->add('by_rule', $rules);
        $ids = $this->pendingIds($scope, $limit);
        $done = 0;
        foreach (array_chunk($ids, (int) config('ai_classification.per_request.trend', 50)) as $chunk) {
            [$map, $lines] = $this->lines($chunk);
            try {
                $res = $this->ai->chat($version->model, $version->body, implode("\n", $lines));
            } catch (\Throwable $e) {
                $run->error($e->getMessage());
                continue;
            }
            $run->usage($version->model, $res['in'], $res['cached'], $res['out']);
            foreach ($res['rows'] as $x) {
                $i = (int) ($x['id'] ?? 0);
                if (isset($map[$i]) && $this->saveDecision($map[$i], $x, $version, null, $run->id)) {
                    $done++;
                }
            }
            if (count($res['rows']) < count($lines)) {
                $run->error('Respuesta incompleta: ' . count($res['rows']) . ' de ' . count($lines));
            }
        }
        $run->add('processed', $rules + $done);

        return ['by_rule' => $rules, 'sent' => count($ids), 'decisions' => $done];
    }

    /** Reportes del alcance clasificados por la IA con esa versión → pendiente (nunca manual ni regla). */
    public function resetVersion(int $versionId, array $scope, bool $dry): int
    {
        [$sql, $b] = $scope;
        $where = "et.relevance_origin = 'ia' AND r.prompt_version_id = ? AND {$sql}";
        if ($dry) {
            return (int) DB::selectOne("SELECT COUNT(*) n FROM entity_trends et JOIN ai_classification_results r ON r.id = et.ai_result_id WHERE {$where}",
                array_merge([$versionId], $b))->n;
        }

        return DB::update("UPDATE entity_trends et JOIN ai_classification_results r ON r.id = et.ai_result_id
            SET et.relevance_status = 'pendiente', et.relevance_origin = NULL, et.ai_result_id = NULL WHERE {$where}", array_merge([$versionId], $b));
    }

    public function estimate(int $items, object $version, bool $batch): array
    {
        $per = (int) config('ai_classification.per_request.trend', 50);
        $calls = (int) ceil($items / $per);
        $in = $calls * Cost::tokens($version->body) + $items * 55;
        $out = $items * 30;

        return ['calls' => $calls, 'tokens_in' => $in, 'tokens_out' => $out, 'cost_usd' => round(Cost::usd($version->model, $in, 0, $out, $batch), 2)];
    }
}
