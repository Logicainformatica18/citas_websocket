<?php

namespace App\Services\AiClassification;

use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Clasificación de ofertas TI / no TI (estado único job_offers.ti_status, PLAN_RANKINGS.md, Bloque 10).
 *
 * Solo se trabaja sobre ofertas en estado 'pendiente' de un ALCANCE (SQL sobre el alias jo: lo nuevo, el
 * histórico sin LinkedIn, LinkedIn). Orden, para no gastar llamadas:
 *   1. title_key de las pendientes (TitleKey::key).
 *   2. Herencia sin llamada (inherit): el título ya tiene decisión (ai_title_classifications) y
 *      a) la decisión es ti / no_ti y no depende de la descripción, o
 *      b) la oferta no tiene descripción ni paréntesis en el título: se queda la clase del título.
 *   3. Pasada por TÍTULO (un título único por elemento): títulos específicos sin decisión. La respuesta se guarda
 *      por título; si la IA dice dudosa o "depende de la descripción" (d = 1), la decisión del título no se
 *      hereda a las ofertas con descripción.
 *   4. Pasada por OFERTA (con la descripción): títulos genéricos (TitleKey::isGeneric) y ofertas cuyo título quedó
 *      dudoso o con d = 1. Sin descripción (y sin paréntesis en el título) no hay llamada: genérico → dudosa.
 * Nunca se sobrescriben decisiones de origen manual ni keyword (condición ti_origin IS NULL al aplicar).
 */
class OfferClassifier
{
    /** Texto de la oferta: funciones, descripción o requisitos (el primero que exista). */
    public const DESC = "COALESCE(NULLIF(jo.functions, ''), NULLIF(jo.description, ''), NULLIF(jo.requirements, ''), '')";

    public const EMPTY_KEY = '00000000000000000000000000000000';

    public function __construct(private OpenAiClient $ai)
    {
    }

    /* =================== alcances =================== */

    /** @return array{0: string, 1: array} */
    public static function scopeNew(): array
    {
        return ['jo.id > ?', [AiSettings::historyMaxId('offers')]];
    }

    /** Histórico sin LinkedIn (o solo LinkedIn: decisión 12, se clasifica al final). */
    public static function scopeHistory(bool $linkedin = false): array
    {
        return ['jo.id <= ? AND jo.source ' . ($linkedin ? '=' : '<>') . " 'LinkedIn'", [AiSettings::historyMaxId('offers')]];
    }

    /* =================== 1. clave =================== */

