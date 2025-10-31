<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Technology;
use App\Models\JobOffer;
use App\Models\TechnologyMetric;
use Carbon\Carbon;

class JobicyByTechnologiesCommand extends Command
{
    protected $signature = 'jobicy:technologies';
    protected $description = '🌍 Importa ofertas laborales desde Jobicy por tecnología, con geolocalización, modalidad y métricas diarias.';

    protected $stats = [
        'mapped'   => 0,
        'api_hits' => 0,
        'fallback' => 0,
        'skipped'  => 0,
    ];

    protected $capitalMap = [
        'us' => ['city' => 'Washington D.C.', 'lat' => 38.8951, 'lng' => -77.0364],
        'ca' => ['city' => 'Ottawa', 'lat' => 45.4215, 'lng' => -75.6997],
        'gb' => ['city' => 'Londres', 'lat' => 51.5074, 'lng' => -0.1278],
        'au' => ['city' => 'Sídney', 'lat' => -33.8688, 'lng' => 151.2093],
        'es' => ['city' => 'Madrid', 'lat' => 40.4168, 'lng' => -3.7038],
        'mx' => ['city' => 'Ciudad de México', 'lat' => 19.4326, 'lng' => -99.1332],
        'br' => ['city' => 'Brasilia', 'lat' => -15.7939, 'lng' => -47.8828],
        'in' => ['city' => 'Nueva Delhi', 'lat' => 28.6139, 'lng' => 77.2090],
        'de' => ['city' => 'Berlín', 'lat' => 52.5200, 'lng' => 13.4050],
        'fr' => ['city' => 'París', 'lat' => 48.8566, 'lng' => 2.3522],
    ];

