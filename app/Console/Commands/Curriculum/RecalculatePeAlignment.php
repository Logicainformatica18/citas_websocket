<?php

namespace App\Console\Commands\Curriculum;

use App\Services\Indicators\CurricularDemand;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Prueba;

class RecalculatePeAlignment extends Command
{
    protected $signature = 'pe:recalculate
                            {career_id? : ID de la carrera}
                            {--all : todas las carreras activas (el conteo de mercado se calcula una vez)}
                            {--year= : Año (default actual)}';

    protected $description = 'Recalcula y materializa resultados PE Alignment (idéntico al modelo dinámico)';

    public function handle()
    {
        if ($this->option('all')) {
            foreach (DB::table('careers')->where('active', 1)->orderBy('id')->pluck('id') as $id) {
                $this->call('pe:recalculate', ['career_id' => $id, '--year' => $this->option('year')]);
            }

            return self::SUCCESS;
        }
        if (! $this->argument('career_id')) {
            $this->error('Indica una carrera o --all.');

            return self::FAILURE;
        }

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

    /**
     * Ofertas del año por competencia: por cada fuente (tecnologías, lenguajes, metodologías) una
     * oferta cuenta una vez por competencia alcanzada a través de los cursos; se suman las tres
     * fuentes (igual que el SQL original: COUNT(DISTINCT oferta) por fuente y SUM). Sale de la
     * pasada del año de CurricularDemand (precalculada por indicators:refresh-all), compartida por
     * todas las carreras: antes cada carrera recorría el año (~60 s).
     *
     * @return array<int, int> competency_id => job_count (solo > 0), ordenado por competency_id
     */
    private function marketCounts(int $year): array
    {
        return CurricularDemand::forYear($year)['competency_jobs'];
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