<?php

namespace App\Console\Commands\Curriculum;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
       2️⃣ DEMANDA REAL
    ===================================================== */

    $jobIds = collect()
        ->merge(DB::table('technology_job')->whereIn('market_entity_id', $entityIds)->pluck('job_offer_id'))
        ->merge(DB::table('language_job')->whereIn('market_entity_id', $entityIds)->pluck('job_offer_id'))
        ->merge(DB::table('methodology_job')->whereIn('market_entity_id', $entityIds)->pluck('job_offer_id'))
        ->unique()
        ->values();

    $jobDemand = $jobIds->isNotEmpty()
        ? DB::table('job_offers')->whereIn('id', $jobIds)->whereYear('published_at', $year)->count()
        : 0;

    /* =====================================================
       3️⃣ TENDENCIAS
    ===================================================== */

    $trendSignals = DB::table('entity_trends')
        ->whereIn('market_entity_id', $entityIds)
        ->where('year', $year)
        ->where('quarter', $quarter)
        ->count();

    /* =====================================================
       4️⃣ CERTIFICACIONES
    ===================================================== */

    $relatedCertifications = DB::table('certifications')
        ->whereIn('market_entity_id', $entityIds)
        ->pluck('name')
        ->values();

    /* =====================================================
       5️⃣ TOP TECNOLOGÍAS
    ===================================================== */

    $topMarketTech = DB::table('technology_metrics')
        ->whereYear('run_date', $year)
        ->orderByDesc('jobs_found_count')
        ->limit(5)
        ->pluck('technology_name')
        ->values();

    /* =====================================================
       6️⃣ COMPETENCIAS
    ===================================================== */

    $competencies = DB::table('competency_course as cc')
        ->join('competencies as comp', 'comp.id', '=', 'cc.competency_id')
        ->where('cc.course_id', $courseId)
        ->pluck('comp.name')
        ->values();

    /* =====================================================
       🔥 6.5️⃣ MATCH DE SÍLABO (CORREGIDO)
    ===================================================== */

    $syllabusList = DB::table('syllabus')
        ->where('status', 'processed')
        ->get();

    $bestScore = 0;
    $bestSyllabus = null;

    foreach ($syllabusList as $s) {

        $data = json_decode($s->structured_data, true);
        if (!$data || empty($data['curso'])) continue;

        $score = 0;

        // MATCH NOMBRE
        similar_text(
            \Illuminate\Support\Str::ascii(strtolower($course->name)),
            \Illuminate\Support\Str::ascii(strtolower($data['curso'])),
            $nameScore
        );

        $score += $nameScore * 0.6;

        // MATCH TECNOLOGÍAS
        $courseTech = $technologies->pluck('name')->map(fn($t) => strtolower(trim($t)));

        $syllabusTech = collect($data['tecnologias'] ?? [])
            ->map(fn($t) => strtolower(trim(is_array($t) ? $t['nombre'] : $t)));

        $score += $courseTech->intersect($syllabusTech)->count() * 5;

        // MATCH LENGUAJES
        $courseLang = $languages->pluck('name')->map(fn($l) => strtolower(trim($l)));

        $syllabusLang = collect($data['lenguajes'] ?? [])
            ->map(fn($l) => strtolower(trim($l)));

        $score += $courseLang->intersect($syllabusLang)->count() * 4;

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestSyllabus = $s;
        }
    }

    Log::info('SYLLABUS MATCH', [
        'best_score' => $bestScore,
        'found' => !!$bestSyllabus
    ]);

    /* =====================================================
       RESULTADO SÍLABO
    ===================================================== */

$syllabusText = $bestSyllabus?->raw_text;

