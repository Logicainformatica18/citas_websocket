<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Methodology;
use App\Models\JobOffer;
use App\Models\MethodologyMetric;
use Carbon\Carbon;

class GetOnBoardByMethodologiesCommand extends Command
{
    protected $signature = 'getonboard:methodologies {--pages=1}';
    protected $description = '📊 Recorre todas las metodologías y guarda métricas de empleos desde GetOnBoard.';

    public function handle()
    {
        $pages = (int) $this->option('pages');
        $methodologies = Methodology::pluck('name', 'id');

        $this->info("🔎 Iniciando scraping de GetOnBoard para {$methodologies->count()} metodologías...");

        foreach ($methodologies as $methodologyId => $methodologyName) {
            $this->warn("\n💡 Procesando metodología: {$methodologyName}");

            $totalFound = 0;
            $totalNew = 0;
            $countries = [];
            $modalities = [];

            for ($page = 1; $page <= $pages; $page++) {
                $url = "https://www.getonbrd.com/api/v0/search/jobs?query=" . urlencode($methodologyName) . "&page={$page}&per_page=100";

                try {
                    $response = Http::timeout(20)->get($url);
                    if ($response->failed()) continue;

                    $data = $response->json('data') ?? [];
                    $totalFound += count($data);

                    foreach ($data as $job) {
                        $attr = $job['attributes'] ?? [];

                        $title = $attr['title'] ?? 'N/A';
                        $company = $attr['company']['data']['attributes']['name'] ?? null;
                        $country = $attr['countries'][0] ?? 'Remote';
                        $modality = $attr['remote_modality'] ?? 'unknown';
                        $urlJob = $job['links']['public_url'] ?? null;

                        $countries[$country] = ($countries[$country] ?? 0) + 1;
                        $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;

                        // Evita duplicados
                        if (JobOffer::where('url', $urlJob)->exists()) continue;

                        JobOffer::create([
                            'title' => $title,
                            'company' => $company,
                            'country' => $country,
                            'city' => $attr['city'] ?? null,
                            'modality' => $modality,
                            'source' => 'GetOnBoard',
                            'search_query' => $methodologyName,
                            'external_id' => $job['id'] ?? null,
                            'url' => $urlJob,
                            'published_at' => isset($attr['published_at'])
                                ? Carbon::createFromTimestamp($attr['published_at'])
                                : now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $totalNew++;
                    }

                    sleep(1.5);

                } catch (\Throwable $th) {
                    Log::error("⚠️ Error en {$methodologyName} (página {$page}): " . $th->getMessage());
                }
            }

            MethodologyMetric::create([
                'methodology_id' => $methodologyId,
                'methodology_name' => $methodologyName,
                'jobs_found_count' => $totalFound,
                'jobs_new_count' => $totalNew,
                'countries_breakdown' => $countries,
                'modality_breakdown' => $modalities,
                'run_date' => now(),
                'source' => 'GetOnBoard',
            ]);

            $this->info("✅ {$methodologyName}: {$totalNew} nuevas / {$totalFound} encontradas");
        }

        $this->info("\n🎯 Proceso completado. Métricas guardadas en methodology_metrics.");
    }
}
