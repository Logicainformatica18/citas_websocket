<?php

namespace App\Console\Commands\Trends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GenerateMacroTrendsFromAI extends Command
{
    protected $signature = 'trends:generate-macro-ai';
    protected $description = 'Agrupa micro tendencias usando IA y crea macro_trends';

    public function handle()
    {
        $year = now()->year;
        $quarter = now()->quarter;

        $this->info("📅 Generando macro tendencias para {$year} Q{$quarter}");



        $microTrends = $this->getMicroTrends($year, $quarter);

        if (empty($microTrends)) {
            $this->warn("⚠ No hay micro tendencias para este periodo.");
            return;
        }

        $prompt = $this->buildPrompt($microTrends);

        $rawContent = $this->callAI($prompt);

        if (!$rawContent) {
            $this->error("❌ No hubo respuesta válida de IA.");
            return;
        }

        $macroGroups = $this->parseResponse($rawContent);

        if (!$macroGroups) {
            return;
        }

        $this->storeMacroTrends($macroGroups, $year, $quarter);

        $this->info("🎯 Macro tendencias generadas correctamente.");
    }

    /* =======================================================
       Verificar si ya existen macros para el periodo
    ======================================================= */
    private function macrosAlreadyGenerated($year, $quarter): bool
    {
        return DB::table('macro_trends')
            ->where('year', $year)
            ->where('quarter', $quarter)
            ->exists();
    }

    /* =======================================================
       Obtener micro tendencias
    ======================================================= */
   private function getMicroTrends($year, $quarter): array
{
    return DB::table('entity_trends')
        ->where('year', $year)
        ->where('quarter', $quarter)
        ->where('macro_processed', 0) // 🔥 SOLO nuevas
        ->orderByDesc('created_at')   // 🔥 más recientes primero
        ->take(120)
        ->pluck('trend_name')
        ->toArray();
}


    /* =======================================================
       Construir Prompt
    ======================================================= */
    private function buildPrompt(array $microTrends): string
    {
        $list = implode("\n- ", $microTrends);

        return "
        Responde exclusivamente en español.
Eres analista estratégico del Observatorio Tecnológico.

A continuación tienes una lista de micro tendencias detectadas automáticamente:

- {$list}

Tu tarea:

1. Agruparlas por similitud semántica.
2. Crear máximo 6 macro tendencias estratégicas.
3. Cada macro tendencia debe:
   - Tener máximo 5 palabras.
   - Ser concreta y específica.
   - No ser genérica (evitar: 'Emerging Technologies', 'Innovation', 'Technology Trends').
   - Representar una fuerza real del mercado.
4. NO inventes tendencias nuevas.
5. Solo agrupa las micro tendencias existentes.
6. Cada micro tendencia debe aparecer en una sola macro.
7. Incluye una breve descripción estratégica (máximo 2 líneas).

Devuelve únicamente JSON válido con esta estructura:

[
  {
    \"macro_name\": \"...\",
    \"description\": \"...\",
    \"includes\": [\"micro exacta 1\", \"micro exacta 2\"]
  }
]

No incluyas texto fuera del JSON.
";
    }
private function clearCurrentPeriod($year, $quarter): void
{
    $macroIds = DB::table('macro_trends')
        ->where('year', $year)
        ->where('quarter', $quarter)
        ->pluck('id');

    if ($macroIds->isEmpty()) {
        return;
    }

    DB::table('macro_trend_entity_trend')
        ->whereIn('macro_trend_id', $macroIds)
        ->delete();

    DB::table('macro_trends')
        ->where('year', $year)
        ->where('quarter', $quarter)
        ->delete();

    $this->info("🧹 Macros anteriores del periodo eliminadas.");
}

    /* =======================================================
       Llamar a OpenAI
    ======================================================= */
    private function callAI(string $prompt): ?string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
        ])
        ->timeout(120)
        ->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'temperature' => 0.2,
            'messages' => [
               ['role' => 'system', 'content' => 'Eres un analista estratégico del Observatorio Tecnológico. Respondes exclusivamente en español.'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        return $response['choices'][0]['message']['content'] ?? null;
    }

    /* =======================================================
       Parsear respuesta IA
    ======================================================= */
    private function parseResponse(string $content): ?array
    {
        $content = trim($content);

        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```json|^```|```$/m', '', $content);
        }

        $content = trim($content);

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("❌ JSON inválido.");
            $this->line($content);
            return null;
        }

        return $decoded;
    }

    /* =======================================================
       Guardar macro tendencias
    ======================================================= */
    private function storeMacroTrends(array $macroGroups, $year, $quarter): void
    {
        foreach ($macroGroups as $group) {

            if (!isset($group['macro_name'], $group['includes'])) {
                continue;
            }

            $macroName = trim($group['macro_name']);
            $slug = Str::slug($macroName);

            DB::table('macro_trends')->updateOrInsert(
                [
                    'slug' => $slug,
                    'year' => $year,
                    'quarter' => $quarter,
                ],
                [
                    'name' => $macroName,
                    'description' => $group['description'] ?? null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $macroId = DB::table('macro_trends')
                ->where('slug', $slug)
                ->where('year', $year)
                ->where('quarter', $quarter)
                ->value('id');

            foreach ($group['includes'] as $microName) {

                $entityTrendId = DB::table('entity_trends')
                    ->where('trend_name', $microName)
                    ->where('year', $year)
                    ->where('quarter', $quarter)
                    ->value('id');

               if ($entityTrendId) {

    DB::table('macro_trend_entity_trend')->insertOrIgnore([
        'macro_trend_id' => $macroId,
        'entity_trend_id' => $entityTrendId,
    ]);

    // 🔥 MARCAR COMO PROCESADA
    DB::table('entity_trends')
        ->where('id', $entityTrendId)
        ->update(['macro_processed' => 1]);
}

            }

            $this->info("✅ Macro creada: {$macroName}");
        }
    }
}
