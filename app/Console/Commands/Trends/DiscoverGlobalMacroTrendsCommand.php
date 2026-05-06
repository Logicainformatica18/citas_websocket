<?php

namespace App\Console\Commands\Trends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DiscoverGlobalMacroTrendsCommand extends Command
{
    protected $signature = 'trends:discover-global
                            {--year=}
                            {--quarter=}
                            {--limit=30}';

    protected $description = 'Descubre macro tendencias usando Tavily + GPT';

    public function handle()
    {
        $year    = $this->option('year') ?? now()->year;
        $quarter = $this->option('quarter') ?? now()->quarter;
        $limit   = (int) $this->option('limit');

        $this->info("🌍 Buscando artículos de tendencias {$year}");

        try {

            $articles = $this->searchArticles($year, $limit);

            if (empty($articles)) {
                $this->warn("No se encontraron artículos");
                return Command::SUCCESS;
            }

            $this->info("Artículos encontrados: ".count($articles));

            $trends = $this->extractTrends($articles);

            if (!$trends) {
                $this->warn("GPT no devolvió tendencias");
                return Command::SUCCESS;
            }

            foreach ($trends as $trend) {

                $url = $trend['source']['url'] ?? null;

                if (!$url) {
                    continue;
                }

                if (!$this->validateUrl($url)) {
                    continue;
                }

                $exists = DB::table('macro_trend_raw')
                    ->where('source_url', $url)
                    ->where('year', $year)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('macro_trend_raw')->insert([
                    'trend_name'   => $trend['name'],
                    'description'  => $trend['description'] ?? null,
                    'year'         => $year,
                    'quarter'      => $quarter,
                    'source_name'  => $trend['source']['name'] ?? null,
                    'source_title' => $trend['source']['title'] ?? null,
                    'source_url'   => $url,
                    'source_type'  => $trend['source']['type'] ?? 'article',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            $this->info("✅ Tendencias guardadas");

        } catch (\Throwable $e) {

            $this->error("Error: ".$e->getMessage());

            Log::error('[GLOBAL-TRENDS]', [
                'error' => $e->getMessage()
            ]);
        }

        return Command::SUCCESS;
    }

    /* =====================================================
       BUSCAR ARTÍCULOS CON TAVILY
    ===================================================== */

    protected function searchArticles(int $year, int $limit): array
    {
        $queries = [
            "technology trends {$year}",
            "AI trends {$year}",
            "future of work technology {$year}",
            "cybersecurity trends report {$year}",
            "digital transformation trends {$year}"
        ];

        $results = [];

        foreach ($queries as $query) {

            $response = Http::post(
                'https://api.tavily.com/search',
                [
                    'api_key' => env('TAVILY_KEY'),
                    'query' => $query,
                    'search_depth' => 'basic',
                    'max_results' => $limit
                ]
            );

            $data = $response->json();

            foreach ($data['results'] ?? [] as $item) {

                if (!isset($item['url'])) {
                    continue;
                }

                $results[] = [
                    'title' => $item['title'] ?? '',
                    'url'   => $item['url']
                ];
            }
        }

        return $results;
    }

    /* =====================================================
       EXTRAER TENDENCIAS CON GPT
    ===================================================== */

    protected function extractTrends(array $articles): ?array
    {
        $apiKey = config('services.openai.key');

        $prompt = $this->buildPrompt($articles);

        $response = Http::withToken($apiKey)
            ->timeout(200)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-5',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a global technology intelligence analyst.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);

        $text = data_get($response->json(), 'choices.0.message.content');

        return $this->extractJson($text)['trends'] ?? null;
    }

    /* =====================================================
       VALIDAR URL
    ===================================================== */

    protected function validateUrl(string $url): bool
    {
        try {

            $response = Http::timeout(6)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0'
                ])
                ->get($url);

            return $response->status() >= 200 && $response->status() < 400;

        } catch (\Throwable $e) {
            return false;
        }
    }

    /* =====================================================
       PROMPT GPT
    ===================================================== */

protected function buildPrompt(array $articles): string
{
    $list = collect($articles)
        ->take(60)
        ->map(function ($a, $i) {

            $title = $a['title'] ?? '';
            $url   = $a['url'] ?? '';

            return ($i + 1) . ". {$title}\n{$url}";
        })
        ->implode("\n\n");

    return <<<PROMPT
Eres un analista senior de tendencias tecnológicas con enfoque en transformación digital, empleabilidad y educación.

ARTÍCULOS

A continuación recibirás varios artículos relacionados con:
- tecnología
- inteligencia artificial
- transformación digital
- futuro del trabajo
- cloud
- ciberseguridad
- automatización
- educación digital

Cada artículo contiene:
- título
- enlace

ARTÍCULOS:
{$list}

TAREA

Identifica entre 8 y 12 MACROTENDENCIAS tecnológicas globales basadas EXCLUSIVAMENTE en los artículos proporcionados.

DEFINICIÓN CLAVE

Una macrotendencia NO es una tecnología aislada.

Debe representar un cambio estructural que impacta:
- la forma en que trabajan las empresas
- los perfiles profesionales
- las habilidades demandadas
- la transformación digital empresarial

CRITERIOS OBLIGATORIOS

Cada tendencia debe:
- estar respaldada por al menos 2 artículos
- representar un cambio observable y real
- tener impacto en empleabilidad o roles tecnológicos
- ser estratégica y transversal
- NO ser solamente una herramienta o producto

ENFOQUE ADICIONAL

Prioriza tendencias que representen oportunidades claras para:
- actualización curricular
- nuevas competencias digitales
- formación tecnológica
- nuevas especializaciones
- adaptación educativa al mercado laboral

IMPORTANTE:
- NO inventar tendencias educativas
- El enfoque educativo debe derivarse de la evidencia de mercado

RESTRICCIONES

- NO inventar información
- NO usar conocimiento externo
- NO crear tendencias sin respaldo
- NO repetir tendencias similares
- Evitar nombres genéricos como:
  - "Inteligencia Artificial"
  - "Cloud"
  - "Automatización"

En su lugar usar enfoques específicos como:
- "Automatización inteligente de procesos empresariales"
- "Adopción de IA generativa en flujos corporativos"
- "Convergencia entre cloud e IA empresarial"

SALIDA

Devuelve ÚNICAMENTE un JSON válido con esta estructura EXACTA:

{
  "trends": [
    {
      "trend_name": "Nombre de la tendencia en español",

      "description": "Explicación clara del cambio estructural que está ocurriendo",

      "evidence": [
        {
          "title": "Título original del artículo",

          "url": "URL exacta del artículo",

          "insight": "Qué parte del artículo sustenta la tendencia"
        }
      ],

      "impact_on_employability": "Cómo afecta roles, habilidades o demanda laboral",

      "education_opportunity": "Cómo podría traducirse en actualización curricular o nuevas competencias",

      "maturity_level": "Emergente | En crecimiento | Consolidada"
    }
  ]
}

REGLAS FINALES

- SOLO JSON
- NO markdown
- NO explicaciones
- NO texto adicional
- JSON válido
PROMPT;
}

    protected function extractJson(string $text): array
    {
        if (preg_match('/\{.*\}/s', $text, $m)) {
            return json_decode($m[0], true) ?? [];
        }

        return [];
    }
}
