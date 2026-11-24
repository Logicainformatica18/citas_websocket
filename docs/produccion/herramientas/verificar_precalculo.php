<?php
// Verifica que cada indicador precalculado responde EXACTAMENTE lo mismo que calculado en vivo
// (IndicatorFacts::$forceLive) y mide el tiempo de carga leyendo el precálculo. Uso, desde la raíz:
//   php83 docs/produccion/herramientas/verificar_precalculo.php <indicador> [año-periodo …]
// <indicador>: ver $CASES. Sin periodos: todos los precalculados. Solo lectura.
chdir(__DIR__ . '/../../..');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Indicators\IndicatorFacts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$name = $argv[1] ?? exit("Uso: verificar_precalculo.php <indicador> [año-periodo …]\n");
$periods = array_slice($argv, 2) ?: array_map(fn ($p) => "{$p[0]}-{$p[1]}", IndicatorFacts::periods());
Illuminate\Support\Facades\Auth::login(App\Models\User::permission('administrar')->first());
$version = app(App\Http\Middleware\HandleInertiaRequests::class)->version(Request::create('/')) ?? '';
$careers = DB::table('careers')->where('active', 1)->orderBy('id')->get(['id', 'slug']);

function call(string $uri, array $params, bool $inertia): array
{
    global $kernel, $version;
    $req = Request::create($uri, 'GET', $params);
    $req->headers->set('Accept', 'application/json');
    if ($inertia) {
        $req->headers->set('X-Inertia', 'true');
        $req->headers->set('X-Inertia-Version', $version);
    }
    $t = microtime(true);
    $resp = $kernel->handle($req);
    $ms = (microtime(true) - $t) * 1000;
    $kernel->terminate($req, $resp);
    $json = json_decode($resp->getContent(), true);
    if ($resp->getStatusCode() !== 200) {
        throw new RuntimeException("{$uri} → HTTP {$resp->getStatusCode()}");
    }

    return [$inertia ? ($json['props'] ?? []) : $json, $ms];
}

// Quita lo que cambia en cada llamada y lo que no es del indicador (sesión, menú, Datos Generales en caché)
function scrub($v)
{
    if (! is_array($v)) {
        return $v;
    }
    foreach (['updated_at', 'actualizado', 'generated_at', 'calculated_at', 'auth', 'ziggy', 'flash', 'errors', 'sidebarOpen',
        'permissions', 'name', 'quote', 'pendingCourseLinks', 'history_age', 'human', 'scraping'] as $k) {
        unset($v[$k]);
    }

    return array_map('scrub', $v);
}

// indicador => fn (año, periodo) => [[uri, params, inertia], …]
$CASES = [
    'seniority' => fn ($y, $p) => array_merge([
        ['/dashboard/indicators/seniority', ['year' => $y, 'period' => $p], true],
        ['/dashboard/indicators/seniority/distribution-by-career', ['year' => $y, 'period' => $p], false],
        ['/dashboard/indicators/seniority/modality', ['year' => $y, 'period' => $p], false],
        ['/dashboard/indicators/seniority', ['year' => $y, 'period' => $p, 'career' => [$GLOBALS['careers'][0]->slug]], true],
        ['/dashboard/indicators/seniority/distribution-by-career', ['year' => $y, 'period' => $p, 'career' => [$GLOBALS['careers'][4]->slug]], false],
    ], "{$y}-{$p}" === '2026-s2' ? array_map(fn ($c) => ['/dashboard/indicators/seniority', ['year' => $y, 'period' => $p, 'career' => [$c->slug]], true], $GLOBALS['careers']->slice(1)->all()) : []),
];

$cases = $CASES[$name] ?? exit("Indicador sin casos: {$name}\n");
$ok = 0;
$bad = 0;
$times = [];
foreach ($periods as $yp) {
    [$y, $p] = explode('-', $yp);
    foreach ($cases((int) $y, $p) as [$uri, $params, $inertia]) {
        IndicatorFacts::$forceLive = true;
        [$live, $msLive] = call($uri, $params, $inertia);
        IndicatorFacts::$forceLive = false;
        call($uri, $params, $inertia);                 // calienta (caché de Datos Generales, etc.)
        [$facts, $msFacts] = call($uri, $params, $inertia);
        $same = json_encode(scrub($live)) === json_encode(scrub($facts));
        $same ? $ok++ : $bad++;
        $times[$uri][] = $msFacts;
        printf("%s %-58s %-28s vivo %6.0f ms  precalc %5.0f ms\n", $same ? 'OK ' : 'DIF', $uri,
            mb_substr(json_encode($params + ['_' => null], JSON_UNESCAPED_UNICODE), 0, 28), $msLive, $msFacts);
        if (! $same) {
            file_put_contents(sys_get_temp_dir() . '/precalc_live.json', json_encode(scrub($live), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            file_put_contents(sys_get_temp_dir() . '/precalc_facts.json', json_encode(scrub($facts), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
}
echo "\n{$name}: {$ok} iguales, {$bad} diferentes\n";
foreach ($times as $uri => $ms) {
    sort($ms);
    printf("  %-58s precalculado: máx %5.0f ms, mediana %5.0f ms\n", $uri, max($ms), $ms[intdiv(count($ms), 2)]);
}
