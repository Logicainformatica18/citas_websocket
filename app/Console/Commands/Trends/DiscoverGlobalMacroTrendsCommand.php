<?php

namespace App\Console\Commands\Trends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DiscoverGlobalMacroTrendsCommand extends Command
{
    protected $signature = 'trends:discover-global
                            {--year= : Año específico}
                            {--quarter= : Trimestre específico}
                            {--sleep=8 : Segundos entre requests GPT}';

    protected $description = 'Descubre macro-tendencias globales estratégicas desde fuentes Tier 1–5 y las guarda en macro_trend_raw';

    public function handle()
    {
        $year    = $this->option('year') ?? now()->year;
        $quarter = $this->option('quarter') ?? now()->quarter;
        $sleep   = (int) $this->option('sleep');

        $this->info("🌍 Descubriendo MACRO-TENDENCIAS GLOBALES – Y{$year} Q{$quarter}");

        try {

            $prompt = $this->buildPrompt($year);
            $response = $this->gptSearch($prompt);

            if (is_string($response)) {
                $response = $this->extractJson($response);
            }

            if (!isset($response['trends']) || !is_array($response['trends'])) {
                throw new \Exception('Respuesta GPT inválida');
            }
$this->info('Total trends recibidas: ' . count($response['trends']));

            foreach ($response['trends'] as $trend) {

                if (empty($trend['name']) || empty($trend['source']['url'])) {
                    continue;
                }

                $url = strtok(trim($trend['source']['url']), '?');
                $url = rtrim($url, '/');

                $title = trim($trend['source']['title'] ?? '');

                // 🔎 Evitar duplicados básicos
                $exists = DB::table('macro_trend_raw')
                    ->where(function ($q) use ($url, $title) {
                        $q->where('source_url', $url);

                        if ($title) {
                            $q->orWhereRaw(
                                'LOWER(TRIM(source_title)) = ?',
                                [strtolower($title)]
                            );
                        }
                    })
                    ->where('year', $year)
                    ->exists();

                if ($exists) {
                    continue;
                }

               // 🔎 Validar URL antes de guardar
$urlValidation = $this->validateUrl($url);

// ❌ Si está rota (404) no la guardamos
if ($urlValidation['status'] === 'broken') {
    $this->warn("URL rota detectada: {$url}");
    continue;
}

DB::table('macro_trend_raw')->insert([
    'trend_name'   => trim($trend['name']),
    'description'  => $trend['description'] ?? null,
    'year'         => $year,
    'quarter'      => $quarter,
    'source_name'  => $trend['source']['name'] ?? null,
    'source_title' => $title,
    'source_url'   => $url,
    'source_type'  => $trend['source']['type'] ?? null,

    // 🔥 NUEVO
    'url_status'   => $urlValidation['status'],
    'url_http_code'=> $urlValidation['code'],
    'url_checked_at' => now(),

    'created_at'   => now(),
    'updated_at'   => now(),
]);

            }

            $this->info("✅ Tendencias guardadas correctamente");
            sleep($sleep);

        } catch (\Throwable $e) {

            $this->error("❌ Error en proceso global");
            $this->line("👉 {$e->getMessage()}");

            Log::error('[GLOBAL-MACRO-TRENDS]', [
                'error' => $e->getMessage(),
            ]);
        }

        $this->info('🏁 Proceso finalizado');
        return Command::SUCCESS;
    }
/* =========================================================
   VALIDAR URL
========================================================= */
protected function validateUrl(string $url): array
{
    try {
        $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; ISIL-ObserverBot/1.0)'
            ])
            ->timeout(10)
            ->get($url);

        if ($response->status() >= 200 && $response->status() < 300) {
            return ['status' => 'ok'];
        }

        return [
            'status' => 'broken',
            'http_code' => $response->status()
        ];

    } catch (\Throwable $e) {
        return [
            'status' => 'broken',
            'http_code' => null
        ];
    }
}


    /* =========================================================
       PROMPT GLOBAL
    ========================================================= */
    protected function buildPrompt(int $year): string
    {
        $previousYear = $year - 1;

        return <<<PROMPT
You are a global strategic technology intelligence analyst.

Your task is to identify 4–8 high-impact MACRO TECHNOLOGY TRENDS shaping global digital transformation.

Focus on:
- Artificial Intelligence
- Cloud & Infrastructure
- Cybersecurity
- Data & Analytics
- Digital Skills
- Enterprise Software
- Automation
- Emerging technologies
- Higher education digital transformation

REQUIREMENTS:
- Prefer sources from {$previousYear} or {$year}.
- If publication year is uncertain, include only highly authoritative institutions.
- Do NOT invent sources.
- Use well-known global institutions.


PRIORITY SOURCES (Highest Authority)

Tier 1:
HolonIQ
WEF (Future of Jobs)
OECD (Digital Economy Outlook / Skills for Jobs)
UNESCO (AI Competency Framework / Digital Education)
Gartner (Hype Cycle / Top Trends / Cybersecurity)
McKinsey Tech & AI
PwC AI Jobs Barometer
Deloitte Technology Insights
LinkedIn Economic Graph
Coursera Global Skills Report
Lightcast (Burning Glass)

Tier 2:
TechCrunch
The Verge
ZDNet
VentureBeat
Computerworld
InformationWeek
TechRepublic
Network World
The Register
TechTarget

Tier 3:
IEEE Xplore
KDnuggets
Towards Data Science
Research conferences

Tier 4:
AWS re:Invent
Microsoft Ignite
Oracle CloudWorld
Salesforce Dreamforce
Cisco Live
CES
CloudSummit

Tier 5:
EDUCAUSE Horizon Report
EdTech Magazine
Somos Digital (DigComp)
APTC Peru

Return STRICT JSON only.

IMPORTANT:
- Trend names MUST be written in Spanish.
- Descriptions MUST be written in Spanish.
- Do NOT translate the source title.
- Keep the original source URL.

{
  "trends": [
    {
      "name": "Trend title",
      "description": "Short strategic explanation (max 3 sentences)",
      "source": {
        "name": "Institution name",
        "title": "Report or article title",
        "url": "https://example.com",
        "type": "report | article | study | conference | blog"
      }
    }
  ]
}

Do NOT include text outside JSON.
PROMPT;
    }

    /* =========================================================
       GPT CALL
    ========================================================= */
protected function gptSearch(string $prompt): string
{
    $apiKey = config('services.openai.key');

    if (!$apiKey) {
        throw new \Exception('OPENAI_API_KEY no configurada');
    }

    $response = Http::withToken($apiKey)
        ->timeout(400)
        ->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-5',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a global strategic technology intelligence analyst. Always return strictly valid JSON.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ]);

    if (!$response->successful()) {
        throw new \Exception('Error GPT: ' . $response->body());
    }

    $json = $response->json();

    $text = data_get($json, 'choices.0.message.content');

    if (!$text) {
        throw new \Exception('No se pudo extraer texto de la respuesta GPT');
    }

    return $text;
}



    /* =========================================================
       EXTRAER JSON
    ========================================================= */
    protected function extractJson(string $text): array
    {
        if (preg_match('/\{.*\}/s', $text, $m)) {
            return json_decode($m[0], true) ?? [];
        }
        return [];
    }
}
