<?php

namespace App\Console\Commands\TrendsTechnologies;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\GlobalTrend;

class GdeltTrendsCommand extends Command
{
    protected $signature = 'gdelt:trends {query} {--limit=50}';
    protected $description = 'Importa tendencias tecnológicas desde GDELT basado en menciones en noticias.';

    public function handle()
    {
        $query = urlencode($this->argument('query'));
        $limit = (int) $this->option('limit');

        $url = "https://api.gdeltproject.org/api/v2/doc/doc?query={$query}&format=json";

        $this->info("📡 Consultando GDELT para: {$query}");

        $response = Http::withOptions(['http_errors' => false])->get($url);

        if ($response->failed()) {
            $this->error("❌ Error al consultar la API de GDELT (HTTP {$response->status()})");
            return Command::FAILURE;
        }

        $data = $response->json();

        if (!isset($data['articles']) || !is_array($data['articles'])) {
            $this->error("❌ Respuesta inesperada de GDELT.");
            return Command::FAILURE;
        }

        $articles = array_slice($data['articles'], 0, $limit);

        $rank = 1;
        $year = now()->year;

        foreach ($articles as $article) {

            $title = $article['title'] ?? null;
            $url_article = $article['url'] ?? null;

            if (!$title || !$url_article) continue;

            // Crear un ID único para evitar duplicados
            $nodeId = "gdelt_" . md5($url_article);

            GlobalTrend::updateOrCreate(
                [
                    'repo_node_id' => $nodeId,
                    'subcategory'  => 'tech_mentions',
                    'year'         => $year,
                ],
                [
                    'source'      => 'gdelt',
                    'source_url'  => $url,
                'source_type' => 'api',

                    'category'    => 'gdelt_trends',
                    'item_name'   => $this->argument('query'),
                    'item_type'   => 'technology',
                    'summary'     => $title,
                    'quarter'     => now()->quarter,
                    'value'       => $rank, 
                    'rank'        => $rank,
                    'metadata'    => [
                        'article_title' => $title,
                        'article_url'   => $url_article,
                        'source_country'=> $article['sourcecountry'] ?? null,
                        'date'          => $article['seendate'] ?? null
                    ]
                ]
            );

            $rank++;
        }

        $this->info("✅ GDELT importado con éxito ({$rank}-1 artículos procesados).");

        return Command::SUCCESS;
    }
}
