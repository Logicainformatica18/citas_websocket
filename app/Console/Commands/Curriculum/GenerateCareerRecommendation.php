<?php

namespace App\Console\Commands\Curriculum;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\Career;

class GenerateCareerRecommendation extends Command
{
    protected $signature = 'career:recommend
                            {career_id : ID de la carrera}
                            {--year= : Año de análisis (default actual)}';

    protected $description = 'Genera recomendaciones estructurales por competencia';

    public function handle()
    {
        $careerId = (int) $this->argument('career_id');
        $year = $this->option('year')
            ? (int) $this->option('year')
            : now()->year;

        $career = Career::find($careerId);

        if (!$career) {
            $this->error("❌ Carrera no encontrada.");
            return;
        }

        $this->info("📘 Carrera: {$career->name}");
        $this->info("📅 Año analizado: {$year}");

        /* ==========================================================
           1️⃣ MATRIZ ESTRATÉGICA POR COMPETENCIA
        ========================================================== */

        $rows = DB::select("
        WITH market_base AS (
            SELECT
                cc.competency_id,
                COUNT(DISTINCT jo.id) AS job_count
            FROM competency_course cc
            LEFT JOIN course_technology ct ON ct.course_id = cc.course_id
            LEFT JOIN technology_job tj ON tj.technology_id = ct.technology_id
            LEFT JOIN job_offers jo
                ON jo.id = tj.job_offer_id
               AND YEAR(jo.published_at) = ?
            GROUP BY cc.competency_id
        ),
        market_ranked AS (
            SELECT
                competency_id,
                job_count,
                NTILE(4) OVER (ORDER BY job_count DESC) AS market_quartile
            FROM market_base
        ),
        trend_base AS (
            SELECT
                cc.competency_id,
                COUNT(DISTINCT et.id) AS trend_count
            FROM competency_course cc
            LEFT JOIN course_technology ct ON ct.course_id = cc.course_id
            LEFT JOIN technologies t ON t.id = ct.technology_id
            LEFT JOIN entity_trends et
                ON et.market_entity_id = t.market_entity_id
               AND et.year = ?
            GROUP BY cc.competency_id
        ),
        trend_ranked AS (
            SELECT
                competency_id,
                trend_count,
                NTILE(4) OVER (ORDER BY trend_count DESC) AS trend_quartile
            FROM trend_base
        ),
        course_count AS (
            SELECT
                competency_id,
                COUNT(DISTINCT course_id) AS total_courses
            FROM competency_course
            GROUP BY competency_id
        )

        SELECT
            comp.name,
            COALESCE(cc.total_courses,0) AS total_courses,
            COALESCE(mr.job_count,0) AS market_total,
            COALESCE(tr.trend_count,0) AS trend_total,
            COALESCE(mr.market_quartile,4) AS market_q,
            COALESCE(tr.trend_quartile,4) AS trend_q

        FROM competencies comp
        LEFT JOIN course_count cc ON cc.competency_id = comp.id
        LEFT JOIN market_ranked mr ON mr.competency_id = comp.id
        LEFT JOIN trend_ranked tr ON tr.competency_id = comp.id
        WHERE comp.career_id = ?
        ORDER BY market_total DESC
        ", [$year, $year, $careerId]);

        if (empty($rows)) {
            $this->error("❌ No hay competencias registradas.");
            return;
        }

        /* ==========================================================
           2️⃣ CLASIFICACIÓN ESTRATÉGICA AUTOMÁTICA
        ========================================================== */

        $competencyBlocks = collect($rows)->map(function($r){

            $classification = match(true) {

                $r->market_q == 1 && $r->trend_q == 1
                    => 'Alta Prioridad Estratégica',

                $r->market_q == 1 && $r->trend_q >= 3
                    => 'Desbalance Mercado',

                $r->trend_q == 1 && $r->market_q >= 3
                    => 'Desbalance Tendencia',

                $r->market_total == 0 && $r->trend_total == 0
                    => 'Riesgo Crítico',

                default => 'Equilibrada'
            };

            return "
Competencia: {$r->name}
Cursos asociados: {$r->total_courses}
Mercado: Q{$r->market_q}
Tendencia: Q{$r->trend_q}
Clasificación: {$classification}
";
        })->implode("\n---------------------------------\n");

        /* ==========================================================
           3️⃣ PROMPT ENFOCADO EN DECISIÓN ESTRUCTURAL
        ========================================================== */

        $prompt = "
Eres consultor estratégico curricular del Observatorio ISIL.

Tu tarea es decidir acciones estructurales por competencia.
No describas.
No repitas métricas.
No hagas diagnóstico narrativo.
Decide acción curricular.

Carrera: {$career->name}
Año: {$year}

Competencias evaluadas:

{$competencyBlocks}

Para cada competencia define:

- Acción recomendada (Reforzar, Reformular, Integrar transversalmente, Reducir, Fusionar, Mantener)
- Justificación estructural breve
- Nivel de urgencia (Alta, Media, Baja)

Responde en Markdown estructurado así:

## Competencia: Nombre

**Acción recomendada:**
**Justificación:**
**Urgencia:**

Repite para cada competencia.
";

        /* ==========================================================
           4️⃣ LLAMADA A OPENAI
        ========================================================== */

        $response = Http::withToken(env('OPENAI_API_KEY'))->post(
            'https://api.openai.com/v1/chat/completions',
            [
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres consultor curricular estratégico.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2,
                'max_tokens' => 1500,
            ]
        );

        if (!$response->ok()) {
            $this->error("❌ Error al conectar con OpenAI.");
            return;
        }

        $recommendation = trim($response->json('choices.0.message.content'));

        /* ==========================================================
           5️⃣ GUARDAR RESULTADO
        ========================================================== */

        $career->update([
            'strategic_recommendation' => $recommendation,
            'recommendation_generated_at' => now(),
            'recommendation_year' => $year,
        ]);

        $this->info("✅ Recomendaciones estructurales generadas correctamente.");
    }
}
