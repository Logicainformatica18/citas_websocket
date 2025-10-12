<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Language;
use App\Models\JobOffer;
use App\Models\LanguageMetric;
use Carbon\Carbon;

class ArbeitnowByLanguagesCommand extends Command
{
    protected $signature = 'arbeitnow:languages';
    protected $description = '🌍 Recorre todos los lenguajes y registra métricas de demanda laboral desde Arbeitnow (Europa/Asia).';

    public function handle()
    {
        $languages = Language::pluck('name', 'id');
        $this->info("🌐 Iniciando scraping de Arbeitnow por lenguaje ({$languages->count()} lenguajes)...");

        foreach ($languages as $languageId => $languageName) {
            $this->warn("\n💡 Procesando lenguaje: {$languageName}");

            $totalFound = 0;
            $totalNew = 0;
            $countries = [];
            $modalities = [];

            try {
                $response = Http::timeout(20)->get('https://www.arbeitnow.com/api/job-board-api', [
                    'search' => $languageName,
                ]);

                if ($response->failed()) {
                    $this->error("❌ Falló la API para {$languageName}");
                    continue;
                }

                $jobs = $response->json()['data'] ?? [];
                $totalFound = count($jobs);

                foreach ($jobs as $job) {
                    $title = $job['title'] ?? 'N/A';
                    $company = $job['company_name'] ?? null;
                    $location = $job['location'] ?? '';
                    $isRemote = $job['remote'] ?? false;
                    $urlJob = $job['url'] ?? null;

                    // 🌍 País (simplificado)
                    $country = $this->extractCountry($location);
                    $countries[$country] = ($countries[$country] ?? 0) + 1;

                    // 🧠 Modalidad
                    $modality = $this->extractModality($location, $isRemote);
                    $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;

                  // 🔁 Evita duplicados avanzados (por slug, similitud de URL y atributos principales)
$exists = JobOffer::where('source', 'Arbeitnow')
    ->where(function ($q) use ($job, $title, $company, $country, $languageName, $urlJob) {
        $externalId = $job['slug'] ?? md5($urlJob ?? uniqid('arbeitnow_'));

        $q->where('external_id', $externalId)
          ->orWhere(function ($q2) use ($title, $company, $country, $languageName, $urlJob) {
              $q2->where('title', $title)
                 ->where('company', $company)
                 ->where('country', $country)
                 ->where('search_query', $languageName)
                 ->where(function ($q3) use ($urlJob) {
                     $q3->where('url', $urlJob)
                        ->orWhere('url', 'like', '%' . substr($urlJob, -25) . '%');
                 });
          });
    })
    ->exists();

if ($exists) continue;


                    // 💾 Insertar oferta base
                    JobOffer::create([
                        'title'        => $title,
                        'company'      => $company,
                        'country'      => $country,
                        'city'         => null,
                        'latitude'     => null,
                        'longitude'    => null,
                        'modality'     => $modality,
                        'source'       => 'Arbeitnow',
                        'external_id'  => $job['slug'] ?? null,
                        'url'          => $urlJob,
                        'currency'     => 'EUR',
                        'salary_min'   => null,
                        'salary_max'   => null,
                        'search_query' => $languageName,
                        'published_at' => isset($job['date']) ? Carbon::parse($job['date']) : now(),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);

                    $totalNew++;
                }

                // 📊 Guardar métrica igual que GetOnBoard
                LanguageMetric::create([
                    'language_id' => $languageId,
                    'language_name' => $languageName,
                    'jobs_found_count' => $totalFound,
                    'jobs_new_count' => $totalNew,
                    'countries_breakdown' => $countries,
                    'modality_breakdown' => $modalities,
                    'run_date' => now(),
                    'source' => 'Arbeitnow',
                ]);

                $this->info("✅ {$languageName}: {$totalNew} nuevas | 🌍 {$totalFound} encontradas");

            } catch (\Throwable $th) {
                Log::error("⚠️ Error en {$languageName}: " . $th->getMessage());
            }

            sleep(1.5);
        }

        $this->info("\n🎯 Proceso completado: métricas registradas en `language_metrics`.");
    }

    protected function extractCountry(string $location): string
    {
        $loc = strtolower($location);
        return match (true) {
            str_contains($loc, 'germany') => 'Germany',
            str_contains($loc, 'spain') => 'Spain',
            str_contains($loc, 'france') => 'France',
            str_contains($loc, 'portugal') => 'Portugal',
            str_contains($loc, 'remote') => 'Remote',
            default => 'Unknown',
        };
    }

    protected function extractModality(string $location, bool $isRemote): string
    {
        $loc = strtolower($location);
        return match (true) {
            $isRemote, str_contains($loc, 'remote') => 'fully_remote',
            str_contains($loc, 'hybrid') => 'hybrid',
            default => 'no_remote'
        };
    }
}
