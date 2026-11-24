<?php

namespace App\Console\Commands\Curriculum;

use App\Services\Indicators\IndicatorScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Prueba;

class RecalculatePeAlignment extends Command
{
    protected $signature = 'pe:recalculate
                            {career_id : ID de la carrera}
                            {--year= : Año (default actual)}';

    protected $description = 'Recalcula y materializa resultados PE Alignment (idéntico al modelo dinámico)';

    public function handle()
    {
        $careerId = (int) $this->argument('career_id');
        $year = $this->option('year')
            ? (int) $this->option('year')
            : now()->year;

        $weights = Prueba::getActive('pe_alignment');

        $laborWeight = (float) ($weights?->labor_weight ?? 0.7);
        $trendWeight = (float) ($weights?->trend_weight ?? 0.3);

        $this->info("🔄 Recalculando PE Alignment...");
        $this->info("Career: {$careerId} | Year: {$year}");

        $market = $this->marketCounts($year);

        DB::beginTransaction();

        try {

            /* ======================================================
               1️⃣ MISMO UNIVERSO GLOBAL QUE EL MODELO ORIGINAL
            ====================================================== */

            $rows = DB::select("
            WITH market_aggregated AS (
                /* Ofertas del año por competencia (suma de las tres fuentes), calculadas por
                   tramos en marketCounts() con el criterio común de los indicadores. */
                " . $this->valuesSql($market) . "
            ),

            market_ranked AS (
                SELECT
                    competency_id,
                    job_count,
                    NTILE(4) OVER (ORDER BY job_count DESC) AS quartile
                FROM market_aggregated
            ),

            trend_base AS (

                SELECT
                    cc.competency_id,
                    COUNT(DISTINCT et.id) AS trend_count
                FROM competency_course cc
                JOIN course_technology ct ON ct.course_id = cc.course_id
                JOIN technologies t ON t.id = ct.technology_id
                JOIN entity_trends et
                    ON et.market_entity_id = t.market_entity_id
                   AND et.year = ?
                GROUP BY cc.competency_id

                UNION ALL

                SELECT
                    cc.competency_id,
                    COUNT(DISTINCT et.id) AS trend_count
                FROM competency_course cc
                JOIN course_language cl ON cl.course_id = cc.course_id
                JOIN languages l ON l.id = cl.language_id
                JOIN entity_trends et
                    ON et.market_entity_id = l.market_entity_id
                   AND et.year = ?
                GROUP BY cc.competency_id

                UNION ALL

                SELECT
                    cc.competency_id,
                    COUNT(DISTINCT et.id) AS trend_count
                FROM competency_course cc
                JOIN course_methodology cm ON cm.course_id = cc.course_id
                JOIN methodologies m ON m.id = cm.methodology_id
                JOIN entity_trends et
                    ON et.market_entity_id = m.market_entity_id
                   AND et.year = ?
                GROUP BY cc.competency_id
            ),

            trend_aggregated AS (
                SELECT competency_id,
                       SUM(trend_count) AS trend_count
                FROM trend_base
                GROUP BY competency_id
            ),

            trend_ranked AS (
                SELECT
                    competency_id,
                    trend_count,
                    NTILE(4) OVER (ORDER BY trend_count DESC) AS quartile
                FROM trend_aggregated
            )

            SELECT
                comp.id AS competency_id,
                mr.job_count,
                tr.trend_count,
                mr.quartile AS market_q,
                tr.quartile AS trend_q
            FROM competencies comp
            LEFT JOIN market_ranked mr ON mr.competency_id = comp.id
            LEFT JOIN trend_ranked tr ON tr.competency_id = comp.id
            WHERE comp.career_id = ?
            ", [
                $year, $year, $year,                  // trend_base
                $careerId
            ]);

            /* ======================================================
               2️⃣ UPSERT EXACTAMENTE IGUAL AL MODELO ORIGINAL
            ====================================================== */

            foreach ($rows as $row) {

                $marketScore = 0;
                if ($row->job_count !== null) {
                    $marketScore = match ($row->market_q) {
                        1 => 1,
                        2 => 0.75,
                        3 => 0.5,
                        default => 0.25
                    };
                }

                $trendScore = 0;
                if ($row->trend_count !== null) {
                    $trendScore = match ($row->trend_q) {
                        1 => 1,
                        2 => 0.75,
                        3 => 0.5,
                        default => 0.25
                    };
                }

                $finalScore =
                    ($laborWeight * $marketScore) +
                    ($trendWeight * $trendScore);

                $percentage = round($finalScore * 100, 1);

                $level = match (true) {
                    $percentage >= 80 => 'Fuerte',
                    $percentage >= 60 => 'Media',
                    $percentage >= 40 => 'Débil',
                    default => 'Crítica'
                };

                DB::table('pe_alignment_competency_results')
                    ->updateOrInsert(
                        [
                            'career_id' => $careerId,
                            'competency_id' => $row->competency_id,
                            'year' => $year
                        ],
                        [
                            'job_count' => $row->job_count ?? 0,
                            'trend_count' => $row->trend_count ?? 0,
                            'market_quartile' => $row->market_q,
                            'trend_quartile' => $row->trend_q,
                            'market_score' => $marketScore,
                            'trend_score' => $trendScore,
                            'final_score' => $percentage,
                            'level' => $level,
                            'calculated_at' => now()
                        ]
                    );
            }

            DB::commit();

            $this->info("✅ PE Alignment recalculado correctamente (idéntico al modelo dinámico).");

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("❌ Error: " . $e->getMessage());
        }
    }

    /** Conteo por competencia y año, compartido entre carreras del mismo proceso (no depende de la carrera). */
    private static array $marketCache = [];

    /**
     * Ofertas del año por competencia: por cada fuente (tecnologías, lenguajes, metodologías) una
     * oferta cuenta una vez por competencia alcanzada a través de los cursos; luego se suman las
     * tres fuentes (igual que el SQL anterior: COUNT(DISTINCT oferta) por fuente y SUM).
     * Pares (oferta, entidad) del año con el criterio común (IndicatorScope), leídos por tramos de
     * job_offer_id; el mapa entidad → competencias está en memoria. Antes, un COUNT(DISTINCT) sobre
     * pares × cursos × competencias tardaba varios minutos por carrera.
     *
     * @return array<int, int> competency_id => job_count (solo > 0)
     */
    private function marketCounts(int $year, int $chunk = 10000): array
    {
        $key = $year . '|' . implode(',', IndicatorScope::$off);
        if (isset(self::$marketCache[$key])) {
            return self::$marketCache[$key];
        }

        $from = "{$year}-01-01";
        $to = ($year + 1) . '-01-01';
        $maxId = (int) DB::table('job_offers')->max('id');
        $counts = [];

        foreach ([
            ['technology_job', 'tj', 'technology_id', 'course_technology'],
            ['language_job', 'lj', 'language_id', 'course_language'],
            ['methodology_job', 'mj', 'methodology_id', 'course_methodology'],
        ] as [$pivot, $a, $col, $courseTable]) {
            $map = [];   // entidad => [competency_id => true]
            foreach (DB::table($courseTable . ' as x')->join('competency_course as cc', 'cc.course_id', '=', 'x.course_id')
                ->distinct()->get(["x.{$col} as e", 'cc.competency_id as c']) as $r) {
                $map[$r->e][$r->c] = true;
            }

            $active = IndicatorScope::activeIdSet(IndicatorScope::entityType($pivot));

            for ($lo = 0; $lo <= $maxId; $lo += $chunk) {
                // Asociaciones válidas (IndicatorScope): las exclusiones del detector del tramo se leen
                // una vez y se descartan en memoria (un NOT EXISTS por par tardaba ~5 min).
                $excluded = IndicatorScope::excludedPairSet($pivot, $lo, $lo + $chunk);
                $offers = [];   // oferta => [competency_id => true]
                foreach (DB::select(
                    "SELECT DISTINCT {$a}.job_offer_id AS o, {$a}.{$col} AS e, {$a}.market_entity_id AS me
                       FROM job_offers jo
                       JOIN {$pivot} {$a} ON {$a}.job_offer_id = jo.id
                      WHERE jo.id >= ? AND jo.id < ? AND jo.published_at >= ? AND jo.published_at < ?"
                        . IndicatorScope::offersSql('jo'),
                    [$lo, $lo + $chunk, $from, $to]
                ) as $r) {
                    if (! isset($map[$r->e])
                        || ($active !== null && ! isset($active[(int) $r->me]))
                        || isset($excluded[$r->o . ':' . $r->me])) {
                        continue;
                    }
                    $offers[$r->o] = ($offers[$r->o] ?? []) + $map[$r->e];
                }
                foreach ($offers as $competencies) {
                    foreach ($competencies as $c => $_) {
                        $counts[$c] = ($counts[$c] ?? 0) + 1;
                    }
                }
            }
        }

        return self::$marketCache[$key] = $counts;
    }

    /** Filas (competency_id, job_count) como tabla derivada; sin filas si no hay conteos. */
    private function valuesSql(array $counts): string
    {
        if ($counts === []) {
            return 'SELECT NULL AS competency_id, 0 AS job_count FROM DUAL WHERE 1 = 0';
        }
        $rows = [];
        foreach ($counts as $c => $n) {
            $rows[] = 'ROW(' . (int) $c . ', ' . (int) $n . ')';
        }

        return 'SELECT column_0 AS competency_id, column_1 AS job_count FROM (VALUES ' . implode(', ', $rows) . ') AS v';
    }
}