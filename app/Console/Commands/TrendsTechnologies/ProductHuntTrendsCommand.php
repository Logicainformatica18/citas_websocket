<?php

namespace App\Console\Commands\TrendsTechnologies;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\GlobalTrend;

class ProductHuntTrendsCommand extends Command
{
    protected $signature = 'producthunt:trends
        {--days=30 : Días hacia atrás para analizar}
        {--year= : Año a registrar}';

    protected $description = 'Importa tendencias tecnológicas desde Product Hunt (productos tech más votados).';

    public function handle()
    {
        $days = (int) $this->option('days') ?: 30;
        $year = $this->option('year') ?? now()->year;

        $apiToken = env('PRODUCTHUNT_API_TOKEN');

        if (!$apiToken) {
            $this->error("❌ PRODUCTHUNT_API_TOKEN faltante en .env");
            return Command::FAILURE;
        }

        $this->info("🔥 Product Hunt Trends — Últimos {$days} días");

        $allProducts = [];
        $topicsCount = [];

        /* ============================================================
         * 1) Consultar Product Hunt día por día
         * ============================================================*/
        for ($i = 0; $i < $days; $i++) {

            $date = now()->subDays($i)->format('Y-m-d');
            $this->info("📦 Día: {$date}");

            $query = <<<'GRAPHQL'
            query GetPosts($postedAfter: DateTime!, $postedBefore: DateTime!) {
                posts(postedAfter: $postedAfter, postedBefore: $postedBefore, first: 50, order: VOTES) {
                    edges {
                        node {
                            id
                            name
                            tagline
                            description
                            votesCount
                            commentsCount
                            url
                            website
                            topics {
                                edges {
                                    node {
                                        name
                                    }
                                }
                            }
                        }
                    }
                }
            }
            GRAPHQL;

            $response = Http::retry(3, 2000)
                ->withHeaders([
                    'Authorization' => "Bearer {$apiToken}",
                    'Content-Type'  => 'application/json',
                ])
                ->timeout(40)
                ->post('https://api.producthunt.com/v2/api/graphql', [
                    'query' => $query,
                    'variables' => [
                        'postedAfter'  => $date . 'T00:00:00Z',
                        'postedBefore' => $date . 'T23:59:59Z',
                    ]
                ]);

            if ($response->failed()) {
                $this->warn("⚠️ Error {$date}: " . $response->status());
                continue;
            }

            $posts = $response->json()['data']['posts']['edges'] ?? [];
            $this->info("   → Encontrados: " . count($posts));

            foreach ($posts as $edge) {
                $p = $edge['node'];

                $topics = array_map(fn($t) => $t['node']['name'], $p['topics']['edges']);

                // Guardar producto en array único por ID
                $allProducts[$p['id']] = [
                    'name'        => $p['name'],
                    'tagline'     => $p['tagline'],
                    'description' => $p['description'],
                    'votes'       => $p['votesCount'],
                    'comments'    => $p['commentsCount'],
                    'url'         => $p['url'],
                    'website'     => $p['website'],
                    'topics'      => $topics,
                    'date'        => $date,
                ];

                // Contar topics
                foreach ($topics as $t) {
                    if ($t) {
                        $topicsCount[$t] = ($topicsCount[$t] ?? 0) + 1;
                    }
                }
            }

            usleep(300000); // evitar rate limit
        }

        if (empty($allProducts)) {
            $this->warn("⚠️ No se encontró información");
            return Command::SUCCESS;
        }

        $this->info("📊 Total productos: " . count($allProducts));
        $this->info("📌 Topics detectados: " . count($topicsCount));

        arsort($topicsCount);

        /* ============================================================
         * 2) Guardar TOPICS (popularidad de tecnologías)
         * ============================================================*/
        $rank = 1;
        foreach ($topicsCount as $topic => $count) {

            $hash = hash('sha256', "{$topic}|producthunt_topics|{$year}");

            GlobalTrend::updateOrCreate(
                ['hash' => $hash],
                [
                    'source'       => 'producthunt',
                    'source_url'   => "https://www.producthunt.com/topics/" . urlencode($topic),
                    'source_type'  => 'api',
                    'category'     => 'producthunt_topics',
                    'subcategory'  => 'tech_products',
                    'item_name'    => $topic,
                    'item_type'    => 'topic',
                    'summary'      => "Topic presente en {$count} productos.",
                    'year'         => $year,
                    'quarter'      => now()->quarter,
                    'value'        => $count,
                    'rank'         => $rank,
                    'metadata'     => [
                        'mentions' => $count,
                        'total_products' => count($allProducts),
                        'days_analyzed' => $days,
                    ],
                ]
            );

            $rank++;
        }

        /* ============================================================
         * 3) Guardar TOP productos por votos
         * ============================================================*/
        $topProducts = collect($allProducts)
            ->sortByDesc('votes')
            ->take(50);

        $rank = 1;
        foreach ($topProducts as $product) {

            $hash = hash('sha256', "{$product['name']}|producthunt_products|{$year}");

            GlobalTrend::updateOrCreate(
                ['hash' => $hash],
                [
                    'source'       => 'producthunt',
                    'source_url'   => $product['url'],
                    'source_type'  => 'api',
                    'category'     => 'producthunt_products',
                    'subcategory'  => 'trending',
                    'item_name'    => $product['name'],
                    'item_type'    => 'product',
                    'summary'      => $product['tagline'],
                    'year'         => $year,
                    'quarter'      => now()->quarter,
                    'value'        => $product['votes'],
                    'rank'         => $rank,
                    'metadata'     => [
                        'votes'     => $product['votes'],
                        'comments'  => $product['comments'],
                        'topics'    => $product['topics'],
                        'website'   => $product['website'],
                        'launch_date' => $product['date'],
                    ],
                ]
            );

            $rank++;
        }

        $this->info("✅ Product Hunt Trends importados correctamente.");

        return Command::SUCCESS;
    }
}
