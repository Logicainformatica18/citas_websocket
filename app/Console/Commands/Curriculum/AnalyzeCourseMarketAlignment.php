<?php
namespace App\Console\Commands\Curriculum;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
     use Illuminate\Support\Str;
class AnalyzeCourseMarketAlignment extends Command
{
    protected $signature = 'curriculum:analyze-course
                            {course_id : ID del curso}
                            {--year=2025}
                            {--quarter=2}
                            {--save=1}';

    protected $description = 'Analiza un curso contra señales reales de mercado y genera recomendación IA';

    private function cleanUtf8($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->cleanUtf8($value);
            }
            return $data;
        }

        if (is_string($data)) {
            return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        }

        return $data;
    }

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

        $jobIds = collect()
            ->merge(DB::table('technology_job')->whereIn('market_entity_id', $entityIds)->pluck('job_offer_id'))
            ->merge(DB::table('language_job')->whereIn('market_entity_id', $entityIds)->pluck('job_offer_id'))
            ->merge(DB::table('methodology_job')->whereIn('market_entity_id', $entityIds)->pluck('job_offer_id'))
            ->unique()
            ->values();

        $jobDemand = $jobIds->isNotEmpty()
            ? DB::table('job_offers')->whereIn('id', $jobIds)->whereYear('published_at', $year)->count()
            : 0;

        $trendSignals = DB::table('entity_trends')
            ->whereIn('market_entity_id', $entityIds)
            ->where('year', $year)
            ->where('quarter', $quarter)
            ->count();

        $relatedCertifications = DB::table('certifications')
            ->whereIn('market_entity_id', $entityIds)
            ->pluck('name')
            ->values();

        $topMarketTech = DB::table('technology_metrics')
            ->whereYear('run_date', $year)
            ->orderByDesc('jobs_found_count')
            ->limit(5)
            ->pluck('technology_name')
            ->values();

        $competencies = DB::table('competency_course as cc')
            ->join('competencies as comp', 'comp.id', '=', 'cc.competency_id')
            ->where('cc.course_id', $courseId)
            ->pluck('comp.name')
            ->values();



$normalizedCourse = Str::ascii(strtolower(trim($course->name)));

