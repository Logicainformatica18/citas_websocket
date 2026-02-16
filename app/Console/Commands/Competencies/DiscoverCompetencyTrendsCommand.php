<?php

namespace App\Console\Commands\Competencies;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscoverCompetencyTrendsCommand extends Command
{
    protected $signature = 'trends:discover-competencies
                            {--limit=20}
                            {--sleep=8}';

    protected $description = 'Descubre tendencias directas para competencias (sin market_entities)';

    public function handle()
    {
        $year = now()->year;
        $quarter = now()->quarter;

        $limit = (int) $this->option('limit');
        $sleep = (int) $this->option('sleep');

        $this->info("🔍 Analizando competencias – Y{$year} Q{$quarter}");

        $competencies = DB::table('competencies')
            ->whereNotNull('description_en')
            ->limit($limit)
            ->get();

        foreach ($competencies as $competency) {

            try {

                $prompt = $this->buildPrompt(
                    $competency->name,
                    $competency->description_en
                );

                $response = $this->gptSearch($prompt);

                if (is_string($response)) {
                    $response = $this->extractJson($response);
                }

                if (!isset($response['resultado'])) {
                    throw new \Exception('Respuesta GPT inválida');
                }

                $data = $response['resultado'];

                $confidence = (float) ($data['confidence'] ?? 0);
                $recommendation = $data['recomendacion'] ?? 'Revisión académica recomendada.';
                $trends = $data['trends'] ?? [];

                $trendsCount = count($trends);

                // SEMÁFORO
                if ($trendsCount === 0 || $confidence < 0.40) {
                    $status = 'gap';
                } elseif ($confidence < 0.75 || $trendsCount === 1) {
                    $status = 'partial';
                } else {
                    $status = 'aligned';
                }

                // Guardar tendencias DIRECTAS
                foreach ($trends as $trend) {

                    if (empty($trend['name']) || empty($trend['score'])) {
                        continue;
                    }

                    $url = trim($trend['source']['url'] ?? '');

                    DB::table('competency_trends')->updateOrInsert(
                        [
                            'competency_id' => $competency->id,
                            'source_url' => $url
                        ],
                        [
                            'trend_name' => trim($trend['name']),
                            'trend_score' => (float) $trend['score'],
                            'source_title' => $trend['source']['title'] ?? null,
                            'source_type' => $trend['source']['type'] ?? null,
                            'confidence_score' => $confidence,
                            'year' => $year,
                            'quarter' => $quarter,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }

                // Actualizar competencia
                DB::table('competencies')
                    ->where('id', $competency->id)
                    ->update([
                        'alignment_status' => $status,
                        'alignment_confidence' => $confidence,
                        'alignment_checked_at' => now(),
                        'alignment_source' => 'ai',
                        'alignment_recommendation' => $recommendation,
                        'updated_at' => now(),
                    ]);

                $this->info("✔ {$competency->name} → {$status}");

                sleep($sleep);

            } catch (\Throwable $e) {

                $this->error("❌ Error en {$competency->name}");
                Log::error('[COMPETENCY-TRENDS]', [
                    'competency_id' => $competency->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info('🏁 Proceso finalizado');
        return Command::SUCCESS;
    }

    protected function buildPrompt(string $name, string $description): string
    {
        $currentYear = now()->year;
        $previousYear = $currentYear - 1;

        return <<<PROMPT
Eres un analista estratégico del mercado laboral global.

Analiza la competencia académica:

Nombre: "{$name}"
Descripción: "{$description}"

Evalúa si tiene respaldo en tendencias laborales publicadas en {$previousYear} o {$currentYear}.

Devuelve JSON estricto:

{
  "resultado": {
    "confidence": 0-1,
    "recomendacion": "Texto obligatorio",
    "trends": [
      {
        "name": "Nombre tendencia",
        "score": 0-100,
        "source": {
          "title": "Título",
          "url": "https://...",
          "type": "report | article | study"
        }
      }
    ]
  }
}
PROMPT;
    }

    protected function gptSearch(string $prompt)
    {
        $response = Http::withToken(config('services.openai.key'))
            ->timeout(90)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4.1-mini',
                'temperature' => 0.2,
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres un analista experto en mercado laboral.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (!$response->successful()) {
            throw new \Exception($response->body());
        }

        return data_get($response->json(), 'choices.0.message.content');
    }

    protected function extractJson(string $text): array
    {
        if (preg_match('/\{.*\}/s', $text, $m)) {
            return json_decode($m[0], true) ?? [];
        }
        return [];
    }
}