    public function handle()
    {
        $technologies = Technology::whereIn('technologies.id', function ($q) {
            $q->select('course_technology.technology_id')
                ->from('course_technology')
                ->join('career_course', 'career_course.course_id', '=', 'course_technology.course_id');
        })->pluck('name', 'id');

        $this->info("🌍 Iniciando importación desde Jobicy para {$technologies->count()} tecnologías...");

        foreach ($technologies as $techId => $techName) {
            $this->warn("\n💡 Procesando tecnología: {$techName}");

            try {
                $response = Http::timeout(25)->get('https://jobicy.com/api/v2/remote-jobs');
                if ($response->failed()) {
                    $this->error("❌ Error al consultar Jobicy API para {$techName}");
                    continue;
                }

                $results = collect($response->json('jobs') ?? [])
                    ->filter(fn($job) => str_contains(
                        strtolower(($job['jobTitle'] ?? '') . ' ' . ($job['jobDescription'] ?? '')),
                        strtolower($techName)
                    ))
                    ->values()
                    ->toArray();

                $totalFound = count($results);
                $totalNew = 0;
                $countries = [];
                $modalities = [];

                foreach ($results as $job) {
                    $externalId = $job['id'] ?? null;
                    if ($externalId && JobOffer::where('external_id', $externalId)->exists()) {
                        continue;
                    }

                    // ==============================
                    // 🌍 Detección y normalización de país
                    // ==============================
                    $mapCountry = [
                        'us' => 'United States', 'usa' => 'United States', 'united states' => 'United States',
                        'ca' => 'Canada', 'canada' => 'Canada',
                        'uk' => 'United Kingdom', 'gb' => 'United Kingdom', 'united kingdom' => 'United Kingdom',
                        'sp' => 'Spain', 'es' => 'Spain', 'spain' => 'Spain',
                        'mx' => 'Mexico', 'me' => 'Mexico', 'mexico' => 'Mexico',
                        'br' => 'Brazil', 'brazil' => 'Brazil',
                        'fr' => 'France', 'france' => 'France',
                        'de' => 'Germany', 'germany' => 'Germany',
                        'it' => 'Italy', 'italy' => 'Italy',
                        'co' => 'Colombia', 'colombia' => 'Colombia',
                        'ar' => 'Argentina', 'argentina' => 'Argentina',
                        'pe' => 'Peru', 'peru' => 'Peru',
                        'cl' => 'Chile', 'chile' => 'Chile',
                        'eu' => 'Europe', 'europe' => 'Europe',
                        'ww' => 'Worldwide', 'worldwide' => 'Worldwide', 'global' => 'Worldwide'
                    ];

                    $countryRaw = strtolower(trim($job['jobGeo'] ?? ''));
                    $countryName = $mapCountry[$countryRaw] ?? null;

                    // 🧹 Si no se detecta país, descartar el registro
                    if (empty($countryName)) {
                        $this->stats['skipped']++;
                        Log::info("⏭️ Omitido: {$job['jobTitle']} (sin país detectado)");
                        continue;
                    }

                    // ==============================
                    // 🧭 Geolocalización por país
                    // ==============================
                    $city = $this->capitalMap[strtolower(substr($countryRaw, 0, 2))]['city'] ?? 'Remote';
                    [$city, $lat, $lng] = $this->getCoordsFromCountry($city, substr($countryRaw, 0, 2));

                    // ==============================
                    // 💼 Otros datos
                    // ==============================
                    $title   = $job['jobTitle'] ?? 'N/A';
                    $company = $job['companyName'] ?? null;
                    $urlJob  = $job['url'] ?? null;
                    $desc    = strtolower($job['jobDescription'] ?? '');
                    $modality = $this->detectModality(
                        $desc,
                        is_array($job['jobType']) ? implode(' ', $job['jobType']) : ($job['jobType'] ?? '')
                    );

                    // ==============================
                    // 💾 Guardar oferta
                    // ==============================
                    JobOffer::create([
                        'title'        => $title,
                        'company'      => $company,
                        'country'      => $countryName,
                        'city'         => $city,
                        'latitude'     => $lat,
                        'longitude'    => $lng,
                        'modality'     => $modality,
                        'salary_min'   => null,
                        'salary_max'   => null,
                        'currency'     => 'USD',
                        'source'       => 'Jobicy',
                        'external_id'  => $externalId,
                        'url'          => $urlJob,
                        'search_query' => $techName,
                        'published_at' => isset($job['pubDate']) ? Carbon::parse($job['pubDate']) : now(),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);

                    // 📊 Acumular métricas
                    $totalNew++;
                    $countries[$countryName] = ($countries[$countryName] ?? 0) + 1;
                    $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;
                }

                // ==============================
                // 📈 Guardar métricas diarias
                // ==============================
                if ($totalNew > 0) {
                    $today = now()->toDateString();
                    $existsToday = TechnologyMetric::whereDate('run_date', $today)
                        ->where('technology_id', $techId)
                        ->where('source', 'Jobicy')
                        ->exists();

                    if (!$existsToday) {
                        TechnologyMetric::create([
                            'technology_id'       => $techId,
                            'technology_name'     => $techName,
                            'jobs_found_count'    => $totalFound,
                            'jobs_new_count'      => $totalNew,
                            'countries_breakdown' => $countries,
                            'modality_breakdown'  => $modalities,
                            'run_date'            => Carbon::today(),
                            'source'              => 'Jobicy',
                        ]);
                    }
                }

                $this->info("✅ {$techName}: {$totalNew} nuevas | 🌍 {$totalFound} encontradas | ⏭️ Omitidas: {$this->stats['skipped']}");

            } catch (\Throwable $e) {
                Log::error("⚠️ Error en {$techName}: " . $e->getMessage());
                $this->error("❌ {$techName}: " . $e->getMessage());
            }

            sleep(1.5);
        }

        $this->newLine();
        $this->info("🎯 Proceso completado Jobicy");
        $this->line("   ⏭️ Ofertas omitidas sin país: {$this->stats['skipped']}");
    }

    protected function detectModality(string $desc, string $type): string
    {
        $text = strtolower($desc . ' ' . $type);

        return match (true) {
            str_contains($text, 'fully remote'),
            str_contains($text, 'work from anywhere'),
            str_contains($text, 'remote worldwide'),
            str_contains($text, 'anywhere') => 'fully_remote',

            str_contains($text, 'hybrid'),
            str_contains($text, 'partial remote') => 'hybrid',

            str_contains($text, 'remote local'),
            str_contains($text, 'remote in'),
            str_contains($text, 'local remote') => 'remote_local',

            default => 'no_remote',
        };
    }

  protected function getCoordsFromCountry(?string $city, ?string $countryCode)
{
    // 🔍 Intentar buscar en la base de datos de ciudades
    if ($city && strtolower($city) !== 'remote') {
        $foundCity = \App\Models\City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])
            ->when($countryCode, fn($q) => $q->whereRaw('LOWER(iso2) = ?', [strtolower($countryCode)]))
            ->first();

        if ($foundCity) {
            $this->stats['mapped']++;
            return [$foundCity->city, $foundCity->lat, $foundCity->lng];
        }
    }

    // 🌍 Si no se encuentra en la BD, usar el capitalMap como fallback
    if ($countryCode && isset($this->capitalMap[$countryCode])) {
        $capital = $this->capitalMap[$countryCode];
        $this->stats['fallback']++;
        return [$capital['city'], $capital['lat'], $capital['lng']];
    }

    // ⏭️ En último caso, devolver Remote sin coordenadas
    $this->stats['skipped']++;
    return [$city ?? 'Remote', null, null];
}

}