$syllabus = DB::table('syllabus as s')
    ->select('s.*')
    ->where('s.status', 'processed')
    ->whereRaw("
        LOWER(?) COLLATE utf8mb4_general_ci =
        LOWER(JSON_UNQUOTE(JSON_EXTRACT(s.structured_data, '$.curso')))
        COLLATE utf8mb4_general_ci
    ", [$course->name])
    ->orderByDesc('s.created_at') // 🔥 último escaneado
    ->first();

Log::info('SYLLABUS EXACT MATCH', [
    'course_id' => $courseId,
    'course_name' => $course->name,
    'matched_syllabus' => $syllabus
        ? json_decode($syllabus->structured_data, true)['curso'] ?? null
        : null
]);

$syllabusText = $syllabus?->raw_text ?? '';

if ($syllabusText) {
    $syllabusText = iconv('UTF-8', 'UTF-8//IGNORE', $syllabusText);
    $syllabusText = mb_convert_encoding($syllabusText, 'UTF-8', 'UTF-8');
    $syllabusText = substr($syllabusText, 0, 5000);
}

        $context = $this->cleanUtf8([
            'course_name' => $course->name,
            'course_competencies' => $competencies,
            'current_languages' => $languages->pluck('name'),
            'current_technologies' => $technologies->pluck('name'),
            'current_methodologies' => $methodologies->pluck('name'),
            'job_demand_year' => $jobDemand,
            'trend_signals_quarter' => $trendSignals,
            'related_certifications' => $relatedCertifications,
            'top_market_technologies' => $topMarketTech
        ]);
$contextJson = $this->cleanUtf8(
    json_encode($context, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE)
);
  
$prompt = <<<PROMPT
Actúa como un experto en diseño curricular, empleabilidad tecnológica y análisis de tendencias del mercado laboral en tecnología.

Cuentas con acceso a información actualizada del mercado laboral global:
- demanda laboral real
- evolución de tecnologías
- habilidades requeridas por roles técnicos
- tendencias de la industria

Tu objetivo es analizar un curso académico y generar un diagnóstico estratégico profundo, accionable y orientado a decisiones curriculares.

Debes evaluar:
- aplicabilidad real
- vigencia tecnológica
- permanencia en el tiempo
- impacto en empleabilidad

Evita respuestas superficiales.

=====================
SÍLABO DEL CURSO:
=====================
{$syllabusText}

=====================
CONTEXTO DEL CURSO:
=====================
{$contextJson}

=====================
ANÁLISIS INTERNO OBLIGATORIO (NO MOSTRAR)
=====================

Antes de responder, evalúa:

- Nivel del curso (básico, intermedio, avanzado)
- Rol en empleabilidad (entry-level, soporte, especialización)
- Tipo de curso (core técnico, habilitador, aplicado, transversal)
- Brechas:
  - tecnológica
  - aplicación real
  - empleabilidad
  - actualidad
- Vigencia de tecnologías enseñadas
- Aplicabilidad práctica real
- Qué exige el mercado y el curso no cubre

⚠️ REGLA CRÍTICA:
NO recomendar tecnologías modernas por tendencia si no están justificadas en el sílabo.

=====================
SALIDA (JSON OBLIGATORIO)
=====================

Devuelve SOLO JSON válido con esta estructura:

{
  "course_category": "",
  "alignment_score": 0,
  "diagnosis": {
    "strategic": {
      "alignment_level": "",
      "course_type": "",
      "curricular_role": ""
    },
    "gaps": {
      "technological": "",
      "practical": "",
      "employability": "",
      "currency": ""
    },
    "technology_evaluation": {
      "relevance": "",
      "pros_cons": "",
      "market_usage": ""
    },
    "obsolescence": {
      "level": "",
      "justification": ""
    },
    "recommendations": {
      "type": "",
      "what_to_change": "",
      "where_to_change": "",
      "how_to_change": ""
    },
    "sessions_analysis": [
      {
        "session": "",
        "issue": "",
        "gap": "",
        "recommendation": "",
        "priority": ""
      }
    ],
    "impact": {
      "employability": "",
      "alignment_improvement": "",
      "value_perception": ""
    },
    "risk": "",
    "applicability": {
      "roles": [],
      "real_tasks": []
    },
    "practical_level": {
      "current_level": "",
      "missing_elements": ""
    },
    "curricular_integration": "",
    "evolution_opportunities": "",
    "strategic_questions": []
  },
  "missing_technologies": [],
  "missing_methodologies": [],
  "recommended_certifications": [],
  "market_justification": ""
}

=====================
REGLAS
=====================

- No agregar texto fuera del JSON
- No repetir el sílabo
- Ser crítico, no descriptivo
- Justificar decisiones con lógica
- Mantener coherencia con el contenido real del sílabo
- Priorizar empleabilidad real sobre teoría

🎯 Objetivo:
Generar un diagnóstico accionable para decisiones curriculares.

PROMPT;
 Log::info('AI REQUEST', [
    'course_id' => $courseId,
    'course_name' => $course->name,
    'job_demand' => $jobDemand,
    'trend_signals' => $trendSignals,
    'context' => $context,
    'prompt_length' => strlen($prompt),
    'prompt_preview' => substr($prompt, 0, 1000) // evita logs gigantes
]);
        $response = Http::withToken(env('OPENAI_API_KEY'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'Responde únicamente JSON válido'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.1
            ]);

        if (!$response->successful()) {
            $this->error("Error IA");
            return;
        }

        $content = $response->json()['choices'][0]['message']['content'] ?? null;

        if (!$content) {
            $this->error("Respuesta vacía");
            return;
        }
Log::info('AI RAW RESPONSE', [
    'course_id' => $courseId,
    'raw_response' => $content
]);
        $content = trim($content);
        $content = preg_replace('/```json|```/i', '', $content);

        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start !== false && $end !== false) {
            $content = substr($content, $start, $end - $start + 1);
        }

        $json = json_decode($content, true);
$currentDomainTech = collect($technologies->pluck('name'))
    ->map(fn($t) => strtolower($t));

$missingTechnologies = collect($json['missing_technologies'] ?? [])
    ->filter(function ($tech) use ($currentDomainTech) {

        $tech = strtolower($tech);

        // 🔥 SOLO permitir tecnologías del MISMO DOMINIO (Linux / sistemas)
        $allowedKeywords = [
            'linux', 'bash', 'shell', 'network', 'security',
            'server', 'monitoring', 'system', 'infra'
        ];

        foreach ($allowedKeywords as $keyword) {
            if (str_contains($tech, $keyword)) {
                return true;
            }
        }

        return false;
    })
    ->values();

$json['missing_technologies'] = $missingTechnologies;
        if (!$json) {
            Log::error('JSON inválido', ['raw' => $content]);
            $this->error("JSON inválido");
            return;
        }

        if ($save) {
            DB::table('course_ai_recommendations')->insert([
                'course_id' => $courseId,
              'diagnosis' => json_encode($json['diagnosis'] ?? [], JSON_UNESCAPED_UNICODE),
                'suggested_entities' => json_encode($json['missing_technologies'] ?? []),
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
