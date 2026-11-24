<?php
// Mide UN indicador de análisis (valores, tiempo y pico de memoria) en un proceso propio. Uso, desde la raíz:
//   php83 docs/produccion/herramientas/medir_indicadores.php <indicador> [apagados] [año] [s1|s2]
// <indicador>: datos_generales | empresas | modalidad | seniority | carreras | geo | pe | cctc | modales | ciudad | operativas
// [apagados]: filtros de IndicatorScope a apagar, separados por coma: title, detector, inactive ('-' = ninguno).
//   "title,detector,inactive" reproduce el cálculo anterior al criterio común; comparar con '-' da cuánto deja
//   de contar el indicador, y con 'title' (solo asociaciones) separa lo que aporta cada tipo de exclusión.
// Llama a los endpoints como la interfaz (usuario con 'administrar'). Invalida las cachés de los indicadores
// (IndicatorScope::bumpCacheVersion) para medir el cálculo y no la caché. Imprime una línea JSON. No escribe datos.
chdir(__DIR__ . '/../../..');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Indicators\IndicatorScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$section = $argv[1] ?? exit("Uso: medir_indicadores.php <indicador> [apagados] [año] [periodo]\n");
$off = ($argv[2] ?? '-') === '-' ? [] : explode(',', $argv[2]);
$year = (int) ($argv[3] ?? 2026);
$period = $argv[4] ?? 's2';
IndicatorScope::$off = $off;
// Invalida las cachés de los indicadores (Datos Generales, Geo): se mide el cálculo, no la caché
IndicatorScope::bumpCacheVersion();
Illuminate\Support\Facades\Auth::login(App\Models\User::permission('administrar')->first());
$version = app(App\Http\Middleware\HandleInertiaRequests::class)->version(Request::create('/')) ?? '';
$p = ['year' => $year, 'period' => $period];

function get(string $uri, array $params = [], bool $inertia = false): array
{
    global $kernel, $version;
    $req = Request::create($uri, 'GET', $params);
    $req->headers->set('Accept', 'application/json');
    if ($inertia) {
        $req->headers->set('X-Inertia', 'true');
        $req->headers->set('X-Inertia-Version', $version);
    }
    $resp = $kernel->handle($req);
    $kernel->terminate($req, $resp);
    if ($resp->getStatusCode() !== 200) {
        throw new RuntimeException("{$uri} → HTTP {$resp->getStatusCode()}");
    }
    $json = json_decode($resp->getContent(), true);

    return $inertia ? ($json['props'] ?? []) : $json;
}

$scalars = fn (?array $a) => collect($a ?? [])->filter(fn ($x) => is_scalar($x) || $x === null)->all();
$career = DB::table('careers')->where('active', 1)->orderBy('id')->value('id');

