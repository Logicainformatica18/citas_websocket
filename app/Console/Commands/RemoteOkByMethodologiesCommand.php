<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Methodology;
use App\Models\JobOffer;
use App\Models\MethodologyMetric;
use Carbon\Carbon;

class RemoteOkByMethodologiesCommand extends Command
{
    protected $signature = 'remoteok:methodologies';
    protected $description = '🧭 Registra empleos 100% remotos desde RemoteOK, agrupados por metodología.';

    public function handle()
    {
        $methodologies = Methodology::pluck('name', 'id');
        $this->info("🚀 Iniciando scraping de RemoteOK (solo empleos con ubicación remota real, por metodología)...");

        foreach ($methodologies as $methodologyId => $methodologyName) {
            $this->warn("\n💡 Procesando metodología: {$methodologyName}");

            $totalFound = 0;
            $totalNew = 0;

            try {
                // 📡 Llamada a la API principal
                $response = Http::timeout(25)->get('https://remoteok.com/api');
                if ($response->failed()) {
                    $this->warn("❌ Falló la API para {$methodologyName}");
                    continue;
                }

                $jobs = collect($response->json())
                    ->skip(1) // Ignorar aviso legal
                    ->filter(fn($j) => isset($j['position']));

                foreach ($jobs as $job) {
                    $title = $job['position'] ?? 'N/A';
                    $company = $job['company'] ?? null;
                    $urlJob = $job['url'] ?? null;
                    $externalId = $job['id'] ?? null;
                    $tags = $job['tags'] ?? [];
                    $location = $job['location'] ?? null;

                    // 🧠 Validar que esté relacionado con la metodología
                    $text = strtolower($title . ' ' . implode(' ', $tags));
                    if (!str_contains($text, strtolower($methodologyName))) {
                        continue;
                    }

                    // ⚙️ Validar que el empleo sea realmente remoto
                    if (empty($location) ||
                        (!str_contains(strtolower($location), 'remote')
                         && !str_contains(strtolower($location), 'anywhere'))) {
                        continue; // ❌ ignorar empleos no remotos
                    }

                    $totalFound++;

                    // 🔍 Evitar duplicados
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
                        'search_query' => $methodologyName,
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

                // 🧾 Guardar métricas
                if ($totalFound > 0) {
                    $today = now()->toDateString();

                    $existsToday = MethodologyMetric::whereDate('run_date', $today)
                        ->where('methodology_id', $methodologyId)
                        ->where('source', 'RemoteOK')
                        ->exists();

                    if (!$existsToday) {
                        MethodologyMetric::create([
                            'methodology_id' => $methodologyId,
                            'methodology_name' => $methodologyName,
                            'jobs_found_count' => $totalFound,
                            'jobs_new_count' => $totalNew,
                            'countries_breakdown' => ['remote' => $totalFound],
                            'modality_breakdown' => ['remote' => $totalFound],
                            'run_date' => now(),
                            'source' => 'RemoteOK',
                        ]);
                    }

                    $this->info("✅ {$methodologyName}: {$totalNew} nuevas / {$totalFound} remotas encontradas");
                } else {
                    $this->warn("⚠️ No se encontraron empleos remotos válidos para {$methodologyName}");
                }

                sleep(1.5);

            } catch (\Throwable $th) {
                Log::error("⚠️ Error procesando {$methodologyName}: " . $th->getMessage());
            }
        }

        $this->info("\n🎯 Finalizado: métricas guardadas solo con empleos 100 % remotos.");
    }
}