if ($syllabusText) {
    // 🔥 esto es lo que realmente arregla
    $syllabusText = iconv('UTF-8', 'UTF-8//IGNORE', $syllabusText);

    // opcional pero recomendado
    $syllabusText = mb_convert_encoding($syllabusText, 'UTF-8', 'UTF-8');

    $syllabusText = substr($syllabusText, 0, 5000);
}

    /* =====================================================
       CONTEXTO IA
    ===================================================== */

    $context = [
        'course_name' => $course->name,
        'syllabus' => $syllabusText,
        'course_competencies' => $competencies,
        'current_languages' => $languages->pluck('name'),
        'current_technologies' => $technologies->pluck('name'),
        'current_methodologies' => $methodologies->pluck('name'),
        'job_demand_year' => $jobDemand,
        'trend_signals_quarter' => $trendSignals,
        'related_certifications' => $relatedCertifications,
        'top_market_technologies' => $topMarketTech
    ];


    /* =====================================================
       PROMPT
    ===================================================== */

    $prompt = "
Eres un experto en diseño curricular y alineación con mercado laboral.

Analiza el curso usando:

1. Su sílabo REAL (principal fuente de verdad)
2. Sus competencias académicas
3. Señales del mercado

=====================
SÍLABO DEL CURSO:
=====================
{$syllabusText}

=====================
CONTEXTO ESTRUCTURADO:
=====================
" . json_encode($context, JSON_PRETTY_PRINT) . "

=====================
INSTRUCCIONES:
=====================

PASO 1:
Clasifica el curso según su naturaleza real.

PASO 2:
Evalúa alineación considerando:
- Contenido real del sílabo
- Competencias
- Tecnologías actuales
- Demanda laboral
- Tendencias

REGLAS:
- El sílabo tiene PRIORIDAD sobre cualquier otro dato
- No inventar tecnologías no coherentes con el contenido
- Detectar brechas reales entre contenido y mercado

PASO 3:
Genera JSON:

{
  \"course_category\": \"\",
  \"diagnosis\": \"Explicación detallada (mínimo 120 palabras)\",
  \"alignment_score\": 0,
  \"missing_technologies\": [],
  \"missing_methodologies\": [],
  \"recommended_certifications\": [],
  \"market_justification\": \"\"
}

REGLAS:

- 'missing_technologies' debe contener tecnologías específicas del mercado (herramientas reales, frameworks o plataformas)
- Ejemplo correcto: 'Docker', 'Kubernetes', 'Azure SQL', 'Power BI'
- Ejemplo incorrecto: 'lenguajes modernos', 'herramientas de datos'

- El diagnóstico debe ser profundo, técnico y detallado (no superficial)
- No usar términos genéricos

Devuelve SOLO JSON válido.
";
 Log::info('AI REQUEST', [
        'syllabus_length' => strlen($syllabusText ?? ''),
        'has_syllabus' => !!$syllabusText
    ]);

    /* =====================================================
       LLAMADA IA
    ===================================================== */

    $response = Http::withToken(env('OPENAI_API_KEY'))
        ->post('https://api.openai.com/v1/chat/completions', [
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
        return;
    }

    /* =====================================================
       FILTRO TECNOLOGÍAS DUPLICADAS
    ===================================================== */

    $currentTech = collect($technologies->pluck('name'))
        ->merge($languages->pluck('name'))
        ->map(fn($t) => strtolower(trim($t)));

    $missingTechnologies = collect($json['missing_technologies'] ?? [])
        ->reject(fn($tech) => $currentTech->contains(strtolower(trim($tech))))
        ->values();

    /* =====================================================
       GUARDAR
    ===================================================== */

    if ($save) {
        DB::table('course_ai_recommendations')->insert([
            'course_id' => $courseId,
            'diagnosis' => $json['diagnosis'] ?? null,
            'suggested_entities' => json_encode($missingTechnologies),
            'suggested_methodologies' => json_encode($json['missing_methodologies'] ?? []),
            'suggested_certifications' => json_encode($json['recommended_certifications'] ?? []),
            'market_evidence' => json_encode([
                'job_demand_year' => $jobDemand,
                'trend_signals_quarter' => $trendSignals,
                'justification' => $json['market_justification'] ?? null
            ]),
            'created_at' => now(),
        ]);
    }

    $this->info("Análisis completado.");
}
}
