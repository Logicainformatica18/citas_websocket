<?php
// Compara el precálculo de PE y CCTC (CurricularDemand) con consultas SQL directas, escritas como las
// originales (solo exclusión por título, asociaciones completas). Uso: php83 …/verificar_demanda_curricular.php [año]
chdir(__DIR__ . '/../../..');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Indicators\CurricularDemand;
use Illuminate\Support\Facades\DB;

$year = (int) ($argv[1] ?? now()->year);
$t = microtime(true);
$d = CurricularDemand::compute($year);
printf("CurricularDemand::compute(%d): %.1f s, pico %d MB\n", $year, microtime(true) - $t, memory_get_peak_usage(true) / 1048576);

$sources = [['technology_job', 'technologies', 'technology_id', 'course_technology'], ['language_job', 'languages', 'language_id', 'course_language'], ['methodology_job', 'methodologies', 'methodology_id', 'course_methodology']];
$ok = 0;
$bad = 0;
$check = function (string $what, $a, $b) use (&$ok, &$bad) {
    $same = $a === $b;
    $same ? $ok++ : $bad++;
    if (! $same) {
        echo "DIF {$what}: precálculo " . json_encode($a) . ' / directo ' . json_encode($b) . "\n";
    }
};

foreach (DB::table('careers')->where('active', 1)->orderBy('id')->pluck('id') as $career) {
    // Entidades de la carrera (como getGlobalMeta)
    $entities = [];
    foreach ($sources as [, $catalog, $col, $courseTable]) {
        $entities = array_merge($entities, DB::table('career_course as cc')->join("{$courseTable} as x", 'x.course_id', '=', 'cc.course_id')
            ->join("{$catalog} as e", 'e.id', '=', "x.{$col}")->where('cc.career_id', $career)->whereNotNull('e.market_entity_id')
            ->pluck('e.market_entity_id')->all());
    }
    $list = implode(',', array_unique(array_map('intval', $entities))) ?: '0';
    $exists = implode(' OR ', array_map(fn ($s) => "EXISTS (SELECT 1 FROM {$s[0]} p JOIN {$s[1]} e ON e.id = p.{$s[2]} WHERE p.job_offer_id = jo.id AND e.market_entity_id IN ({$list}))", $sources));
    $count = fn (string $from, string $to, string $op) => (int) DB::selectOne("SELECT COUNT(*) n FROM job_offers jo
        WHERE jo.title_exclusion_id IS NULL AND jo.published_at >= ? AND jo.published_at {$op} ? AND ({$exists})", [$from, $to])->n;
    $check("carrera {$career} año", $d['career_year'][$career] ?? 0, $count("{$year}-01-01", ($year + 1) . '-01-01', '<'));
    $check("carrera {$career} s1", $d['career_period']['s1'][$career] ?? 0, $count("{$year}-01-01", "{$year}-06-30", '<='));
    $check("carrera {$career} s2", $d['career_period']['s2'][$career] ?? 0, $count("{$year}-07-01", "{$year}-12-31", '<='));
}

// Ofertas por curso (como el market_base de los cursos de PE: COUNT DISTINCT de las tres fuentes)
$union = implode(' UNION ', array_map(fn ($s) => "SELECT x.course_id, jo.id FROM {$s[3]} x JOIN {$s[0]} p ON p.{$s[2]} = x.{$s[2]}
    JOIN job_offers jo ON jo.id = p.job_offer_id WHERE jo.title_exclusion_id IS NULL AND jo.published_at >= '{$year}-01-01' AND jo.published_at < '" . ($year + 1) . "-01-01'", $sources));
$direct = [];
foreach (DB::select("SELECT course_id, COUNT(DISTINCT id) n FROM ({$union}) u GROUP BY course_id") as $r) {
    $direct[(int) $r->course_id] = (int) $r->n;
}
$pre = $d['course_jobs'];
ksort($pre);
ksort($direct);
$check('ofertas por curso (' . count($direct) . ' cursos)', $pre, $direct);

// Entidades con demanda (CCTC: demanda activa del curso)
foreach (CurricularDemand::SOURCES as $src => [$pivot, , $col]) {
    $directSet = DB::table($pivot . ' as p')->join('job_offers as jo', 'jo.id', '=', 'p.job_offer_id')
        ->whereNull('jo.title_exclusion_id')->where('jo.published_at', '>=', "{$year}-01-01")->where('jo.published_at', '<', ($year + 1) . '-01-01')
        ->distinct()->pluck("p.{$col}")->map(fn ($v) => (int) $v)->sort()->values()->all();
    $preSet = array_keys($d['demand'][$src]);
    sort($preSet);
    $check("entidades con demanda ({$src})", $preSet, $directSet);
}

echo "\nDemanda curricular {$year}: {$ok} iguales, {$bad} diferentes\n";
