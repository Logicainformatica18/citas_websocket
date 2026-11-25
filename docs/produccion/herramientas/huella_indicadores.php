<?php
// Huella de las tablas que escriben los recálculos de los indicadores (rankings y precálculos), para
// comprobar que un refactor no cambia resultados. Uso, desde la raíz del proyecto:
//   php83 docs/produccion/herramientas/huella_indicadores.php capture <archivo.json>
//   php83 docs/produccion/herramientas/huella_indicadores.php compare <archivo.json>   → 0 diferencias = OK
// Por tabla: filas y md5 de todas las filas ordenadas, sin id ni columnas de fecha de cálculo (*_at, ms, mb).
// Las ventanas en curso cortan en "hoy": capturar y comparar el mismo día, recalculando antes cada vez.
chdir(__DIR__ . '/../../..');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

[$_, $mode, $file] = $argv + [null, null, null];
$tables = ['indicator_facts', 'job_offer_alignment', 'career_evolution_cache', 'company_evolution_cache',
    'seniority_evolution_cache', 'pe_alignment_competency_results'];
foreach (['language', 'technology', 'certification'] as $p) {
    array_push($tables, "{$p}_rankings", "{$p}_ranking_meta", "{$p}_ranking_weekly", "{$p}_ranking_weekly_meta");
}
$skip = '/^(id|.*_at|ms|mb|duration_ms|memory_mb)$/';

$pdo = DB::connection()->getPdo();
$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
$out = [];
foreach ($tables as $t) {
    if (! Schema::hasTable($t)) {
        continue;
    }
    $cols = array_values(array_filter(Schema::getColumnListing($t), fn ($c) => ! preg_match($skip, $c)));
    $list = implode(', ', array_map(fn ($c) => "`$c`", $cols));
    $st = $pdo->query("SELECT {$list} FROM `{$t}` ORDER BY {$list}");
    $ctx = hash_init('md5');
    $n = 0;
    while ($r = $st->fetch(PDO::FETCH_NUM)) {
        hash_update($ctx, json_encode($r) . "\n");
        $n++;
    }
    $st->closeCursor();
    $out[$t] = ['rows' => $n, 'md5' => hash_final($ctx)];
    fwrite(STDERR, "$t: $n\n");
}

if ($mode === 'capture') {
    file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT));
    echo "Huella guardada en $file\n";
    exit(0);
}
$old = json_decode(file_get_contents($file), true);
$diff = 0;
foreach ($old as $t => $v) {
    $now = $out[$t] ?? null;
    if ($now !== $v) {
        $diff++;
        printf("DIFERENTE %s: antes %d filas %s, ahora %s\n", $t, $v['rows'], $v['md5'], $now ? "{$now['rows']} filas {$now['md5']}" : 'no existe');
    }
}
echo $diff === 0 ? "0 diferencias en " . count($old) . " tablas\n" : "$diff tablas con diferencias\n";
exit($diff === 0 ? 0 : 1);
