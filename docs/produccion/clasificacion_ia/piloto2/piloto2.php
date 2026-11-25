<?php

/**
 * Piloto 2 (prompts v2): 150 ofertas (las mismas del piloto 1) y 150 tendencias (las 95 de Tavily/search-gpt del
 * piloto 1 + 55 nuevas; gpt-search ya es ruido por regla). Solo lee la base; escribe resultados en esta carpeta.
 *
 *   php83 docs/produccion/clasificacion_ia/piloto2/piloto2.php
 *
 * Ofertas: pasada 1 por título (títulos específicos, sin descripción); pasada 2 con la descripción (títulos
 * genéricos y los que la pasada 1 dejó dudosos o con d = 1), con gpt-4.1-mini y, para comparar, gpt-4.1.
 */
require __DIR__ . '/../../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\AiClassification\TitleKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$dir = __DIR__;
$root = dirname(__DIR__, 4);
$p1 = json_decode(file_get_contents("$dir/../piloto1/pilot_sample.json"), true);
$promptOffers = file_get_contents("$root/database/data/ai_prompts/ofertas_v2.txt");
$promptTrends = file_get_contents("$root/database/data/ai_prompts/tendencias_v2.txt");
$price = ['gpt-4.1-mini' => [0.40, 0.10, 1.60], 'gpt-4.1' => [2.00, 0.50, 8.00]];
$usage = [];
$clean = fn ($s) => trim(preg_replace('/\s+/u', ' ', (string) $s));
$plain = fn ($t) => $clean(html_entity_decode(strip_tags(preg_replace('/<[^>]+>/', ' ', (string) $t)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

$ask = function (string $sys, array $lines, string $model, string $tag) use (&$usage, $price) {
    $out = [];
    foreach (array_chunk($lines, 50, true) as $chunk) {
        $t = microtime(true);
        $r = Http::withToken(config('services.openai.key'))->timeout(170)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model, 'temperature' => 0, 'response_format' => ['type' => 'json_object'],
            'messages' => [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => implode("\n", $chunk)]],
        ])->throw()->json();
        $u = $r['usage'];
        $cached = $u['prompt_tokens_details']['cached_tokens'] ?? 0;
        [$pi, $pc, $po] = $price[$model];
        $usage[$tag]['calls'] = ($usage[$tag]['calls'] ?? 0) + 1;
        $usage[$tag]['in'] = ($usage[$tag]['in'] ?? 0) + $u['prompt_tokens'];
        $usage[$tag]['out'] = ($usage[$tag]['out'] ?? 0) + $u['completion_tokens'];
        $usage[$tag]['secs'] = round(($usage[$tag]['secs'] ?? 0) + microtime(true) - $t, 1);
        $usage[$tag]['cost_usd'] = round(($usage[$tag]['cost_usd'] ?? 0) + (($u['prompt_tokens'] - $cached) * $pi + $cached * $pc + $u['completion_tokens'] * $po) / 1e6, 5);
        foreach (json_decode($r['choices'][0]['message']['content'], true)['r'] ?? [] as $x) {
            $out[(int) $x['id']] = $x;
        }
        printf("%s: %d enviados, %d recibidos\n", $tag, count($chunk), count($out));
    }

    return $out;
};

/* ---------------- ofertas ---------------- */
$offers = [];
$full = DB::table('job_offers')->whereIn('id', array_column($p1['offers'], 'id'))
    ->get(['id', 'title', 'company', 'description', 'requirements', 'functions'])->keyBy('id');
