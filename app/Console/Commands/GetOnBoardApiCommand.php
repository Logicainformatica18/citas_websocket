<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Language;
use App\Models\LanguageMetric;
use App\Models\JobOffer;
use Carbon\Carbon;

class GetOnBoardApiCommand extends Command
{
    /**
     * El nombre y la firma del comando de consola
     */
    protected $signature = 'getonboard:import';

    /**
     * Descripción del comando
     */
    protected $description = 'Importa ofertas de trabajo desde GetOnBoard en base a los lenguajes registrados';

   public function handle()
{
    $languages = Language::pluck('id', 'name');

    foreach ($languages as $languageName => $languageId) {
        $this->info("🔎 Buscando ofertas para: {$languageName}");

        $url = "https://www.getonbrd.com/api/v0/search/jobs?query=" . urlencode($languageName) . "&per_page=100";

        try {
            $response = Http::get($url);
            if ($response->failed()) {
                $this->error("❌ Error al consultar la API para {$languageName}");
                continue;
            }

            $data = $response->json('data') ?? [];
            $saved = 0;
            $countries = [];
            $modalities = [];

            foreach ($data as $job) {
                $attr = $job['attributes'] ?? [];

                $country  = $attr['countries'][0] ?? 'Desconocido';
                $modality = $attr['remote_modality'] ?? 'unknown';

                $countries[$country] = ($countries[$country] ?? 0) + 1;
                $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;

                $existing = JobOffer::where('url', $job['links']['public_url'] ?? '')->exists();

                JobOffer::updateOrCreate(
                    ['url' => $job['links']['public_url'] ?? null],
                    [
                        'title' => $attr['title'] ?? 'N/A',
                        'company' => $attr['company']['data']['attributes']['name'] ?? null,
                        'country' => implode(',', $attr['countries'] ?? []),
                        'city' => $attr['city'] ?? null,
                        'modality' => $modality,
                        'salary_min' => $attr['min_salary'] ?? null,
                        'salary_max' => $attr['max_salary'] ?? null,
                        'currency' => $attr['salary_currency'] ?? 'USD',
                        'experience_level' => $attr['experience_level'] ?? null,
                        'category' => $attr['category_name'] ?? null,
                        'role' => $attr['role'] ?? null,
                        'source' => 'GetOnBoard',
                        'external_id' => $job['id'] ?? null,
                        'published_at' => isset($attr['published_at'])
                            ? Carbon::createFromTimestamp($attr['published_at'])
                            : null,
                    ]
                );

                if (!$existing) {
                    $saved++;
                }
            }

            // 🔹 Registrar métrica
            LanguageMetric::create([
                'language_id' => $languageId,
                'jobs_found_count' => count($data),
                'jobs_new_count' => $saved,
                'countries_breakdown' => $countries,
                'modality_breakdown' => $modalities,
                'run_date' => now(),
                'source' => 'GetOnBoard',
            ]);

            $this->info("✅ {$saved} nuevas ofertas para {$languageName}");

        } catch (\Throwable $e) {
            $this->error("⚠️ Error en {$languageName}: " . $e->getMessage());
        }

        sleep(2);
    }

    $this->info("🎉 Importación y métricas completadas.");
}
}
