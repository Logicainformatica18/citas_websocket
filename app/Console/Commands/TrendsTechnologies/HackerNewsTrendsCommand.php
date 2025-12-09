<?php

namespace App\Console\Commands\TrendsTechnologies;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\GlobalTrend;

class HackerNewsTrendsCommand extends Command
{
    protected $signature = 'hackernews:trends
        {--limit=50 : Cantidad de items a consultar}
        {--year= : Año para almacenar en global_trends}';

    protected $description = 'Importa tendencias tecnológicas desde HackerNews (Top Stories) y las guarda en global_trends.';

    public function handle()
    {
        $limit = (int) $this->option('limit') ?: 50;
        $year  = $this->option('year') ?? now()->year;

        $this->info("🔥 Consultando HackerNews TOP STORIES…");

        // 1️⃣ Obtener IDs de las historias top
        $idsResponse = Http::retry(3, 1500)
            ->timeout(20)
            ->get('https://hacker-news.firebaseio.com/v0/topstories.json');

        if ($idsResponse->failed()) {
            $this->error("❌ Error obteniendo topstories: " . $idsResponse->body());
            return Command::FAILURE;
        }

        $topIds = array_slice($idsResponse->json(), 0, $limit);
        $rank   = 1;

        foreach ($topIds as $id) {

            // 2️⃣ Obtener información del item individual
            $itemResponse = Http::retry(3, 1500)
                ->timeout(20)
                ->get("https://hacker-news.firebaseio.com/v0/item/{$id}.json");

            if ($itemResponse->failed()) {
                $this->warn("⚠ No se pudo obtener la historia {$id}");
                continue;
            }

            $item = $itemResponse->json();

            if (!$item || !isset($item['title'])) continue;

            $title = $item['title'];
            $score = $item['score'] ?? 0;

            // 3️⃣ Crear hash único solo por item del año
            $hash = hash('sha256', "{$id}|hackernews|{$year}");

            $existing = GlobalTrend::where('hash', $hash)->first();

            if ($existing) {
                // Actualizar score / rank
                $existing->update([
                    'value'    => $score,
                    'rank'     => $rank,
                    'metadata' => json_encode($item),
                ]);
            } else {
                // Insertar nueva tendencia
                GlobalTrend::create([
                    'source'        => 'hackernews',
                    'source_url'    => $item['url'] ?? null,
                    'source_type'   => 'api',

                    'category'      => 'hackernews_top',
                    'subcategory'   => 'global',

                    'item_name'     => "HN Story {$id}",
                    'item_type'     => 'trend',

                    'summary'       => $title,
                    'year'          => $year,
                    'quarter'       => now()->quarter,

                    'value'         => $score,
                    'rank'          => $rank,

                    'metadata'      => json_encode($item),
                    'hash'          => $hash,
                ]);
            }

            $rank++;
        }

        $this->info("✅ HackerNews trends guardados/actualizados correctamente.");
        return Command::SUCCESS;
    }
}
