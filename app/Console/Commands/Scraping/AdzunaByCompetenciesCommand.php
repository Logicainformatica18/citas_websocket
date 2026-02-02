<?php

namespace App\Console\Commands\Scraping;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Competency;
use App\Models\JobOffer;
use App\Models\City;
use Carbon\Carbon;
use App\Helpers\RegionHelper;

class AdzunaByCompetenciesCommand extends Command
{
    protected $signature = 'adzuna:competencies {--country=us} {--pages=1}';
    protected $description = '🌐 Importa ofertas laborales desde Adzuna por competencias, con geolocalización y asociación automática.';

    protected $stats = [
        'api_hits'  => 0,
        'fallback'  => 0,
        'mapped'    => 0,
        'skipped'   => 0,
    ];

    protected $capitalMap = [
        'us' => ['city' => 'Washington D.C.', 'lat' => 38.8951, 'lng' => -77.0364],
        'mx' => ['city' => 'Ciudad de México', 'lat' => 19.4326, 'lng' => -99.1332],
        'ca' => ['city' => 'Ottawa', 'lat' => 45.4215, 'lng' => -75.6997],
        'br' => ['city' => 'Brasilia', 'lat' => -15.7939, 'lng' => -47.8828],
        'es' => ['city' => 'Madrid', 'lat' => 40.4168, 'lng' => -3.7038],
        'gb' => ['city' => 'Londres', 'lat' => 51.5074, 'lng' => -0.1278],
        'fr' => ['city' => 'París', 'lat' => 48.8566, 'lng' => 2.3522],
    ];
  public function handle()
{
    $country = strtolower($this->option('country'));
    $pages   = (int) $this->option('pages');

    // Solo competencias asociadas a carreras (tu requerimiento)
    // $competencies = Competency::whereNotNull('career_id')
    //     ->pluck('description_en', 'id');
    $competencies = Competency::where('career_id', 11)
    ->pluck('description_en', 'id');


    $appId   = config('services.adzuna.app_id');
    $appKey  = config('services.adzuna.app_key');
    $baseUrl = config('services.adzuna.base_url', 'https://api.adzuna.com/v1/api/jobs');

    $this->info("🌍 Importando desde Adzuna para {$competencies->count()} competencias...");

    foreach ($competencies as $competencyId => $description_en) {

        $this->warn("\n💡 Procesando competencia: {$description_en}");

        $totalFound = 0;   // cuántos resultados devolvió Adzuna
        $totalNew   = 0;   // cuántas ofertas nuevas se insertaron

        for ($page = 1; $page <= $pages; $page++) {

            $url = "{$baseUrl}/{$country}/search/{$page}?app_id={$appId}&app_key={$appKey}"
                 . "&results_per_page=100&what=" . urlencode($description_en);

            try {
                $response = Http::timeout(25)->get($url);
                if ($response->failed()) continue;

                $results = $response->json('results') ?? [];

                $totalFound += count($results);

                foreach ($results as $job) {

                    // Crear o reutilizar JobOffer
                    $offer = $this->processJobOffer($job, $description_en, $country);

                    if ($offer) {
                        // Asociar competencia en pivot
                        $offer->competencies()->syncWithoutDetaching([$competencyId]);
                        $totalNew++;
                    }
                }

                sleep(1.1);

            } catch (\Throwable $e) {
                $this->error("❌ Error en {$description_en}: {$e->getMessage()}");
                Log::error("Error en AdzunaByCompetenciesCommand: " . $e->getMessage());
            }
        }

        // =====================================================
        // 📊 MÉTRICAS DIARIAS POR COMPETENCIA
        // =====================================================
        $today = now()->toDateString();

        \App\Models\CompetencyMetric::updateOrCreate(
            [
                'competency_id' => $competencyId,
                'run_date'      => $today,
                'source'        => 'Adzuna',
            ],
            [
                'competency_name'    => $description_en,
                'jobs_found_count'   => $totalFound,
                'jobs_new_count'     => $totalNew,
                'updated_at'         => now(),
            ]
        );
    }

    $this->info("\n🎯 Proceso completado.");
}

protected function processJobOffer(array $job, string $query, string $country)
{
    $existing = JobOffer::where('external_id', $job['id'])->first();

    $area = $job['location']['area'] ?? [];
    $city = $area[1] ?? ($area[0] ?? null);
    $countryName = $area[0] ?? null;

    $countryCode = strtoupper($countryName ?? $country);

    [$city, $lat, $lng] = $this->getCoordsFromCountry($city, strtolower($countryCode));

    if (!$lat || !$lng) {
        if (!isset($this->capitalMap[$countryCode])) return null;

        $city = $this->capitalMap[$countryCode]['city'];
        $lat  = $this->capitalMap[$countryCode]['lat'];
        $lng  = $this->capitalMap[$countryCode]['lng'];
    }

    if ($existing) {
        return $existing;
    }

    $offer = JobOffer::create([
        'title'        => $job['title'] ?? 'N/A',
        'company'      => $job['company']['display_name'] ?? null,
        'country'      => $countryCode,
        'city'         => $city,
        'latitude'     => $lat,
        'longitude'    => $lng,
        'description'  => strip_tags($job['description'] ?? ''),
        'source'       => 'Adzuna',
        'external_id'  => $job['id'],
        'url'          => $job['redirect_url'] ?? null,
        'search_query' => $query,
        'published_at' => isset($job['created']) ? Carbon::parse($job['created']) : now(),
    ]);

    return $offer;
}
  protected function detectModality(string $location, string $description): string
    {
        $text = strtolower($location . ' ' . $description);

        return match (true) {
            str_contains($text, 'remote'),
            str_contains($text, 'work from home'),
            str_contains($text, 'home office'),
            str_contains($text, 'teletrabajo'),
            str_contains($text, 'anywhere') => 'remote',

            str_contains($text, 'hybrid'),
            str_contains($text, 'híbrido') => 'hybrid',

            default => 'no_remote',
        };
    }