foreach ($p1['offers'] as $o) {
    $f = $full[$o['id']];
    $o['text'] = mb_substr($plain($f->functions ?: ($f->description ?: $f->requirements)), 0, 2000);
    $o['generic'] = TitleKey::isGeneric($o['title']);
    $offers[$o['id']] = $o;
}
// Pasada 1: títulos específicos (índice corto = posición)
$idx = 0;
$map1 = [];
$lines = [];
foreach ($offers as $id => $o) {
    if (! $o['generic']) {
        $map1[++$idx] = $id;
        $lines[$idx] = "$idx\t" . $clean($o['title']) . "\t" . $clean($o['company']) . "\t";
    }
}
foreach ($ask($promptOffers, $lines, 'gpt-4.1-mini', 'ofertas_titulo') as $i => $x) {
    $offers[$map1[$i]]['pass1'] = $x;
}
// Pasada 2: genéricos + dudosos o d = 1 de la pasada 1, con la descripción
$idx = 0;
$map2 = [];
$lines = [];
foreach ($offers as $id => $o) {
    $p = $o['pass1'] ?? null;
    if (! ($o['generic'] || $p === null || $p['c'] === 'dudosa' || (int) ($p['d'] ?? 0) === 1)) {
        continue;
    }
    // Sin descripción no hay segunda pasada (decisión del clasificador): genérico o dudoso → dudosa; d = 1 → la clase del título
    if (mb_strlen($o['text']) < 50) {
        $offers[$id]['final_sin_texto'] = ($o['generic'] || $p === null || $p['c'] === 'dudosa') ? 'dudosa' : $p['c'];
        continue;
    }
    $map2[++$idx] = $id;
    $lines[$idx] = "$idx\t" . $clean($o['title']) . "\t" . $clean($o['company']) . "\t" . $o['text'];
}
foreach (['gpt-4.1-mini' => 'pass2', 'gpt-4.1' => 'pass2_41'] as $model => $key) {
    foreach ($ask($promptOffers, $lines, $model, "ofertas_descripcion_$model") as $i => $x) {
        $offers[$map2[$i]][$key] = $x;
    }
}

/* ---------------- tendencias ---------------- */
$rules = config('ai_classification.trend_rules', [
    'curso' => '#udemy\.com|coursera\.org/(learn|courses|specializations|professional-certificates|projects)|linkedin\.com/learning|pluralsight\.com#i',
    'perfil' => '#linkedin\.com/in/#i',
    'empleo' => '#/jobs?/|indeed\.|glassdoor\.#i',
    'portada' => '#^https?://[^/]+/?$#i',
    'sitemap' => '#\.xml$#i',
]);
mt_srand(20261126);
$prev = "$dir/resultado_piloto2_borrador_a.json";
$trends = array_values(array_filter($p1['trends'], fn ($t) => $t['discovered_by'] !== 'gpt-search'));
if (is_file($prev)) {   // mismas 150 tendencias que el borrador A
    $trends = array_map(fn ($t) => array_diff_key($t, ['ai' => 1, 'rule' => 1]), json_decode(file_get_contents($prev), true)['trends']);
}
$have = array_column($trends, 'id');
foreach (is_file($prev) ? [] : [['tavily-search', 'technology', 25], ['tavily-search', 'certification', 10], ['tavily-search', 'language', 5], ['search-gpt', 'technology', 15]] as [$by, $type, $n]) {
    $ids = array_column(DB::select('SELECT et.id FROM entity_trends et JOIN market_entities me ON me.id = et.market_entity_id WHERE et.discovered_by = ? AND me.entity_type = ?', [$by, $type]), 'id');
    shuffle($ids);
    $ids = array_slice(array_values(array_diff($ids, $have)), 0, $n);
    foreach (DB::table('entity_trends as et')->join('market_entities as me', 'me.id', '=', 'et.market_entity_id')->whereIn('et.id', $ids)
        ->get(['et.id', 'me.name', 'me.entity_type', 'me.is_active', 'et.trend_name', 'et.source_title', 'et.source_url', 'et.source_type', 'et.discovered_by']) as $r) {
        $trends[] = (array) $r + ['new' => 1];
    }
}
$idx = 0;
$map = [];
$lines = [];
foreach ($trends as $k => $t) {
    foreach ($rules as $cat => $re) {
        if (preg_match($re, (string) $t['source_url'])) {
            $trends[$k]['rule'] = $cat;
            continue 2;
        }
    }
    $title = $clean($t['source_title'] ?: $t['trend_name']);
    $summary = mb_strtolower($clean($t['trend_name'])) !== mb_strtolower($title) ? $clean($t['trend_name']) : '';
    $url = mb_substr(preg_replace('#^https?://(www\.)?#', '', (string) $t['source_url']), 0, 100);
    $map[++$idx] = $k;
    $lines[$idx] = "$idx\t{$t['entity_type']}: " . $clean($t['name']) . "\t$title\t$summary\t$url\t" . $clean($t['source_type']);
}
foreach ($ask($promptTrends, $lines, 'gpt-4.1-mini', 'tendencias') as $i => $x) {
    $trends[$map[$i]]['ai'] = $x;
}

file_put_contents("$dir/resultado_piloto2.json", json_encode(['usage' => $usage, 'offers' => array_values($offers), 'trends' => $trends],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
print_r($usage);
