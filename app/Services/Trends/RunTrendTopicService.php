<?php

namespace App\Services\Trends;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ScrapingSource;
use App\Models\TechnologyTrend;
use App\Models\TrendTopic;
use App\Models\Technology;
use App\Models\TechnologyTrendTechnology;


class RunTrendTopicService
{


 
    /* =========================================================
     * Public API
     * ========================================================= */
   public function run(TrendTopic $topic): int
{
    $label  = $topic->topic_name;
    $intent = $topic->intent;

    $query = $this->normalizeQueryByIntent(
        $topic->search_query,
        $intent
    );

    $year    = now()->year;
    $quarter = ceil(now()->month / 3);

    sleep(2);

    Log::info("🔍 Ejecutando TrendTopic", [
        'topic_id' => $topic->id,
        'topic'    => $label,
        'intent'   => $intent,
        'query'    => $query,
    ]);

    $result = $this->searchBlock($query, $label, $intent);

   if (
    !$result ||
    !isset($result['tendencias'])
) {

    // 🟢 CERTIFICATION: 0 resultados es válido
    if ($intent === 'certification') {
        Log::warning("⚠️ Sin certificaciones válidas", [
            'topic_id' => $topic->id,
            'query'    => $query,
        ]);

        return 0; // 👈 NO ERROR
    }

    // 🔴 Otros intents sí fallan
    throw new \RuntimeException(
        "No se generaron resultados para el topic."
    );
}


    /* ======================================================
     * Guardar fuentes
     * ====================================================== */
    foreach ($result['used_sources'] ?? [] as $src) {
        if (empty($src['url'])) {
            continue;
        }

        ScrapingSource::firstOrCreate(
            ['url' => $src['url']],
            [
                'name'     => $src['title'] ?? 'Fuente registrada',
                'web_only' => 1,
                'notes'    => "Fuente usada para topic: {$label}",
            ]
        );
    }

    /* ======================================================
     * Guardar tendencias
     * ====================================================== */
    $saved = 0;

    foreach ($result['tendencias'] as $t) {

        /* -------- Clasificación -------- */
        if ($intent === 'certification') {

            if (!$this->isRealCertification($t)) {
                Log::info('⛔ Descartado: no es certificación real', [
                    'topic_name' => $t['topic_name'] ?? null,
                ]);
                continue;
            }

            $classification = 'certification';
        } else {
            $classification = $this->classifyResult($t, $intent);
        }

        /* -------- Fuente -------- */
        $source = null;

        if (!empty($t['source_links'][0]['url'])) {
            $source = ScrapingSource::firstOrCreate(
                ['url' => $t['source_links'][0]['url']],
                [
                    'name'     => $t['source_links'][0]['title'] ?? 'Fuente tendencia',
                    'web_only' => 1,
                    'notes'    => "Fuente principal para topic: {$label}",
                ]
            );
        }

        /* -------- Guardar TechnologyTrend -------- */
        $trend = TechnologyTrend::updateOrCreate(
            [
                'topic_name' => $t['topic_name'],
                'year'       => $year,
                'quarter'    => $quarter,
                'source_id'  => $source->id ?? 1,
            ],
            [
                'topic_category'        => $label,
                'trend_score'           => $t['trend_score'],
                'regions'               => $t['regions'] ?? [],
                'scanned_keywords'      => $t['job_search_keywords'] ?? [],
                'associated_technologies'=> $t['associated_technologies'] ?? [],
                'source_url'            => $t['source_links'][0]['url'] ?? null,
                'source_title'          => $t['source_links'][0]['title'] ?? null,
                'raw_data'              => [
                    'intent'         => $intent,
                    'classification' => $classification,
                    'payload'        => $t,
                ],
            ]
        );

        /* ======================================================
         * MATCH LIMPIO DE TECNOLOGÍAS
         * ====================================================== */
        $associated = $t['associated_technologies'] ?? [];

        if (!empty($associated)) {

            $technologies = Technology::query()
                ->whereIn('name', $associated)
                ->get();

            foreach ($technologies as $tech) {
                TechnologyTrendTechnology::updateOrCreate(
                    [
                        'technology_trend_id' => $trend->id,
                        'technology_id'       => $tech->id,
                    ],
                    [
                        'confidence_score' => 0.90,
                        'source'           => 'ai',
                    ]
                );
            }
        }

        $saved++;
    }

    return $saved;
}


