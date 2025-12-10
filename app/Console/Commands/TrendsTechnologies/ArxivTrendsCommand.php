<?php

namespace App\Console\Commands\TrendsTechnologies;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\GlobalTrend;

class ArxivTrendsCommand extends Command
{
    protected $signature = 'arxiv:trends
        {--categories= : Lista como cs.AI,cs.LG,cs.CL}
        {--days=30 : Rango de días}
        {--year= : Año para registrar}';

    protected $description = 'Mide tendencias ArXiv contando papers recientes por categoría.';

    public function handle()
    {
        $categories = collect(explode(',', $this->option('categories')))
            ->filter()
            ->map(fn($c) => trim($c));

        if ($categories->isEmpty()) {
            $this->error("❌ Debes indicar --categories=cs.AI,cs.LG,cs.CL");
            return Command::FAILURE;
        }

        $days = (int)($this->option('days') ?? 30);
        $year = $this->option('year') ?? now()->year;
        $cutoff = now()->subDays($days);

        foreach ($categories as $cat) {

            // Construir query ArXiv optimizada
            $query = http_build_query([
                'search_query' => "cat:$cat",
                'max_results'  => 2000, // antes 10000 -> timeout fijo
                'sortBy'       => 'submittedDate',
                'sortOrder'    => 'descending',
            ]);

            $url = "https://export.arxiv.org/api/query?$query";

            $response = Http::retry(3, 2000)
                ->timeout(90)     // ⬅ timeout mayor
                ->get($url);

            if ($response->failed()) {
                $this->error("❌ Error ArXiv para {$cat}: HTTP ".$response->status());
                continue;
            }

            $xml = simplexml_load_string($response->body());
            if (!$xml) {
                $this->error("❌ XML inválido para {$cat}");
                continue;
            }

            $count = 0;
            $entries = $xml->entry ?? [];

            foreach ($entries as $entry) {
                $published = isset($entry->published)
                    ? now()->parse((string)$entry->published)
                    : null;

                if ($published && $published->greaterThanOrEqualTo($cutoff)) {
                    $count++;
                }
            }

            GlobalTrend::updateOrCreate(
                [
                    'repo_node_id' => "arxiv_{$cat}",
                    'subcategory'  => $cat,
                    'year'         => $year,
                ],
                [
                    'source'        => 'arxiv',
                    'source_url'    => "https://arxiv.org/list/{$cat}/recent",
                    'source_type'   => 'api',
                    'category'      => 'arxiv_publications',
                    'item_name'     => $cat,
                    'item_type'     => 'trend',
                    'summary'       => "Cantidad de papers en {$days} días en {$cat}.",
                    'quarter'       => now()->quarter,
                    'value'         => $count,
                    'metadata'      => [
                        'days'      => $days,
                        'category'  => $cat,
                        'papers'    => $count,
                        'total_raw' => count($entries),
                    ],
                ]
            );

            $this->info("✔ {$cat}: {$count} papers en {$days} días");
        }

        return Command::SUCCESS;
    }
}
