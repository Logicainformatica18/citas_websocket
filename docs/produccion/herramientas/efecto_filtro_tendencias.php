<?php
// Efecto del filtro de tendencias de la clasificación con IA (reportes en estado ruido) en los tres rankings, SIN
// activarlo ni recalcular nada (solo lectura). Uso, desde la raíz del proyecto:
//   php83 docs/produccion/herramientas/efecto_filtro_tendencias.php [año] [s1|s2] [actual|proyeccion]
// Para cada ranking: reportes, trend_score, puntaje final y top 10 con la fórmula del ranking
// (trend = LOG(reportes+1)/LOG(max+1)*100 entre las entidades activas; final = laboral*wl + tendencia*wt; orden por
// final, vacantes y nombre), con las ponderaciones vigentes y con alternativas.
//   actual      cuenta los reportes que hoy NO están en ruido (las pendientes cuentan, como haría el filtro)
//   proyeccion  además descuenta las pendientes con la tasa de ruido del piloto 2 por origen y tipo de entidad
//               (estimación de cómo quedará cuando se clasifique el histórico)
chdir(__DIR__ . '/../../..');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Ranking\EntityRankingService;
use Illuminate\Support\Facades\DB;

$year = (int) ($argv[1] ?? now()->year);
$period = $argv[2] ?? (now()->month <= 6 ? 's1' : 's2');
$mode = $argv[3] ?? 'actual';
$quarters = $period === 's1' ? [1, 2] : [3, 4];
// Piloto 2 (prompts v2): fracción que NO es ruido entre los reportes que van a la IA, por origen y tipo
$keep = [
    'tavily-search' => ['technology' => 9 / 75, 'certification' => 2 / 35, 'language' => 1 / 15, 'methodology' => 9 / 75],
    'search-gpt' => ['technology' => 14 / 25, 'certification' => 14 / 25, 'language' => 14 / 25, 'methodology' => 14 / 25],
];
$alternatives = [[0.7, 0.3], [0.8, 0.2], [0.9, 0.1], [1.0, 0.0]];

echo "# Efecto del filtro de tendencias — {$year}-{$period} — modo {$mode}\n\n";
foreach (['languages' => 'language', 'technologies' => 'technology', 'certifications' => 'certification'] as $key => $type) {
    $svc = EntityRankingService::for($key);
    [$wl, $wt] = $svc->activeWeights();
    $rows = DB::table($svc->table('rankings') . ' as r')->join('market_entities as me', fn ($j) => $j->on('me.id', '=', 'r.market_entity_id')->where('me.is_active', 1))
        ->where('r.year', $year)->where('r.period', $period)
        ->get(['r.market_entity_id as id', 'r.name', 'r.total_jobs', 'r.labor_score', 'r.trend_score', 'r.trend_reports'])->keyBy('id');
    if ($rows->isEmpty()) {
        echo "## {$key}: sin ranking para el periodo\n\n";
        continue;
    }
    $counts = DB::table('entity_trends as et')->join('market_entities as me', 'me.id', '=', 'et.market_entity_id')
        ->where('me.entity_type', $type)->where('me.is_active', 1)->where('et.year', $year)->whereIn('et.quarter', $quarters)
        ->groupBy('et.market_entity_id', 'et.relevance_status', 'et.discovered_by')
        ->selectRaw('et.market_entity_id e, et.relevance_status s, et.discovered_by b, COUNT(*) n')->get();
    $before = [];
    $after = [];
    $byStatus = [];
    foreach ($counts as $c) {
        $before[$c->e] = ($before[$c->e] ?? 0) + $c->n;
        $byStatus[$c->s] = ($byStatus[$c->s] ?? 0) + $c->n;
        if ($c->s === 'ruido') {
            continue;
        }
        $n = $c->n;
        if ($mode === 'proyeccion' && in_array($c->s, ['pendiente', 'en_proceso'], true)) {
            $n *= $keep[$c->b][$type] ?? 0.0;
        }
        $after[$c->e] = ($after[$c->e] ?? 0) + $n;
    }
    $score = function (array $reports) use ($rows) {
        $max = 0;
        foreach ($rows as $id => $r) {
            $max = max($max, $reports[$id] ?? 0);
        }
        $out = [];
        foreach ($rows as $id => $r) {
            $out[$id] = $max > 0 ? log(($reports[$id] ?? 0) + 1) / log($max + 1) * 100 : 0.0;
        }

        return [$out, $max];
    };
    [$tBefore, $maxBefore] = $score($before);
    [$tAfter, $maxAfter] = $score($after);
    $rank = function (array $trend, float $wl, float $wt) use ($rows) {
        $list = [];
        foreach ($rows as $id => $r) {
            $list[] = ['id' => $id, 'name' => $r->name, 'jobs' => (int) $r->total_jobs, 'labor' => (float) $r->labor_score,
                'trend' => $trend[$id], 'final' => (float) $r->labor_score * $wl + $trend[$id] * $wt];
        }
        usort($list, fn ($a, $b) => [$b['final'], $b['jobs'], $a['name']] <=> [$a['final'], $a['jobs'], $b['name']]);

        return $list;
    };
    $rb = $rank($tBefore, $wl, $wt);
    $ra = $rank($tAfter, $wl, $wt);
    $posA = array_flip(array_column($ra, 'id'));
    $checkStored = abs(array_sum($tBefore) - $rows->sum('trend_score')) < 0.5 * $rows->count();

    printf("## %s (ponderaciones vigentes %.2f / %.2f)\n\n", $key, $wl, $wt);
    printf("- Reportes del semestre (entidades activas): %s antes → %s después (%s); máximo por entidad %s → %s; entidades con reportes %d → %d.\n",
        number_format(array_sum($before), 0, ',', ' '), number_format(array_sum($after), 0, ',', ' '),
        json_encode($byStatus), number_format($maxBefore, 0, ',', ' '), number_format($maxAfter, 1, ',', ' '),
        count(array_filter($before)), count(array_filter($after, fn ($n) => $n >= 0.5)));
    printf("- trend_score medio %.1f → %.1f. Comprobación: trend_score recalculado sin filtro = guardado: %s.\n\n",
        array_sum($tBefore) / max(1, count($tBefore)), array_sum($tAfter) / max(1, count($tAfter)), $checkStored ? 'sí' : 'NO (recalcular el ranking antes)');
    echo "| # | Antes | trend | final | Después | trend | final | Pos. antes→después del top 10 anterior |\n|---|---|---|---|---|---|---|---|\n";
    for ($i = 0; $i < 10 && $i < count($rb); $i++) {
        $b = $rb[$i];
        $a = $ra[$i];
        printf("| %d | %s | %.1f | %.1f | %s | %.1f | %.1f | %s %d→%d |\n", $i + 1, $b['name'], $b['trend'], $b['final'],
            $a['name'], $a['trend'], $a['final'], $b['name'], $i + 1, $posA[$b['id']] + 1);
    }
    echo "\nTop 10 después con otras ponderaciones (laboral / tendencia):\n\n";
    foreach ($alternatives as [$l, $t]) {
        printf("- %.1f / %.1f: %s\n", $l, $t, implode(', ', array_column(array_slice($rank($tAfter, $l, $t), 0, 10), 'name')));
    }
    echo "\n";
}