    protected function getCoordsFromCountry(?string $city, ?string $countryCode)
    {
        if ($city && strtolower($city) !== 'remote') {
            $foundCity = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])
                ->when($countryCode, fn($q) => $q->whereRaw('LOWER(iso2) = ?', [strtolower($countryCode)]))
                ->first();

            if ($foundCity) {
                $this->stats['mapped']++;
                return [$foundCity->city, $foundCity->lat, $foundCity->lng];
            }

            [$lat, $lng] = $this->getCoords($city, $countryCode);
            if ($lat && $lng) {
                $this->stats['api_hits']++;
                return [$city, $lat, $lng];
            }
        }

        return [$city, null, null];
    }

    protected function getCoords(?string $city, ?string $country)
    {
        try {
            $res = Http::withHeaders(['User-Agent' => 'LaravelJobScraper/1.0'])
                ->timeout(10)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => "{$city}, {$country}",
                    'format' => 'json',
                    'limit' => 1,
                ]);

            if ($res->ok() && count($res->json()) > 0) {
                $data = $res->json()[0];
                return [(float) $data['lat'], (float) $data['lon']];
            }
        } catch (\Throwable $th) {
            Log::warning("🌍 Error geocodificando {$city}, {$country}: " . $th->getMessage());
        }

        return [null, null];
    }
        // 🧩 Extrae nivel de experiencia del texto
    protected function extractExperience(string $text): ?string
    {
        $t = strtolower($text);

        return match (true) {
            str_contains($t, 'senior') || str_contains($t, 'sr.') => 'senior',
            str_contains($t, 'mid-level') || str_contains($t, 'semi senior') => 'mid',
            str_contains($t, 'junior') || str_contains($t, 'jr.') => 'junior',
            default => null,
        };
    }

    // 🎓 Detecta nivel educativo
    protected function extractEducation(string $text): ?string
    {
        $t = strtolower($text);

        return match (true) {
            str_contains($t, 'bachelor') || str_contains($t, 'licenciatura') => 'bachelor',
            str_contains($t, 'master') || str_contains($t, 'maestría') => 'master',
            str_contains($t, 'phd') || str_contains($t, 'doctorado') => 'phd',
            str_contains($t, 'technical') || str_contains($t, 'tecnico') => 'technical',
            default => null,
        };
    }

    // 🏅 Busca certificaciones comunes
    protected function extractCertifications(string $text): ?string
    {
        $t = strtolower($text);
        $found = [];

        foreach (['aws', 'azure', 'google cloud', 'scrum', 'pmp', 'cisco', 'ccna', 'itil'] as $cert) {
            if (str_contains($t, $cert)) $found[] = strtoupper($cert);
        }

        return !empty($found) ? implode(', ', $found) : null;
    }

    // 🧩 Extrae habilidades técnicas
    protected function extractSkills(string $text): ?string
    {
        $t = strtolower($text);
        $skills = [];

        foreach (['python', 'java', 'php', 'laravel', 'react', 'vue', 'sql', 'docker', 'aws', 'git', 'node'] as $skill) {
            if (str_contains($t, $skill)) $skills[] = strtoupper($skill);
        }

        return !empty($skills) ? implode(', ', $skills) : null;
    }
}
