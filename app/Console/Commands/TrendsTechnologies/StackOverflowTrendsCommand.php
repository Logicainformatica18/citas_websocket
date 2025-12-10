<?php

namespace App\Console\Commands\TrendsTechnologies;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\GlobalTrend;

class StackOverflowTrendsCommand extends Command
{
    protected $signature = 'stackoverflow:trends
        {--pages=3 : Número de páginas a consultar}
        {--year= : Año a registrar en global_trends}';

    protected $description = 'Importa tendencias tecnológicas desde StackOverflow (tags más usados) y las guarda en global_trends.';

    public function handle()
    {
        $pages = (int) $this->option('pages') ?: 3;
        $year  = $this->option('year') ?? now()->year;

        $this->info("🔥 Consultando StackOverflow Trends…");

        $allTags = [];

        for ($page = 1; $page <= $pages; $page++) {

            $url = "https://api.stackexchange.com/2.3/tags?page={$page}&pagesize=100&order=desc&sort=popular&site=stackoverflow";

            $response = Http::retry(3, 1500)
                ->withHeaders([
                    'User-Agent' => 'Observatorio-ISIL',
                ])
                ->timeout(40)
                ->get($url);

            if ($response->failed()) {
                $this->error("❌ Error consultando StackOverflow: " . $response->body());
                return Command::FAILURE;
            }

            $items = $response->json()['items'] ?? [];

            foreach ($items as $tag) {
                $name  = $tag['name'];
                $count = $tag['count'];

                if (!isset($allTags[$name])) {
                    $allTags[$name] = 0;
                }

                $allTags[$name] += $count;
            }
        }

        arsort($allTags); // ordenar por popularidad total

        $rank = 1;

        foreach ($allTags as $tag => $count) {

            $hash = hash('sha256', "{$tag}|stackoverflow_tags|{$year}");

            $existing = GlobalTrend::where('hash', $hash)->first();

            if ($existing) {
                $existing->update([
                    'value' => $count,
                    'rank'  => $rank,
                ]);
            } else {
                GlobalTrend::create([
                    'source'        => 'stackoverflow',
                    'source_url'    => "https://stackoverflow.com/tags/{$tag}",
                    'source_type'   => 'api',
                    'category'      => 'stackoverflow_tags',
                    'subcategory'   => 'global',
                    'item_name'     => $tag,
                    'item_type'     => 'technology',
                    'summary'       => "Tag popular en StackOverflow.",
                    'year'          => $year,
                    'quarter'       => now()->quarter,
                    'value'         => $count,
                    'rank'          => $rank,
                    'metadata'      => json_encode([
                        'count' => $count,
                        'tag'   => $tag,
                    ]),
                    'hash'          => $hash,
                ]);
            }

            $rank++;
        }

        $this->info("✅ StackOverflow trends importados correctamente.");
        return Command::SUCCESS;
    }
}
