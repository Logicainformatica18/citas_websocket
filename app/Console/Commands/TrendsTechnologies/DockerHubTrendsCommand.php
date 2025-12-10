<?php

namespace App\Console\Commands\TrendsTechnologies;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\GlobalTrend;

class DockerHubTrendsCommand extends Command
{
    protected $signature = 'dockerhub:trends
        {--images= : Lista de imágenes (python,node,redis)}
        {--year= : Año a registrar}';

    protected $description = 'Extrae popularidad de imágenes Docker Hub (pull counts).';

    public function handle()
    {
        $images = collect(explode(',', $this->option('images')))
                    ->filter()
                    ->map(fn($i) => trim($i));

        if ($images->isEmpty()) {
            $this->error("❌ Debes indicar --images=python,node,redis");
            return Command::FAILURE;
        }

        $year = $this->option('year') ?? now()->year;

        foreach ($images as $image) {

            $this->info("🐳 Consultando Docker Hub: {$image}");

            $url = "https://hub.docker.com/v2/repositories/{$image}";

            $response = Http::retry(3, 1500)->get($url);

            if ($response->failed()) {
                $this->error("❌ Error DockerHub para {$image}");
                continue;
            }

            $json = $response->json();

            if (!isset($json['pull_count'])) {
                $this->error("❌ DockerHub no devolvió pull_count para {$image}");
                continue;
            }

            $pulls = $json['pull_count'];
            $stars = $json['star_count'] ?? 0;

            GlobalTrend::updateOrCreate(
                [
                    'subcategory'  => $image,
                    'category'     => 'dockerhub_pulls',
                    'year'         => $year,
                ],
                [
                    'source'        => 'dockerhub',
                    'source_url'    => "https://hub.docker.com/r/{$image}",
                    'source_type'   => 'api',
                    'item_name'     => $image,
                    'item_type'     => 'technology',
                    'summary'       => "Pulls totales en DockerHub para {$image}.",
                    'quarter'       => now()->quarter,
                    'value'         => $pulls,
                    'metadata'      => $json,
                ]
            );

            $this->info("✔ {$image}: {$pulls} pulls, ⭐ {$stars} estrellas");
        }

        return Command::SUCCESS;
    }
}