    /* =========================================================
     * NORMALIZAR QUERY POR INTENT (CLAVE DEL PROBLEMA)
     * ========================================================= */
    private function normalizeQueryByIntent(string $query, string $intent): string
    {
        return match ($intent) {
            'certification' => trim(
                preg_replace(
                    '/skills?|workforce|roles?|demand|jobs?|hiring/i',
                    '',
                    $query
                )
            ) . ' official professional certification exam',

            'skill' => trim(
                preg_replace(
                    '/certifications?|certified|exam|credential/i',
                    '',
                    $query
                )
            ) . ' required skills demand',

            'workforce' => trim(
                preg_replace(
                    '/certifications?|certified|exam|credential/i',
                    '',
                    $query
                )
            ) . ' workforce roles trends',

            default => $query,
        };
    }

    /* =========================================================
     * OpenAI (search-like)
     * ========================================================= */
    private function searchBlock(string $query, string $label, string $intent): ?array
    {
        $prompt = $this->buildPrompt($query, $label, $intent);

        try {
            $res = retry(2, function () use ($prompt) {
                return Http::withToken(config('services.openai.key'))
                    ->timeout(90)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model'       => 'gpt-4o-mini',
                        'temperature' => 0.2,
                        'messages'    => [
                            [
                                'role'    => 'system',
                                'content' => 'Return ONLY valid JSON. No markdown.',
                            ],
                            [
                                'role'    => 'user',
                                'content' => $prompt,
                            ],
                        ],
                    ]);
            }, 5);

            if ($res->failed()) {
                Log::error('OpenAI HTTP Error', [
                    'status' => $res->status(),
                    'body'   => $res->body(),
                ]);
                return null;
            }

            $content = $res->json('choices.0.message.content');
            if (!$content) {
                return null;
            }

            $json = $this->extractJson($content);

