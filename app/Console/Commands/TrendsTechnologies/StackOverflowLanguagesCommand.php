<?php

namespace App\Console\Commands\TrendsTechnologies;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\GlobalTrend;

class StackOverflowLanguagesCommand extends Command
{
    protected $signature = 'stackoverflow:languages {--pages=1}';
    protected $description = 'Importa tendencias de lenguajes desde StackOverflow Tags API.';

    public function handle()
    {
        $pages = (int) $this->option('pages') ?? 1;

        $this->info("📡 Consultando StackOverflow Tags API...");

        $year = now()->year;
        $rank = 1;

        for ($page = 1; $page <= $pages; $page++) {

            $url = "https://api.stackexchange.com/2.3/tags?page={$page}&pagesize=100&order=desc&sort=popular&site=stackoverflow";

            $response = Http::withOptions(['http_errors' => false])->get($url);

            if ($response->failed()) {
                $this->error("❌ Error consultando StackOverflow (HTTP {$response->status()})");
                return Command::FAILURE;
            }

            $data = $response->json();

            if (!isset($data['items'])) {
                $this->error("❌ Estructura inesperada en API StackOverflow.");
                return Command::FAILURE;
            }

            foreach ($data['items'] as $item) {

                $name = $item['name'] ?? null;
                if (!$name) continue;

                $count = $item['count'] ?? null;

                // 🔍 Filtrar solo lenguajes reales
                if (!self::isLanguage($name)) continue;

                GlobalTrend::updateOrCreate(
                    [
                        'repo_node_id' => "so_lang_{$name}_{$year}",
                        'subcategory'  => 'programming_languages',
                        'year'         => $year,
                    ],
                    [
                        'source'      => 'stackoverflow',
                        'source_url'  => $url,
                        'source_type' => 'api',
                        'category'    => 'stackoverflow_languages',
                        'item_name'   => $name,
                        'item_type'   => 'technology',
                        'summary'     => "Popularidad de lenguaje {$name} en StackOverflow {$year}",
                        'quarter'     => now()->quarter,
                        'value'       => $count,
                        'rank'        => $rank,
                        'metadata'    => [
                            'count' => $count
                        ]
                    ]
                );

                $rank++;
            }
        }

        $this->info("✅ StackOverflow importado con éxito ({$rank}-1 lenguajes).");
        return Command::SUCCESS;
    }

    /**
     * Determina si un tag es un lenguaje de programación conocido.
     */
    private static function isLanguage(string $tag): bool
    {
        $languages = [
            'python', 'java', 'javascript', 'c#', 'php', 'c++', 'typescript', 'swift',
            'go', 'rust', 'kotlin', 'ruby', 'scala', 'perl', 'lua', 'dart',
            'haskell', 'matlab', 'r', 'bash', 'powershell', 'assembly'
        ];

        return in_array(strtolower($tag), $languages);
    }
}