$sections = [
    'datos_generales' => function () use ($p) {
        // Sin caché: cálculo directo (lo que guarda la caché de 1 h)
        $r = new ReflectionClass(App\Http\Controllers\Dashboard\JobMarketStatusController::class);
        $g = $r->getMethod('computeGlobal')->invoke(null, now());
        $per = $r->getMethod('computePeriod')->invoke(null, $p['year'], $p['period']);
        $b = App\Services\JobMarketStatusBuilder::build(['mode' => 'market'] + $p);

        return ['rankings.total' => $g['offers_total'], 'rankings.nuevas_mes' => $g['offers_new_month'],
            'rankings.semestre' => $per['offers_analysed'], 'rankings.suma_fuentes' => array_sum(array_column($g['sources'], 'total')),
            'otros.total' => $b['global']['offers_total'], 'otros.nuevas_mes' => $b['global']['offers_new_month'], 'otros.semestre' => $b['period']['offers_analysed']];
    },
    'empresas' => function () use ($p) {
        $j = get('/dashboard/indicators/companies', $p, true);
        $lider = data_get($j, 'meta.empresa_lider');
        $o = $lider ? get('/dashboard/indicators/companies/' . rawurlencode($lider) . '/jobs', $p) : [];

        return ['vacantes' => data_get($j, 'meta.vacantes_analizadas'), 'empresas' => data_get($j, 'meta.empresas_activas'),
            'lider' => $lider, 'lider_vacantes' => data_get($j, 'meta.empresa_lider_vacantes'), 'modal_lider' => $o['total'] ?? null];
    },
    'modalidad' => function () use ($p) {
        $j = get('/dashboard/indicadores/modalidad-laboral', $p, true);

        return ['total' => data_get($j, 'meta.total_vacantes')] + collect($j['data'] ?? [])->pluck('vacantes', 'modalidad')->all();
    },
    'seniority' => function () use ($p) {
        $j = get('/dashboard/indicators/seniority', $p, true);
        $c = get('/dashboard/indicators/seniority/distribution-by-career', $p);
        $m = get('/dashboard/indicators/seniority/modality', $p);

        return ['vacantes' => data_get($j, 'meta.vacantes_analizadas')] + collect($j['jobMarketStatus'] ?? [])->pluck('jobs', 'level')->all()
            + ['por_carrera' => collect($c['data'] ?? [])->sum(fn ($x) => collect($x['distribution'])->sum('jobs')),
                'modalidad' => collect($m['data'] ?? [])->sum('total'), 'dg_total' => data_get($j, 'jobMarketData.global.offers_total')];
    },
    'carreras' => function () use ($p) {
        $j = get('/dashboard/ranking-carreras', $p, true);

        return ['vacantes' => data_get($j, 'meta.vacantes_analizadas'), 'suma_top' => collect(data_get($j, 'ranking.data', []))->sum('total_jobs')];
    },
    'geo' => function () use ($p, $scalars) {
        $j = get('/dashboard/indicators/job-demand-geo', $p, true);
        $h = get('/dashboard/indicators/job-demand-geo/heatmap', $p);

        return $scalars($j['meta'] ?? []) + ['demanda_carreras' => collect($j['careers'] ?? [])->sum('total_jobs'),
            'mapa_puntos' => count($h['results'] ?? []), 'mapa_ofertas' => collect($h['results'] ?? [])->sum('total')];
    },
    'pe' => function () use ($p, $career, $scalars) {
        $j = get('/dashboard/indicators/pe-alignment', $p + ['career_id' => $career], true);

        return $scalars($j['meta'] ?? $j['globalMeta'] ?? []);
    },
    'cctc' => function () use ($p, $career, $scalars) {
        $j = get('/dashboard/indicators/course-alignment', $p + ['career_id' => $career], true);
        $course = DB::table('career_course')->where('career_id', $career)->value('course_id');
        $r = get("/indicators/course/{$course}/recent-jobs");
        $g = get("/indicators/course/{$course}/gaps");

        return collect($scalars($j['meta'] ?? []))->only(['vacantes_analizadas', 'reportes_analizados'])->all()
            + ['recientes' => count($r['data'] ?? $r), 'brechas' => json_encode($g)];
    },
    'modales' => function () use ($p) {
        $r = [];
        foreach (['languages' => ['/dashboard/ranking/languages', 'language'], 'technologies' => ['/dashboard/ranking/technologies', 'technology'],
            'certifications' => ['/dashboard/ranking-certificaciones', 'certification']] as $type => [$url, $px]) {
            $e = DB::table("{$px}_rankings")->where('year', $p['year'])->where('period', $p['period'])->orderByDesc('total_jobs')->value('market_entity_id');
            $j = get("{$url}/{$e}/jobs", $p);
            $r["{$type}#{$e}"] = $j['total'] ?? data_get($j, 'meta.total') ?? data_get($j, 'data.total');
        }
        $t = DB::table('technology_trend_job')->select('technology_trend_id')->groupBy('technology_trend_id')->orderByRaw('COUNT(*) DESC')->value('technology_trend_id');
        $r["tendencia#{$t}"] = get("/trends/{$t}/jobs")['total'] ?? null;
        $r["tendencia_modal#{$t}"] = data_get(get("/dashboard/ranking/technologies/trend/{$t}/jobs"), 'data.total');

        return $r;
    },
    'ciudad' => function () use ($p) {
        $j = get('/ai/city-demand/get-data', ['year' => $p['year']]);

        return ['puntos' => count($j['results'] ?? []), 'total' => data_get($j, 'summary.total_offers'),
            'top_modalidad' => data_get($j, 'summary.top_modality.total'), 'top_fuente' => data_get($j, 'summary.top_source.total')];
    },
    'operativas' => function () {
        return ['job-offers' => get('/job-offers/fetch', ['per_page' => 1])['total'] ?? null,
            'fuentes' => data_get(get('/sources/fetch'), 'metrics.registros_totales'),
            'job-stats' => data_get(get('/api/job-stats'), 'totals.total_offers')];
    },
];

$fn = $sections[$section] ?? exit("Indicador desconocido: {$section}\n");
$t = microtime(true);
$values = $fn();
echo json_encode(['indicador' => $section, 'apagados' => $off ?: '-', 'ms' => (int) round((microtime(true) - $t) * 1000),
    'pico_mb' => round(memory_get_peak_usage(true) / 1048576, 1), 'valores' => $values], JSON_UNESCAPED_UNICODE) . "\n";
