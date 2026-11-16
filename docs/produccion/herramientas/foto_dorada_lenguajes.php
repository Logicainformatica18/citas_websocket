<?php
// Foto dorada del Ranking de Lenguajes (prueba de regresión del refactor). Uso, desde la raíz del proyecto:
//   php83 docs/produccion/herramientas/foto_dorada_lenguajes.php capture <dir>   → guarda la foto
//   php83 docs/produccion/herramientas/foto_dorada_lenguajes.php compare <dir>   → compara (0 diferencias = OK)
// La foto depende de la fecha (las ventanas en curso cortan en "hoy"): capturar y comparar el mismo día.
// Requiere un usuario con permiso 'administrar'. Solo lectura salvo el archivo JSON de <dir>.
// Incluye: las 4 tablas resumen (sin id ni calculated_at) y las respuestas HTTP de la página,
// el modal (3 tipos, todas las páginas) y el export (3 tipos).
chdir(__DIR__ . '/../../..');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

[$_, $mode, $dir] = $argv;
@mkdir($dir, 0777, true);
Illuminate\Support\Facades\Auth::login(App\Models\User::permission('administrar')->first());

function http(string $uri, array $params, array $headers = [])
{
    global $kernel;
    $req = Request::create($uri, 'GET', $params);
    $req->headers->set('Accept', 'application/json');
    foreach ($headers as $k => $v) {
        $req->headers->set($k, $v);
    }
    $resp = $kernel->handle($req);
    $kernel->terminate($req, $resp);

    return $resp;
}

// Quita campos que cambian en cada ejecución aunque el resultado sea el mismo
function scrub($v)
{
    if (is_array($v)) {
        foreach (['calculated_at', 'calculated_at_label', 'actualizado', 'generated_at', 'id_row', 'quote', 'csrf_token', 'last_run_human', 'human', 'history_age', 'last_success_human', 'updated_at'] as $k) {
            unset($v[$k]);
        }
        foreach (['jobMarketStatus', 'scrapingStatus', 'auth', 'permissions', 'name', 'sidebarOpen', 'pendingCourseLinks', 'errors', 'ziggy'] as $k) {
            if (isset($v['ranking']) || isset($v['meta'])) {
                unset($v[$k]);
            }
        }

        return array_map('scrub', $v);
    }

    return $v;
}

$snap = [];

/* 1. Tablas resumen, en orden determinista */
foreach ([
    'language_rankings' => 'year, period, market_entity_id',
    'language_ranking_meta' => 'year, period',
    'language_ranking_weekly' => 'period_type, period_key, market_entity_id',
    'language_ranking_weekly_meta' => 'period_type, period_key',
] as $table => $order) {
    $snap["table:$table"] = DB::table($table)->orderByRaw($order)->get()
        ->map(fn ($r) => collect((array) $r)->except(['id', 'calculated_at'])->all())->all();
}

/* 2. Página: varios periodos y filtros, todas las páginas */
$v = app(App\Http\Middleware\HandleInertiaRequests::class)->version(Request::create('/')) ?? '';
$cases = [
    ['year' => 2026, 'period' => 's1'], ['year' => 2026, 'period' => 's2'], ['year' => 2025, 'period' => 's2'],
    ['year' => 2024, 'period' => 's1'], ['year' => 2023, 'period' => 's1'],
    ['year' => 2026, 'period' => 's2', 'search' => 'Java'],
    ['year' => 2026, 'period' => 's2', 'career' => ['desarrollo-de-software']],
];
foreach ($cases as $c) {
    for ($p = 1; $p <= 5; $p++) {
        $props = json_decode(http('/dashboard/ranking/languages', $c + ['page' => $p], ['X-Inertia' => 'true', 'X-Inertia-Version' => $v])->getContent(), true)['props'];
        $snap['page:' . json_encode($c) . ":$p"] = scrub(['ranking' => $props['ranking'], 'meta' => $props['meta'], 'filters' => $props['filters'], 'weights' => $props['weights']]);
        if ($p >= ($props['ranking']['last_page'] ?? 1)) {
            break;
        }
    }
}

/* 3. Modal: 3 tipos x años, todas las páginas */
foreach (['weekly', 'biweekly', 'monthly'] as $f) {
    foreach ([2024, 2025, 2026] as $y) {
        for ($p = 1; $p <= 50; $p++) {
            $j = json_decode(http('/dashboard/ranking/languages/weekly', ['filter' => $f, 'year' => $y, 'page' => $p, 'per_page' => 5])->getContent(), true);
            $snap["modal:$f:$y:$p"] = scrub($j);
            if ($p >= ($j['pagination']['last_page'] ?? 1)) {
                break;
            }
        }
    }
}

/* 4. Export (contenido de las celdas) */
foreach (['weekly', 'biweekly', 'monthly'] as $f) {
    $resp = http('/dashboard/ranking/languages/evolution/export', ['filter' => $f, 'year' => 2026]);
    $path = $resp->getFile()->getPathname();
    $snap["export:$f"] = (new PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($path)->getActiveSheet()->toArray();
}

$file = "$dir/golden_languages.json";
if ($mode === 'capture') {
    file_put_contents($file, json_encode($snap, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    printf("Foto dorada guardada: %s\n  %d bloques | filas: rankings=%d meta=%d weekly=%d weekly_meta=%d | páginas=%d modal=%d export=%d filas\n",
        $file, count($snap), count($snap['table:language_rankings']), count($snap['table:language_ranking_meta']),
        count($snap['table:language_ranking_weekly']), count($snap['table:language_ranking_weekly_meta']),
        count(array_filter(array_keys($snap), fn ($k) => str_starts_with($k, 'page:'))),
        count(array_filter(array_keys($snap), fn ($k) => str_starts_with($k, 'modal:'))),
        array_sum(array_map(fn ($k) => count($snap[$k]), array_filter(array_keys($snap), fn ($k) => str_starts_with($k, 'export:')))));
    exit;
}

$golden = json_decode(file_get_contents($file), true);
$now = json_decode(json_encode($snap, JSON_UNESCAPED_UNICODE), true);
// duration_ms es el tiempo que tardó el cálculo (varía en cada ejecución), no un resultado
foreach (['golden', 'now'] as $var) {
    foreach ($$var['table:language_ranking_meta'] as $i => $row) {
        unset($$var['table:language_ranking_meta'][$i]['duration_ms']);
    }
}
$diffs = 0;
foreach (array_unique(array_merge(array_keys($golden), array_keys($now))) as $k) {
    if (($golden[$k] ?? null) !== ($now[$k] ?? null)) {
        $diffs++;
        if ($diffs <= 10) {
            echo "DIFERENCIA en $k\n";
            if (is_array($golden[$k] ?? null) && is_array($now[$k] ?? null)) {
                $a = json_encode($golden[$k], JSON_UNESCAPED_UNICODE);
                $b = json_encode($now[$k], JSON_UNESCAPED_UNICODE);
                $i = strspn($a ^ $b, "\0");
                echo '   antes: …' . substr($a, max(0, $i - 80), 200) . "\n   ahora: …" . substr($b, max(0, $i - 80), 200) . "\n";
            }
        }
    }
}
printf("Comparación con la foto dorada: %d bloques revisados, %d con diferencias → %s\n", count($golden), $diffs, $diffs === 0 ? 'OK (0 diferencias)' : 'FALLA');
