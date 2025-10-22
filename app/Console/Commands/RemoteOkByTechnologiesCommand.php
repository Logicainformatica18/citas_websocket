<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Technology;
use App\Models\JobOffer;
use App\Models\TechnologyMetric;
use Carbon\Carbon;

class RemoteOkByTechnologiesCommand extends Command
{
    protected $signature = 'remoteok:technologies';
    protected $description = '🧩 Registra empleos 100% remotos desde RemoteOK, agrupados por tecnología.';

    public function handle()
    {
        $technologies = Technology::pluck('name', 'id');
        $this->info("🚀 Iniciando scraping de RemoteOK (solo empleos con ubicación remota real, por tecnología)...");

        foreach ($technologies as $techId => $techName) {
            $this->warn("\n💡 Procesando tecnología: {$techName}");

            $totalFound = 0;
            $totalNew = 0;

            try {
                // 📡 Llamada a la API principal de RemoteOK
                $response = Http::timeout(25)->get('https://remoteok.com/api');
                if ($response->failed()) {
                    $this->warn("❌ Falló la API para {$techName}");
                    continue;
                }

                $jobs = collect($response->json())
                    ->skip(1) // Ignora el aviso legal
                    ->filter(fn($j) => isset($j['position']));

                foreach ($jobs as $job) {
                    $title = $job['position'] ?? 'N/A';
                    $company = $job['company'] ?? null;
                    $urlJob = $job['url'] ?? null;
                    $externalId = $job['id'] ?? null;
                    $tags = $job['tags'] ?? [];
                    $location = $job['location'] ?? null;

                    // 🧠 Validar que esté relacionado con la tecnología
                    $text = strtolower($title . ' ' . implode(' ', $tags));
                    if (!str_contains($text, strtolower($techName))) {
                        continue;
                    }

                    // ⚙️ Validar que el empleo sea realmente remoto
                    if (empty($location) ||
                        (!str_contains(strtolower($location), 'remote')
                         && !str_contains(strtolower($location), 'anywhere'))) {
                        continue; // ❌ ignorar empleos no remotos
                    }

                    $totalFound++;

                    // 🔍 Evitar duplicados (por external_id)
                    if ($externalId && JobOffer::where('source', 'RemoteOK')
                        ->where('external_id', $externalId)
                        ->exists()) {
                        continue;
                    }

                    // 💾 Insertar oferta remota
                    JobOffer::create([
                        'title' => $title,
                        'company' => $company,
                        'country' => 'Remote',
                        'city' => null,
                        'modality' => 'remote',
                        'latitude' => null,
                        'longitude' => null,
                        'source' => 'RemoteOK',
                        'search_query' => $techName,
                        'external_id' => $externalId,
                        'url' => $urlJob,
                        'published_at' => isset($job['date'])
                            ? Carbon::parse($job['date'])
                            : now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $totalNew++;
                }

                // 🧾 Guardar métricas del día
                if ($totalFound > 0) {
                    $today = now()->toDateString();

                    $existsToday = TechnologyMetric::whereDate('run_date', $today)
                        ->where('technology_id', $techId)
                        ->where('source', 'RemoteOK')
                        ->exists();

                    if (!$existsToday) {
                        TechnologyMetric::create([
                            'technology_id' => $techId,
                            'technology_name' => $techName,
                            'jobs_found_count' => $totalFound,
                            'jobs_new_count' => $totalNew,
                            'countries_breakdown' => ['remote' => $totalFound],
                            'modality_breakdown' => ['remote' => $totalFound],
                            'run_date' => now(),
                            'source' => 'RemoteOK',
                        ]);
                    }

                    $this->info("✅ {$techName}: {$totalNew} nuevas / {$totalFound} remotas encontradas");
                } else {
                    $this->warn("⚠️ No se encontraron empleos remotos válidos para {$techName}");
                }

                sleep(1.5);

            } catch (\Throwable $th) {
                Log::error("⚠️ Error procesando {$techName}: " . $th->getMessage());
            }
        }

        $this->info("\n🎯 Finalizado: métricas guardadas solo con empleos 100 % remotos.");
    }
}