            return $json ? json_decode($json, true) : null;

        } catch (\Throwable $e) {
            Log::error('OpenAI Exception', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /* =========================================================
     * PROMPT
     * ========================================================= */
 private function buildPrompt(string $query, string $label, string $intent): string
{
    $base = <<<BASE
You are a senior technology and labor-market analyst.

Perform a REALISTIC, web-like research for the following query:

{$query}

Context label: {$label}

GENERAL RULES:
- Use real-world knowledge only
- No hallucinations
- No academic degrees
- No explanations
- Output JSON ONLY
- Technologies must be REAL tools, platforms, frameworks or infrastructure
BASE;

    /* =========================================================
     * CERTIFICATIONS (ESTRICTO)
     * ========================================================= */
    if ($intent === 'certification') {
        return <<<PROMPT
{$base}

INTENT: CERTIFICATION (STRICT MODE)

ABSOLUTE RULES:
- Return ONLY OFFICIAL professional certifications or exams
- MUST include issuing organization (AWS, Microsoft, Google, IBM, Cisco, etc.)
- MUST be something a professional can actually take and certify
- DO NOT include:
  - skills
  - job roles
  - platforms
  - trends
  - training courses
  - academic degrees

Return ONLY valid JSON:

{
  "label": "{$label}",
  "tendencias": [
    {
      "topic_name": "Official certification name",
      "trend_score": 20-100,
      "regions": ["Global", "North America", "Europe"],
      "job_search_keywords": ["certification name", "exam code"],
      "associated_technologies": [],
      "source_links": [
        { "title": "Official certification page", "url": "https://official-site.com" }
      ]
    }
  ],
  "used_sources": []
}
PROMPT;
    }

    /* =========================================================
     * TECHNOLOGY TRENDS
     * ========================================================= */
    if ($intent === 'technology_trend') {
        return <<<PROMPT
{$base}

INTENT: TECHNOLOGY TREND

RULES:
- Focus on real adoption of technologies, platforms, tools or infrastructure
- associated_technologies MUST be concrete implementation technologies
- Use names as they appear in professional environments (e.g. Kubernetes, PyTorch, AWS SageMaker)
- DO NOT include certifications or soft skills

JSON FORMAT:

{
  "label": "{$label}",
  "tendencias": [
    {
      "topic_name": "Technology trend name",
      "trend_score": 20-100,
      "regions": ["Global", "North America", "Europe"],
      "job_search_keywords": ["trend keyword", "technology keyword"],
      "associated_technologies": [
        "PyTorch",
        "TensorFlow",
        "Kubernetes",
        "CUDA"
      ],
      "source_links": [
        { "title": "Industry article", "url": "https://real-site.com" }
      ]
    }
  ],
  "used_sources": []
}
PROMPT;
    }

    /* =========================================================
     * SKILLS
     * ========================================================= */
    if ($intent === 'skill') {
        return <<<PROMPT
{$base}

INTENT: SKILL

RULES:
- Return ONLY skills demanded in job postings
- associated_technologies MUST include tools used to apply the skill
- DO NOT include certifications

JSON FORMAT:

{
  "label": "{$label}",
  "tendencias": [
    {
      "topic_name": "Skill name",
      "trend_score": 20-100,
      "regions": ["Global", "North America", "Europe"],
      "job_search_keywords": ["skill keyword"],
      "associated_technologies": [
        "Python",
        "SQL",
        "Power BI"
      ],
      "source_links": [
        { "title": "Job market report", "url": "https://real-site.com" }
      ]
    }
  ],
  "used_sources": []
}
PROMPT;
    }

    /* =========================================================
     * WORKFORCE
     * ========================================================= */
    if ($intent === 'workforce') {
        return <<<PROMPT
{$base}

INTENT: WORKFORCE

RULES:
- Focus on roles, hiring trends, workforce impact
- associated_technologies MUST include technologies commonly required for the role
- DO NOT include certifications

JSON FORMAT:

{
  "label": "{$label}",
  "tendencias": [
    {
      "topic_name": "Role or workforce trend",
      "trend_score": 20-100,
      "regions": ["Global", "North America", "Europe"],
      "job_search_keywords": ["job title", "role keyword"],
      "associated_technologies": [
        "Docker",
        "AWS",
        "Terraform"
      ],
      "source_links": [
        { "title": "Labor market report", "url": "https://real-site.com" }
      ]
    }
  ],
  "used_sources": []
}
PROMPT;
    }

    /* =========================================================
     * MIXED
     * ========================================================= */
    return <<<PROMPT
{$base}

INTENT: MIXED

RULES:
- You may include certifications, skills, technologies or workforce topics
- ALWAYS include associated_technologies when applicable
- Use REAL technology names only

JSON FORMAT:

{
  "label": "{$label}",
  "tendencias": [
    {
      "topic_name": "Item name",
      "trend_score": 20-100,
      "regions": ["Global"],
      "job_search_keywords": [],
      "associated_technologies": [],
      "source_links": []
    }
  ],
  "used_sources": []
}
PROMPT;
}


    /* =========================================================
     * CLASIFICACIÓN
     * ========================================================= */
    private function classifyResult(array $t, string $intent): string
{
    $text = strtolower(json_encode($t));

    if ($intent === 'certification') {
        return 'certification';
    }

    if (preg_match('/skills?|competencies|required/', $text)) {
        return 'skill';
    }

    if (preg_match('/workforce|hiring|roles|productivity/', $text)) {
        return 'workforce';
    }

    if (preg_match('/adoption|platform|deployment/', $text)) {
        return 'technology_trend';
    }

    return 'other';
}


    /* =========================================================
     * FILTRO DURO DE CERTIFICACIONES
     * ========================================================= */
   private function isRealCertification(array $t): bool
{
    $name = strtolower($t['topic_name'] ?? '');

    // 1️⃣ Debe tener términos de certificación
    $hasCredentialTerms = preg_match(
        '/certified|certification|exam|professional|associate|specialty/',
        $name
    );

    // 2️⃣ Debe tener organización emisora REAL
    $hasIssuer = preg_match(
        '/aws|amazon|microsoft|azure|google|gcp|ibm|oracle|cisco|isc2|comptia|cncf|hashicorp/',
        $name
    );

    // 3️⃣ Bloqueos SOLO por nombre (no por payload)
    $blocked = preg_match(
        '/course|training|bootcamp|degree|university|bachelor|master/',
        $name
    );

    return $hasCredentialTerms && $hasIssuer && !$blocked;
}

    /* =========================================================
     * Helpers
     * ========================================================= */
    private function extractJson(string $text): ?string
    {
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');

        if ($start === false || $end === false) {
            return null;
        }

        return substr($text, $start, $end - $start + 1);
    }


}