    public function fillTitleKeys(array $scope): int
    {
        [$sql, $b] = $scope;
        $n = 0;
        $cursor = 0;
        while (true) {
            $rows = DB::select("SELECT jo.id, jo.title FROM job_offers jo
                WHERE jo.id > ? AND jo.ti_status = 'pendiente' AND jo.title_key IS NULL AND {$sql}
                ORDER BY jo.id LIMIT 2000", array_merge([$cursor], $b));
            if (! $rows) {
                return $n;
            }
            $case = '';
            $bind = [];
            foreach ($rows as $r) {
                $case .= ' WHEN ? THEN ?';
                array_push($bind, $r->id, TitleKey::key($r->title) ?: self::EMPTY_KEY);
            }
            $ids = array_column($rows, 'id');
            DB::update('UPDATE job_offers SET title_key = CASE id' . $case . ' END WHERE id IN (' . implode(',', $ids) . ')', $bind);
            $cursor = end($rows)->id;
            $n += count($rows);
        }
    }

    /* =================== 2. herencia =================== */

    public function inherit(array $scope): int
    {
        [$sql, $b] = $scope;
        $range = DB::selectOne("SELECT MIN(jo.id) a, MAX(jo.id) z FROM job_offers jo WHERE jo.ti_status = 'pendiente' AND {$sql}", $b);
        if ($range->a === null) {
            return 0;
        }
        $n = 0;
        $min = (int) config('ai_classification.min_description', 50);
        for ($from = (int) $range->a; $from <= (int) $range->z; $from += 50000) {
            $n += DB::update("UPDATE job_offers jo JOIN ai_title_classifications tc ON tc.title_key = jo.title_key
                SET jo.ti_status = tc.status, jo.ti_origin = tc.origin, jo.ti_result_id = tc.result_id, jo.ti_batch_id = NULL
                WHERE jo.id BETWEEN ? AND ? AND jo.ti_status = 'pendiente' AND jo.ti_origin IS NULL AND {$sql}
                  AND ((tc.status <> 'dudosa' AND tc.depends_on_description = 0)
                       OR (CHAR_LENGTH(" . self::DESC . ") < ? AND jo.title NOT LIKE '%(%' AND jo.title NOT LIKE '%[%'))",
                array_merge([$from, $from + 49999], $b, [$min]));
        }

        return $n;
    }

    /* =================== plan (lo que haría cada pendiente) =================== */

    /**
     * Recorre las pendientes del alcance (consulta no bufferizada, memoria acotada) y las reparte:
     *   inherit   heredarían por título (solo se cuenta; la herencia real es inherit())
     *   title     títulos específicos sin decisión: clave => [título, n ofertas]
     *   offer     ids que van a la pasada por oferta (genéricos o título dudoso/d = 1, con descripción o paréntesis)
     *   rule      ids de genéricos sin descripción (→ dudosa sin llamada)
     *   busy      claves con ofertas en un lote en curso (se esperan)
     * $limit: ofertas consideradas (las más nuevas primero).
     */
    public function plan(array $scope, ?int $limit = null): array
    {
        [$sql, $b] = $scope;
        $titles = DB::table('ai_title_classifications')->pluck('depends_on_description', 'title_key')->all();
        $tcStatus = DB::table('ai_title_classifications')->pluck('status', 'title_key')->all();
        $busy = array_flip(DB::table('job_offers')->where('ti_status', 'en_proceso')->whereNotNull('title_key')
            ->distinct()->pluck('title_key')->all());
        $min = (int) config('ai_classification.min_description', 50);

        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $st = $pdo->prepare("SELECT jo.id, jo.title, jo.title_key, CHAR_LENGTH(" . self::DESC . ") AS dl FROM job_offers jo
            WHERE jo.ti_status = 'pendiente' AND jo.ti_origin IS NULL AND {$sql} ORDER BY jo.id DESC" . ($limit ? ' LIMIT ' . (int) $limit : ''));
        $st->execute($b);

        $plan = ['inherit' => 0, 'title' => [], 'offer' => [], 'rule' => [], 'busy' => 0, 'considered' => 0, 'offer_desc_chars' => 0];
        while ($r = $st->fetch(PDO::FETCH_OBJ)) {
            $plan['considered']++;
            $key = $r->title_key ?: (TitleKey::key($r->title) ?: self::EMPTY_KEY);
            $paren = str_contains((string) $r->title, '(') || str_contains((string) $r->title, '[');
            $hasText = (int) $r->dl >= $min;
            if (isset($tcStatus[$key])) {
                if (($tcStatus[$key] !== 'dudosa' && ! $titles[$key]) || (! $hasText && ! $paren)) {
                    $plan['inherit']++;
                } else {
                    $plan['offer'][] = (int) $r->id;
                    $plan['offer_desc_chars'] += min((int) $r->dl, (int) config('ai_classification.description_chars', 2000));
                }
            } elseif (TitleKey::isGeneric((string) $r->title)) {
                if ($hasText || $paren) {
                    $plan['offer'][] = (int) $r->id;
                    $plan['offer_desc_chars'] += min((int) $r->dl, (int) config('ai_classification.description_chars', 2000));
                } else {
                    $plan['rule'][] = (int) $r->id;
                }
            } elseif (isset($busy[$key])) {
                $plan['busy']++;
            } elseif (isset($plan['title'][$key])) {
                $plan['title'][$key][1]++;
            } else {
                $plan['title'][$key] = [(string) $r->title, 1];
            }
        }
        $st->closeCursor();
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        return $plan;
    }

    /* =================== líneas para la IA =================== */

    /**
     * Elementos de la pasada por título: [índice corto => clave] y líneas "i<TAB>título<TAB>empresas<TAB>".
     *
     * @param  array<string, array{0: string, 1: int}>  $titles  clave => [título, n]
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    public function titleLines(array $titles): array
    {
        $keys = array_keys($titles);
        $companies = [];
        foreach (array_chunk($keys, 500) as $chunk) {
            foreach (DB::table('job_offers')->whereIn('title_key', $chunk)->where('ti_status', 'pendiente')
                ->groupBy('title_key')
                ->selectRaw("title_key, SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT company ORDER BY company SEPARATOR '; '), '; ', 3) AS c")
                ->get() as $r) {
                $companies[$r->title_key] = $r->c;
            }
        }
        $map = [];
        $lines = [];
        $i = 0;
        foreach ($titles as $key => [$title]) {
            $map[++$i] = $key;
            $lines[$i] = $i . "\t" . self::clean($title, 200) . "\t" . self::clean($companies[$key] ?? '', 120) . "\t";
        }

        return [$map, $lines];
    }

    /**
     * Elementos de la pasada por oferta: [índice => id] y líneas "i<TAB>título<TAB>empresa<TAB>descripción".
     *
     * @param  array<int, int>  $ids
     */
    public function offerLines(array $ids): array
    {
        $chars = (int) config('ai_classification.description_chars', 2000);
        $map = [];
        $lines = [];
        $i = 0;
        foreach (array_chunk($ids, 500) as $chunk) {
            $rows = DB::table('job_offers as jo')->whereIn('jo.id', $chunk)
                ->selectRaw('jo.id, jo.title, jo.company, ' . self::DESC . ' AS d')->get()->keyBy('id');
            foreach ($chunk as $id) {
                if (! isset($rows[$id])) {
                    continue;
                }
                $r = $rows[$id];
                $map[++$i] = (int) $id;
                $lines[$i] = $i . "\t" . self::clean($r->title, 200) . "\t" . self::clean($r->company, 80) . "\t" . self::clean(self::plain($r->d), $chars);
            }
        }

        return [$map, $lines];
    }

    /* =================== aplicar decisiones =================== */

    /** Respuesta de la IA para un título: guarda la decisión vigente del título (salvo manual) y el historial. */
    public function saveTitleDecision(string $key, string $title, array $x, object $version, ?int $batchId, ?int $runId): void
    {
        $status = self::cls($x['c'] ?? null);
        $depends = (int) ($x['d'] ?? 0) === 1;
        $rid = $this->result('offer_title', $key, $status, $x, $depends, $version, 'ia', 'title', $batchId, $runId);
        $existing = DB::table('ai_title_classifications')->where('title_key', $key)->value('origin');
        if ($existing === 'manual') {
            return;
        }
        DB::table('ai_title_classifications')->updateOrInsert(['title_key' => $key], [
            'title' => mb_substr($title, 0, 500), 'status' => $status, 'depends_on_description' => $depends,
            'category' => self::str($x['cat'] ?? null, 60), 'reason' => self::str($x['m'] ?? null, 300),
            'confidence' => self::conf($x['p'] ?? null), 'prompt_version_id' => $version->id, 'model' => $version->model,
            'origin' => 'ia', 'result_id' => $rid, 'updated_at' => now(), 'created_at' => now(),
        ]);
    }

    /** Respuesta de la IA para una oferta (pasada por oferta). */
    public function saveOfferDecision(int $id, array $x, object $version, ?int $batchId, ?int $runId): bool
    {
        $status = self::cls($x['c'] ?? null);
        $rid = $this->result('offer', (string) $id, $status, $x, null, $version, 'ia', 'offer', $batchId, $runId);

        return DB::table('job_offers')->where('id', $id)->whereIn('ti_status', ['pendiente', 'en_proceso'])->whereNull('ti_origin')
            ->update(['ti_status' => $status, 'ti_origin' => 'ia', 'ti_result_id' => $rid, 'ti_batch_id' => null]) > 0;
    }

    /** Genéricos sin descripción → dudosa sin llamada (una fila de historial por corrida). */
    public function applyGenericRule(array $ids, ?int $runId): int
    {
        if (! $ids) {
            return 0;
        }
        $rid = DB::table('ai_classification_results')->insertGetId([
            'kind' => 'offer', 'item_key' => 'regla:generico_sin_descripcion', 'status' => 'dudosa',
            'reason' => 'Título genérico sin descripción: revisión manual', 'origin' => 'ia', 'pass' => 'sin_descripcion',
            'run_id' => $runId, 'items_affected' => count($ids), 'created_at' => now(),
        ]);
        $n = 0;
        foreach (array_chunk($ids, 1000) as $chunk) {
            $n += DB::table('job_offers')->whereIn('id', $chunk)->where('ti_status', 'pendiente')->whereNull('ti_origin')
                ->update(['ti_status' => 'dudosa', 'ti_origin' => 'ia', 'ti_result_id' => $rid]);
        }

        return $n;
    }

    /* =================== directo (ai:classify) =================== */

    /**
     * Clasifica las pendientes del alcance con llamadas directas: herencia, pasada por título, herencia otra vez,
     * pasada por oferta. Devuelve el resumen; los contadores van a $run.
     */
    public function runSync(array $scope, int $limit, object $version, RunLog $run): array
    {
        $this->fillTitleKeys($scope);
        $inherited = $this->inherit($scope);
        $run->add('inherited', $inherited);

        $plan = $this->plan($scope, $limit);
        $titleItems = 0;
        foreach (array_chunk($plan['title'], (int) config('ai_classification.per_request.title', 50), true) as $chunk) {
            [$map, $lines] = $this->titleLines($chunk);
            $rows = $this->call($version, $lines, $run);
            foreach ($rows as $x) {
                $i = (int) ($x['id'] ?? 0);
                if (isset($map[$i])) {
                    $this->saveTitleDecision($map[$i], $chunk[$map[$i]][0], $x, $version, null, $run->id);
                    $titleItems++;
                }
            }
        }
        $after = $this->inherit($scope);
        $run->add('inherited', $after);

        // Pasada por oferta: con las decisiones de título nuevas
        $plan2 = $this->plan($scope, $limit);
        $offerItems = 0;
        foreach (array_chunk($plan2['offer'], (int) config('ai_classification.per_request.offer', 25)) as $chunk) {
            [$map, $lines] = $this->offerLines($chunk);
            foreach ($this->call($version, $lines, $run) as $x) {
                $i = (int) ($x['id'] ?? 0);
                if (isset($map[$i]) && $this->saveOfferDecision($map[$i], $x, $version, null, $run->id)) {
                    $offerItems++;
                }
            }
        }
        $rule = $this->applyGenericRule($plan2['rule'], $run->id);
        $run->add('by_rule', $rule);
        $run->add('processed', $inherited + $after + $offerItems + $rule);

        return ['considered' => $plan['considered'], 'titles_sent' => count($plan['title']), 'title_decisions' => $titleItems,
            'inherited' => $inherited + $after, 'offers_sent' => count($plan2['offer']), 'offer_decisions' => $offerItems,
            'generic_without_description' => $rule, 'waiting_batch' => $plan['busy']];
    }

    /** Una llamada directa; si falla, se registra el error y la tanda queda pendiente. */
    private function call(object $version, array $lines, RunLog $run): array
    {
        if (! $lines) {
            return [];
        }
        try {
            $res = $this->ai->chat($version->model, $version->body, implode("\n", $lines));
            $run->usage($version->model, $res['in'], $res['cached'], $res['out']);
            if (count($res['rows']) < count($lines)) {
                $run->error('Respuesta incompleta: ' . count($res['rows']) . ' de ' . count($lines) . ' (el resto queda pendiente)');
            }

            return $res['rows'];
        } catch (\Throwable $e) {
            $run->error($e->getMessage());

            return [];
        }
    }

    /* =================== reclasificar (--reclassify-version=N) =================== */

    /**
     * Las ofertas del alcance clasificadas por la IA con esa versión vuelven a pendiente (nunca las de origen
     * manual o keyword) y se quitan las decisiones vigentes de título de esa versión (el historial queda en
     * ai_classification_results). Con $dry solo cuenta.
     */
    public function resetVersion(int $versionId, array $scope, bool $dry): int
    {
        [$sql, $b] = $scope;
        $where = "jo.ti_origin = 'ia' AND jo.ti_status IN ('ti', 'no_ti', 'dudosa') AND r.prompt_version_id = ? AND {$sql}";
        if ($dry) {
            return (int) DB::selectOne("SELECT COUNT(*) n FROM job_offers jo JOIN ai_classification_results r ON r.id = jo.ti_result_id WHERE {$where}",
                array_merge([$versionId], $b))->n;
        }
        $n = DB::update("UPDATE job_offers jo JOIN ai_classification_results r ON r.id = jo.ti_result_id
            SET jo.ti_status = 'pendiente', jo.ti_origin = NULL, jo.ti_result_id = NULL WHERE {$where}", array_merge([$versionId], $b));
        DB::table('ai_title_classifications')->where('prompt_version_id', $versionId)->where('origin', 'ia')->delete();

        return $n;
    }

    /* =================== estimación (--dry-run) =================== */

    /**
     * Llamadas, tokens y costo estimados de un plan. La segunda pasada (títulos que resulten dudosos o con
     * d = 1 y tengan descripción) se estima con la tasa del piloto 2 (≈ 3 % de los títulos).
     */
    public function estimate(array $plan, object $version, bool $batch): array
    {
        $sys = Cost::tokens($version->body);
        $perT = (int) config('ai_classification.per_request.title', 50);
        $perO = (int) config('ai_classification.per_request.offer', 25);
        $titles = count($plan['title']);
        $titleChars = 0;
        foreach ($plan['title'] as [$t]) {
            $titleChars += mb_strlen($t) + 45;     // empresas y separadores
        }
        $second = (int) round(array_sum(array_column($plan['title'], 1)) * 0.03 * 0.4);   // 3 % de títulos, 40 % con descripción
        $offers = count($plan['offer']) + $second;
        $avgDesc = $plan['offer'] ? $plan['offer_desc_chars'] / count($plan['offer']) : 600;
        $calls = (int) ceil($titles / $perT) + (int) ceil($offers / $perO);
        $in = (int) (ceil($titles / $perT) * $sys + $titleChars / 3.5 + ceil($offers / $perO) * $sys + $offers * ($avgDesc + 120) / 3.5);
        $out = ($titles + $offers) * 32;

        return ['calls' => $calls, 'tokens_in' => $in, 'tokens_out' => $out, 'second_pass_estimated' => $second,
            'cost_usd' => round(Cost::usd($version->model, $in, 0, $out, $batch), 2)];
    }

    /* =================== utilidades =================== */

    public function result(string $kind, string $key, string $status, array $x, ?bool $depends, object $version, string $origin, string $pass, ?int $batchId, ?int $runId): int
    {
        return DB::table('ai_classification_results')->insertGetId([
            'kind' => $kind, 'item_key' => $key, 'status' => $status,
            'category' => self::str($x['cat'] ?? null, 60), 'reason' => self::str($x['m'] ?? null, 300),
            'confidence' => self::conf($x['p'] ?? null), 'depends_on_description' => $depends,
            'prompt_version_id' => $version->id, 'model' => $version->model, 'origin' => $origin, 'pass' => $pass,
            'batch_id' => $batchId, 'run_id' => $runId, 'created_at' => now(),
        ]);
    }

    public static function cls(?string $c): string
    {
        $c = strtolower(trim((string) $c));

        return in_array($c, ['ti', 'no_ti', 'dudosa'], true) ? $c : 'dudosa';
    }

    public static function conf($p): ?int
    {
        return is_numeric($p) ? max(0, min(100, (int) $p)) : null;
    }

    public static function str($s, int $max): ?string
    {
        $s = trim((string) $s);

        return $s === '' ? null : mb_substr($s, 0, $max);
    }

    public static function plain(?string $html): string
    {
        $t = preg_replace('/<[^>]+>/', ' ', (string) $html);

        return html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function clean(?string $s, int $max): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $s)), 0, $max);
    }
}
