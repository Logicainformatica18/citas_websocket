<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Methodology;
use App\Models\JobOffer;
use App\Models\MethodologyMetric;
use Carbon\Carbon;

class ArbeitnowByMethodologiesCommand extends Command
{
    protected $signature = 'arbeitnow:methodologies';
    protected $description = '🌍 Recorre todas las metodologías y registra métricas de demanda laboral desde Arbeitnow (Europa/Asia).';

    public function handle()
    {
        $methodologies = Methodology::pluck('name', 'id');
        $this->info("🌐 Iniciando scraping de Arbeitnow por metodología ({$methodologies->count()} metodologías)...");

        foreach ($methodologies as $methodologyId => $methodologyName) {
            $this->warn("\n💡 Procesando metodología: {$methodologyName}");

            $totalFound = 0;
            $totalNew = 0;
            $countries = [];
            $modalities = [];

            try {
                $response = Http::timeout(25)->get('https://www.arbeitnow.com/api/job-board-api', [
                    'search' => $methodologyName,
                ]);

                if ($response->failed()) {
                    $this->error("❌ Falló la API para {$methodologyName}");
                    continue;
                }

                $jobs = $response->json()['data'] ?? $response->json() ?? [];
                $totalFound = count($jobs);

                foreach ($jobs as $job) {
                    $title = $job['title'] ?? 'N/A';
                    $company = $job['company_name'] ?? null;
                    $location = $job['location'] ?? '';
                    $isRemote = $job['remote'] ?? false;
                    $urlJob = $job['url'] ?? null;
                    $externalId = $job['slug'] ?? md5($urlJob ?? uniqid('arbeitnow_'));

                    // 🌍 Detectar país simplificado
                    $country = $this->extractCountry($location);
                    $countries[$country] = ($countries[$country] ?? 0) + 1;

                    // 🧠 Modalidad
                    $modality = $this->extractModality($location, $isRemote);
                    $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;

                    // 🔁 Evitar duplicados avanzados
                    $exists = JobOffer::where('source', 'Arbeitnow')
                        ->where(function ($q) use ($externalId, $title, $company, $country, $methodologyName, $urlJob) {
                            $q->where('external_id', $externalId)
                              ->orWhere(function ($q2) use ($title, $company, $country, $methodologyName, $urlJob) {
                                  $q2->where('title', $title)
                                     ->where('company', $company)
                                     ->where('country', $country)
                                     ->where('search_query', $methodologyName)
                                     ->where(function ($q3) use ($urlJob) {
                                         $q3->where('url', $urlJob)
                                            ->orWhere('url', 'like', '%' . substr($urlJob, -25) . '%');
                                     });
                              });
                        })
                        ->exists();

                    if ($exists) continue;

                    // 💾 Crear oferta
                    JobOffer::create([
                        'title'        => $title,
                        'company'      => $company,
                        'country'      => $country,
                        'city'         => null,
                        'latitude'     => null,
                        'longitude'    => null,
                        'modality'     => $modality,
                        'source'       => 'Arbeitnow',
                        'external_id'  => $externalId,
                        'url'          => $urlJob,
                        'currency'     => 'EUR',
                        'salary_min'   => null,
                        'salary_max'   => null,
                        'search_query' => $methodologyName,
                        'published_at' => isset($job['date']) ? Carbon::parse($job['date']) : now(),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);

                    $totalNew++;
                }

                // 📊 Guardar métrica igual que GetOnBoard
                MethodologyMetric::create([
                    'methodology_id' => $methodologyId,
                    'methodology_name' => $methodologyName,
                    'jobs_found_count' => $totalFound,
                    'jobs_new_count' => $totalNew,
                    'countries_breakdown' => $countries,
                    'modality_breakdown' => $modalities,
                    'run_date' => now(),
                    'source' => 'Arbeitnow',
                ]);

                $this->info("✅ {$methodologyName}: {$totalNew} nuevas | 🌍 {$totalFound} encontradas");
            } catch (\Throwable $th) {
                Log::error("⚠️ Error en {$methodologyName}: " . $th->getMessage());
            }

            sleep(1.5);
        }

        $this->info("\n🎯 Proceso completado: métricas registradas en `methodology_metrics`.");
    }

    protected function extractCountry(string $location): string
    {
        $loc = strtolower($location);
        return match (true) {
            str_contains($loc, 'germany') => 'Germany',
            str_contains($loc, 'spain') => 'Spain',
            str_contains($loc, 'france') => 'France',
            str_contains($loc, 'portugal') => 'Portugal',
            str_contains($loc, 'austria') => 'Austria',
            str_contains($loc, 'netherlands') => 'Netherlands',
            str_contains($loc, 'poland') => 'Poland',
            str_contains($loc, 'remote') => 'Remote',
            default => 'Unknown',
        };
    }

    protected function extractModality(string $location, bool $isRemote): string
    {
        $loc = strtolower($location);
        return match (true) {
            $isRemote, str_contains($loc, 'remote') => 'fully_remote',
            str_contains($loc, 'hybrid'), str_contains($loc, 'híbrido') => 'hybrid',
            default => 'no_remote'
        };
    }
}
