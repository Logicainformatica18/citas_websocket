<?php
// Piloto: clasifica la muestra con OpenAI (solo lectura de la base; resultados en el scratchpad)
require 'D:/github3/citas_websocket/vendor/autoload.php';
$app = require 'D:/github3/citas_websocket/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Http;
$dir = __DIR__; $kind = $argv[1]; $model = $argv[2] ?? 'gpt-4.1-mini'; $batch = 50;
$d = json_decode(file_get_contents("$dir/pilot_sample.json"), true);
$clean = fn($s) => trim(preg_replace('/[\t\r\n]+/u', ' ', (string) $s));
if ($kind === 'offers') {
  $sys = file_get_contents("$dir/prompt_ofertas_v1.txt");
  $lines = array_map(fn($o) => implode("\t", [$o['id'], $clean($o['title']), $clean($o['company']), $clean($o['excerpt'])]), $d['offers']);
} else {
  $sys = file_get_contents("$dir/prompt_tendencias_v1.txt");
  $lines = array_map(function ($t) use ($clean) {
    $u = preg_replace('#^https?://(www\.)?#', '', (string) $t['source_url']);
    $title = $clean($t['source_title'] ?: $t['trend_name']);
    if ($t['trend_name'] && mb_strtolower($clean($t['trend_name'])) !== mb_strtolower($title)) $title .= ' | resumen: ' . $clean($t['trend_name']);
    return implode("\t", [$t['id'], "{$t['entity_type']}: " . $clean($t['name']), $title, mb_substr($u, 0, 100), $clean($t['source_type'])]);
  }, $d['trends']);
}
$out = []; $usage = ['in' => 0, 'cached' => 0, 'out' => 0, 'calls' => 0, 'secs' => 0];
foreach (array_chunk($lines, $batch) as $chunk) {
  $t = microtime(true);
  $resp = Http::withToken(config('services.openai.key'))->timeout(170)->post('https://api.openai.com/v1/chat/completions', [
    'model' => $model, 'temperature' => 0, 'response_format' => ['type' => 'json_object'],
    'messages' => [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => implode("\n", $chunk)]],
  ]);
  $usage['secs'] += microtime(true) - $t; $usage['calls']++;
  if (!$resp->successful()) { echo "ERROR ", substr($resp->body(), 0, 300), "\n"; exit(1); }
  $j = $resp->json();
  $usage['in'] += $j['usage']['prompt_tokens']; $usage['out'] += $j['usage']['completion_tokens'];
  $usage['cached'] += $j['usage']['prompt_tokens_details']['cached_tokens'] ?? 0;
  $r = json_decode($j['choices'][0]['message']['content'], true)['r'] ?? [];
  printf("lote %d: %d enviados, %d recibidos, %.1fs\n", $usage['calls'], count($chunk), count($r), microtime(true) - $t);
  foreach ($r as $x) $out[$x['id']] = $x;
}
// Tarifa gpt-4.1-mini (USD por millón): entrada 0,40, entrada en caché 0,10, salida 1,60
$cost = (($usage['in'] - $usage['cached']) * 0.40 + $usage['cached'] * 0.10 + $usage['out'] * 1.60) / 1e6;
$usage['cost_usd'] = round($cost, 5); $usage['model'] = $model;
file_put_contents("$dir/pilot_result_$kind.json", json_encode(['usage' => $usage, 'r' => $out], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
print_r($usage);
