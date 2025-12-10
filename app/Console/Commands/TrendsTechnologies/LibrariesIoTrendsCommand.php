<?php

namespace App\Console\Commands\TrendsTechnologies;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\GlobalTrend;

class LibrariesIoTrendsCommand extends Command
{
    protected $signature = 'libraries:trends
        {--packages= : Lista separada por comas (react,vue,angular)}
        {--platform=npm : Plataforma (npm, pypi, rubygems, maven)}
        {--year= : Año}';

    protected $description = 'Extrae métricas de popularidad desde Libraries.io API.';

    public function handle()
    {
        $packages = collect(explode(',', $this->option('packages')))
            ->filter()->map(fn($p) => trim($p));

        if ($packages->isEmpty()) {
            $this->error("❌ Debes indicar --packages=react,vue,angular");
            return Command::FAILURE;
        }

        $platform = $this->option('platform') ?? 'npm';
        $year = $this->option('year') ?? now()->year;

        foreach ($packages as $pkg) {
            $this->info("🔎 Consultando Libraries.io para {$pkg} ({$platform})");

            $url = "https://libraries.io/api/{$platform}/{$pkg}";

            $response = Http::retry(3, 2000)->get($url);

            if (!$response->successful()) {
                $this->error("❌ No se pudieron obtener datos para {$pkg}");
                continue;
            }

            $json = $response->json();

            // MÉTRICAS CUANTITATIVAS
            $dependents = $json['dependents_count'] ?? 0;
            $stars      = $json['stars'] ?? 0;
            $forks      = $json['forks'] ?? 0;

            // SCORE PROPIO para ranking
            $score = $dependents + ($stars * 0.1) + ($forks * 0.05);

            GlobalTrend::updateOrCreate(
                [
                    'repo_node_id' => "libio_{$pkg}",
                    'subcategory'  => $pkg,
                    'year'         => $year,
                ],
                [
                    'source'      => 'libraries.io',
                    'source_url'  => $json['repository_url'] ?? null,
                    'source_type' => 'api',
                    'category'    => 'libraries_popularity',
                    'item_name'   => $pkg,
                    'item_type'   => 'technology',
                    'summary'     => "Popularity metrics for {$pkg} via Libraries.io",
                    'quarter'     => now()->quarter,
                    'value'       => $score,
                    'metadata'    => $json,
                ]
            );

            $this->info("✔ {$pkg}: dependents={$dependents}, stars={$stars}, score={$score}");
        }

        return Command::SUCCESS;
    }
}
