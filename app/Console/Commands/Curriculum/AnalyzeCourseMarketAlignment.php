<?php

namespace App\Console\Commands\Curriculum;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AnalyzeCourseMarketAlignment extends Command
{
    protected $signature = 'curriculum:analyze-course
                            {course_id : ID del curso}
                            {--year=2025}
                            {--quarter=2}
                            {--save=1}';

    protected $description = 'Analiza un curso contra señales reales de mercado y genera recomendación IA';

    public function handle()
    {
        $courseId = (int) $this->argument('course_id');
        $year = (int) $this->option('year');
        $quarter = (int) $this->option('quarter');
        $save = (bool) $this->option('save');

        $course = DB::table('courses')->where('id', $courseId)->first();

        if (!$course) {
            $this->error("Curso no encontrado");
            return;
        }

        $this->info("Analizando: {$course->name}");

        /* =====================================================
           1️⃣ ENTIDADES DEL CURSO
        ===================================================== */

        $languages = DB::table('course_language as cl')
            ->join('languages as l', 'l.id', '=', 'cl.language_id')
            ->where('cl.course_id', $courseId)
            ->select('l.name', 'l.market_entity_id')
            ->get();

        $technologies = DB::table('course_technology as ct')
            ->join('technologies as t', 't.id', '=', 'ct.technology_id')
            ->where('ct.course_id', $courseId)
            ->select('t.name', 't.market_entity_id')
            ->get();

        $methodologies = DB::table('course_methodology as cm')
            ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
            ->where('cm.course_id', $courseId)
            ->select('m.name', 'm.market_entity_id')
            ->get();

        $entityIds = collect()
            ->merge($languages->pluck('market_entity_id'))
            ->merge($technologies->pluck('market_entity_id'))
            ->merge($methodologies->pluck('market_entity_id'))
            ->filter()
            ->unique()
            ->values();

        /* =====================================================
           2️⃣ DEMANDA REAL (JOBS)
        ===================================================== */

        $jobDemand = DB::table('job_offers as jo')
            ->leftJoin('technology_job as tj', 'tj.job_offer_id', '=', 'jo.id')
            ->leftJoin('language_job as lj', 'lj.job_offer_id', '=', 'jo.id')
            ->leftJoin('methodology_job as mj', 'mj.job_offer_id', '=', 'jo.id')
            ->where(function ($q) use ($entityIds) {
                $q->whereIn('tj.market_entity_id', $entityIds)
                  ->orWhereIn('lj.market_entity_id', $entityIds)
                  ->orWhereIn('mj.market_entity_id', $entityIds);
            })
            ->distinct('jo.id')
            ->count('jo.id');

        /* =====================================================
           3️⃣ TENDENCIAS REALES (entity_trends)
        ===================================================== */

        $trendSignals = DB::table('entity_trends')
            ->whereIn('market_entity_id', $entityIds)
            ->where('year', $year)
            ->where('quarter', $quarter)
            ->count();

        /* =====================================================
           4️⃣ CERTIFICACIONES RELACIONADAS
        ===================================================== */

        $relatedCertifications = DB::table('certifications')
            ->whereIn('market_entity_id', $entityIds)
            ->pluck('name')
            ->values();

        /* =====================================================
           5️⃣ TOP TECNOLOGÍAS DEL MERCADO (para comparación)
        ===================================================== */

        $topMarketTech = DB::table('technology_metrics')
            ->whereYear('run_date', $year)
            ->orderByDesc('jobs_found_count')
            ->limit(5)
            ->pluck('technology_name')
            ->values();

        /* =====================================================
           6️⃣ CONSTRUCCIÓN CONTEXTO IA
        ===================================================== */
/* =====================================================
   🔹 COMPETENCIA DEL CURSO
===================================================== */

$competencies = DB::table('competency_course as cc')
    ->join('competencies as comp', 'comp.id', '=', 'cc.competency_id')
    ->where('cc.course_id', $courseId)
    ->select('comp.name')
    ->pluck('comp.name')
    ->values();

      $context = [
    'course_name' => $course->name,

    'course_competencies' => $competencies,

    'current_languages' => $languages->pluck('name'),
    'current_technologies' => $technologies->pluck('name'),
    'current_methodologies' => $methodologies->pluck('name'),

    'job_demand_count' => $jobDemand,
    'trend_signals_count' => $trendSignals,

    'related_certifications' => $relatedCertifications,
    'top_market_technologies' => $topMarketTech
];


      $prompt = "
Eres un experto en diseño curricular y alineación estratégica con mercado laboral.

Analiza el curso usando su competencia académica como principal criterio de interpretación.

Contexto:
" . json_encode($context, JSON_PRETTY_PRINT) . "

PASO 1:
Clasifica el curso según su naturaleza real basándote en su competencia:
- Tecnico_programacion
- Tecnico_infraestructura
- Analitico_cuantitativo
- Estrategico_empresarial
- Transversal_conceptual

PASO 2:
Evalúa alineación considerando:
- Coherencia con la competencia
- Entidades actuales del curso
- Señales reales de empleo
- Tendencias detectadas

REGLAS ESTRICTAS:

- No sugerir lenguajes de programación si la competencia no menciona desarrollo o implementación técnica.
- No sugerir BI o herramientas analíticas si el curso no trata análisis de datos.
- No sugerir Machine Learning salvo que la competencia lo justifique.
- Para cursos estratégicos priorizar marcos, estándares y certificaciones.
- Para cursos técnicos priorizar herramientas concretas y certificaciones técnicas.
- Mantener coherencia absoluta con la competencia declarada.

SI course_category = Estrategico_empresarial:
- Priorizar certificaciones internacionales relevantes.
- Priorizar marcos como ISO, ESG, OKR, Balanced Scorecard.
- Sugerir estándares de responsabilidad social, sostenibilidad o gestión.
- No dejar la lista vacía si existen certificaciones coherentes.

SI course_category = Tecnico_programacion:
- Sugerir lenguajes, frameworks y certificaciones técnicas.

SI course_category = Tecnico_infraestructura:
- Sugerir herramientas de administración y certificaciones oficiales.

SI course_category = Analitico_cuantitativo:
- Sugerir herramientas estadísticas y certificaciones analíticas.

PASO 3:
Generar JSON con:

{
  \"course_category\": \"\",
  \"diagnosis\": \"\",
  \"alignment_score\": 0,
  \"missing_technologies\": [],
  \"missing_methodologies\": [],
  \"recommended_certifications\": [],
  \"market_justification\": \"\"
}

Devuelve SOLO JSON válido.
";


        /* =====================================================
           7️⃣ LLAMADA IA
        ===================================================== */

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
            'Content-Type' => 'application/json'
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'Responde solo JSON válido'],
                ['role' => 'user', 'content' => $prompt]
            ],
          'temperature' => 0.1

        ]);

        if (!$response->successful()) {
            $this->error("Error IA");
            return;
        }

        $content = $response->json()['choices'][0]['message']['content'] ?? null;
        $json = json_decode($content, true);

        if (!$json) {
            $this->error("IA no devolvió JSON válido");
            $this->line($content);
            return;
        }

        $this->info("Diagnóstico:");
        $this->line($json['diagnosis'] ?? '');

        /* =====================================================
           8️⃣ GUARDAR
        ===================================================== */

        if ($save) {
            DB::table('course_ai_recommendations')->insert([
                'course_id' => $courseId,
                'diagnosis' => $json['diagnosis'] ?? null,
                'suggested_entities' => json_encode($json['missing_technologies'] ?? []),
                'suggested_methodologies' => json_encode($json['missing_methodologies'] ?? []),
                'suggested_certifications' => json_encode($json['recommended_certifications'] ?? []),
                'market_evidence' => json_encode($json['market_justification'] ?? null),
                'created_at' => now(),
            ]);
        }

        $this->info("Análisis completado.");
    }
}
