<?php

namespace App\Console\Commands\TrendsTechnologies;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\GlobalTrend;

class RedditTrendsCommand extends Command
{
    protected $signature = 'reddit:trends
        {--keywords= : Lista separada por comas (python,react,ai)}
        {--subreddit=programming : Subreddit a consultar}
        {--year= : Año (default actual)}';

    protected $description = 'Extrae tendencias desde Reddit usando PullPush API (comentarios + puntaje).';

    public function handle()
    {
        $keywords = collect(explode(',', $this->option('keywords')))
                        ->filter()
                        ->map(fn($k) => trim($k));

        if ($keywords->isEmpty()) {
            $this->error("❌ Debes indicar --keywords=python,react,ai");
            return;
        }

        $subreddit = $this->option('subreddit') ?? 'programming';
        $year      = $this->option('year') ?? now()->year;

        foreach ($keywords as $keyword) {

            $this->info("🔎 Buscando {$keyword} en r/{$subreddit}...");

            $url = "https://api.pullpush.io/reddit/search/comment/?q={$keyword}&subreddit={$subreddit}&size=100";

            $response = Http::timeout(20)->get($url);

            if ($response->failed()) {
                $this->error("❌ Error Reddit API para {$keyword}");
                continue;
            }

            $json = $response->json();
            $comments = $json['data'] ?? [];

            if (empty($comments)) {
                $this->error("⚠️ Sin resultados para {$keyword}");
                continue;
            }

            // Valor: suma de scores (upvotes)
            $totalScore = collect($comments)->sum('score');

            // Cantidad de menciones
            $mentions = count($comments);

            GlobalTrend::updateOrCreate(
                [
                    'subcategory'  => $keyword,
                    'category'     => 'reddit_mentions',
                    'year'         => $year,
                ],
                [
                    'source'        => 'reddit',
                    'source_url'    => "https://reddit.com/r/{$subreddit}",
                    'source_type'   => 'api',
                    'item_name'     => $keyword,
                    'item_type'     => 'trend',
                    'summary'       => "Menciones y puntaje total para {$keyword} en r/{$subreddit}.",
                    'quarter'       => now()->quarter,
                    'value'         => $totalScore,
                    'metadata'      => [
                        'mentions' => $mentions,
                        'total_score' => $totalScore,
                        'subreddit' => $subreddit
                    ],
                ]
            );

            $this->info("✔ {$keyword}: score={$totalScore}, menciones={$mentions}");
        }

        $this->info("🎉 Reddit Trends finalizado.");
        return Command::SUCCESS;
    }
}
