<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Language;
use App\Models\JobOffer;
use App\Models\LanguageMetric;
use Carbon\Carbon;

class RemoteOkByLanguagesCommand extends Command
{
    protected $signature = 'remoteok:languages';
    protected $description = '🌍 Registra únicamente empleos remotos reales desde RemoteOK, agrupados por lenguaje.';

    public function handle()
    {
        $languages = Language::pluck('name', 'id');
        $this->info("🚀 Iniciando scraping de RemoteOK (solo empleos remotos verdaderos)...");

        foreach ($languages as $languageId => $languageName) {
            $this->warn("\n💡 Procesando lenguaje: {$languageName}");

            $totalFound = 0;
            $totalNew = 0;

            try {
                // 📡 Llamada a la API
                $response = Http::timeout(25)->get('https://remoteok.com/api');
                if ($response->failed()) {
                    $this->warn("❌ Falló la API para {$languageName}");
                    continue;
                }

                $jobs = collect($response->json())
                    ->filter(fn($j) => isset($j['position']) && isset($j['remote']) && $j['remote'] === true);

                foreach ($jobs as $job) {
                    $title = $job['position'] ?? 'N/A';
                    $company = $job['company'] ?? null;
                    $urlJob = $job['url'] ?? null;
                    $tags = $job['tags'] ?? [];

                    // 🧠 Validar que esté relacionado con el lenguaje
                    $text = strtolower($title . ' ' . implode(' ', $tags));
                    if (!str_contains($text, strtolower($languageName))) {
                        continue;
                    }

                    $totalFound++;

                    // Evitar duplicados
                    $exists = JobOffer::where('source', 'RemoteOK')
                        ->where(function ($q) use ($title, $company, $languageName, $urlJob) {
                            $q->where('title', $title)
                              ->where('company', $company)
                              ->where('search_query', $languageName)
                              ->where(function ($q2) use ($urlJob) {
                                  $q2->where('url', $urlJob)
                                     ->orWhere('url', 'like', '%' . substr($urlJob, -20) . '%');
                              });
                        })
                        ->exists();

                    if ($exists) continue;

                    // 💾 Insertar oferta solo si es remota
                    JobOffer::create([
                        'title' => $title,
                        'company' => $company,
                        'country' => null,
                        'city' => null,
                        'modality' => 'remote',
                        'latitude' => null,
                        'longitude' => null,
                        'source' => 'RemoteOK',
                        'search_query' => $languageName,
                        'external_id' => $job['id'] ?? null,
                        'url' => $urlJob,
                        'published_at' => isset($job['date'])
                            ? Carbon::parse($job['date'])
                            : now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $totalNew++;
                }

                // 🧾 Registrar métricas si hubo resultados
                if ($totalFound > 0) {
                    $today = now()->toDateString();

                    $existsToday = LanguageMetric::whereDate('run_date', $today)
                        ->where('language_id', $languageId)
                        ->where('source', 'RemoteOK')
                        ->exists();

                    if ($existsToday) {
                        $this->warn("📆 Ya existe una métrica registrada hoy ({$today}) para {$languageName}, se omite.");
                        continue;
                    }

                    LanguageMetric::create([
                        'language_id' => $languageId,
                        'language_name' => $languageName,
                        'jobs_found_count' => $totalFound,
                        'jobs_new_count' => $totalNew,
                        'countries_breakdown' => ['Remote' => $totalFound],
                        'modality_breakdown' => ['remote' => $totalFound],
                        'run_date' => now(),
                        'source' => 'RemoteOK',
                    ]);

                    $this->info("✅ {$languageName}: {$totalNew} nuevas / {$totalFound} remotas encontradas");
                } else {
                    $this->warn("⚠️ No se encontraron empleos remotos válidos para {$languageName}");
                }

                sleep(1.5);

            } catch (\Throwable $th) {
                Log::error("⚠️ Error procesando {$languageName}: " . $th->getMessage());
            }
        }

        $this->info("\n🎯 Finalizado: métricas guardadas solo con empleos 100 % remotos.");
    }
}
