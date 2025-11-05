<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Methodology;
use App\Models\JobOffer;
use App\Models\MethodologyMetric;
use App\Models\City;
use Carbon\Carbon;

class AdzunaByMethodologiesCommand extends Command
{
    protected $signature = 'adzuna:methodologies {--country=us} {--pages=1}';
    protected $description = '🌐 Importa ofertas laborales desde Adzuna por metodología, con geolocalización, modalidad y métricas diarias.';

    protected $stats = [
        'api_hits'  => 0,
        'fallback'  => 0,
        'mapped'    => 0,
        'skipped'   => 0,
    ];

    protected $capitalMap = [
        // 🌎 América
        'us' => ['city' => 'Washington D.C.', 'lat' => 38.8951, 'lng' => -77.0364, 'country' => 'United States'],
        'ca' => ['city' => 'Ottawa', 'lat' => 45.4215, 'lng' => -75.6997, 'country' => 'Canada'],
        'mx' => ['city' => 'Ciudad de México', 'lat' => 19.4326, 'lng' => -99.1332, 'country' => 'Mexico'],
        'br' => ['city' => 'Brasilia', 'lat' => -15.7939, 'lng' => -47.8828, 'country' => 'Brazil'],

        // 🌍 Europa
        'es' => ['city' => 'Madrid', 'lat' => 40.4168, 'lng' => -3.7038, 'country' => 'Spain'],
        'fr' => ['city' => 'París', 'lat' => 48.8566, 'lng' => 2.3522, 'country' => 'France'],
        'de' => ['city' => 'Berlín', 'lat' => 52.5200, 'lng' => 13.4050, 'country' => 'Germany'],
        'it' => ['city' => 'Roma', 'lat' => 41.9028, 'lng' => 12.4964, 'country' => 'Italy'],
        'gb' => ['city' => 'Londres', 'lat' => 51.5074, 'lng' => -0.1278, 'country' => 'United Kingdom'],
        'nl' => ['city' => 'Ámsterdam', 'lat' => 52.3676, 'lng' => 4.9041, 'country' => 'Netherlands'],
        'ch' => ['city' => 'Berna', 'lat' => 46.9480, 'lng' => 7.4474, 'country' => 'Switzerland'],
        'pl' => ['city' => 'Varsovia', 'lat' => 52.2297, 'lng' => 21.0122, 'country' => 'Poland'],

        // 🌏 Asia
        'in' => ['city' => 'Nueva Delhi', 'lat' => 28.6139, 'lng' => 77.2090, 'country' => 'India'],
        'sg' => ['city' => 'Singapur', 'lat' => 1.3521, 'lng' => 103.8198, 'country' => 'Singapore'],

        // 🌍 África
        'za' => ['city' => 'Pretoria', 'lat' => -25.7461, 'lng' => 28.1881, 'country' => 'South Africa'],

        // 🌏 Oceanía
        'au' => ['city' => 'Sídney', 'lat' => -33.8688, 'lng' => 151.2093, 'country' => 'Australia'],
        'nz' => ['city' => 'Wellington', 'lat' => -41.2865, 'lng' => 174.7762, 'country' => 'New Zealand'],

        // 🌐 Remoto
        'remote' => ['city' => 'Remoto', 'lat' => 0.0000, 'lng' => 0.0000, 'country' => 'Remote'],
    ];

    public function handle()
    {
        $country = strtolower($this->option('country'));
        $pages   = (int) $this->option('pages');

        $methodologies = Methodology::whereIn('methodologies.id', function ($q) {
            $q->select('course_methodology.methodology_id')
                ->from('course_methodology')
                ->join('career_course', 'career_course.course_id', '=', 'course_methodology.course_id');
        })->pluck('name', 'id');

        $appId   = config('services.adzuna.app_id');
        $appKey  = config('services.adzuna.app_key');
        $baseUrl = config('services.adzuna.base_url', 'https://api.adzuna.com/v1/api/jobs');

        $this->info("🌍 Iniciando importación desde Adzuna para {$methodologies->count()} metodologías...");

        foreach ($methodologies as $mId => $mName) {
            $this->warn("\n💡 Procesando metodología: {$mName}");

            $totalFound = $totalNew = $totalDuplicates = $totalUnmapped = 0;
            $countries = [];
            $modalities = [];

            for ($page = 1; $page <= $pages; $page++) {
                $url = "{$baseUrl}/{$country}/search/{$page}?app_id={$appId}&app_key={$appKey}&results_per_page=100&what=" . urlencode($mName);

                try {
                    $response = Http::timeout(25)->get($url);
                    if ($response->failed()) {
                        $this->error("❌ Error al consultar API (página {$page})");
                        continue;
                    }

                    $results = $response->json('results') ?? [];
                    $totalFound += count($results);

                    foreach ($results as $job) {
                        $title   = $job['title'] ?? 'N/A';
                        $company = $job['company']['display_name'] ?? null;
                        $desc    = strtolower($job['description'] ?? '');
                        $loc     = strtolower(($job['location']['display_name'] ?? '') . ' ' . ($job['title'] ?? ''));
                        $urlJob  = $job['redirect_url'] ?? null;

                        // 🧭 Modalidad
                        $modality = $this->detectModality($loc, $desc);

                        $area    = $job['location']['area'] ?? [];
                        $countryCode = strtolower($country);
                        $city    = $area[1] ?? null;

                        [$city, $latitude, $longitude] = $this->getCoordsFromCountry($city, $countryCode);

                        if (!$latitude || !$longitude) {
                            if (isset($this->capitalMap[$countryCode])) {
                                $capital = $this->capitalMap[$countryCode];
                                $city = $capital['city'];
                                $latitude = $capital['lat'];
                                $longitude = $capital['lng'];
                                $countryName = $capital['country'];
                                $this->stats['fallback']++;
                            } else {
                                $this->stats['skipped']++;
                                $totalUnmapped++;
                                continue;
                            }
                        } else {
                            $countryName = strtoupper($countryCode);
                        }

                        // Evitar duplicados
                        if (!empty($job['id']) && JobOffer::where('external_id', $job['id'])->exists()) {
                            $totalDuplicates++;
                            continue;
                        }

                        JobOffer::create([
                            'title'        => $title,
                            'company'      => $company,
                            'country'      => $countryName ?? strtoupper($countryCode),
                            'city'         => $city,
                            'latitude'     => $latitude,
                            'longitude'    => $longitude,
                            'modality'     => $modality,
                            'salary_min'   => $job['salary_min'] ?? null,
                            'salary_max'   => $job['salary_max'] ?? null,
                            'currency'     => $job['salary_currency'] ?? 'USD',
                            'source'       => 'Adzuna',
                            'external_id'  => $job['id'] ?? null,
                            'url'          => $urlJob,
                            'search_query' => $mName,
                            'published_at' => isset($job['created']) ? Carbon::parse($job['created']) : now(),
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ]);

                        $totalNew++;
                        $countries[$countryName ?? 'Unknown'] = ($countries[$countryName ?? 'Unknown'] ?? 0) + 1;
                        $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;
                    }

                    sleep(1.2);
                } catch (\Throwable $e) {
                    Log::error("⚠️ Error en {$mName} (página {$page}): " . $e->getMessage());
                    $this->error("❌ {$mName}: " . $e->getMessage());
                }
            }

            // 📊 Registrar métricas diarias
            $today = now()->toDateString();
            $existsToday = MethodologyMetric::whereDate('run_date', $today)
                ->where('methodology_id', $mId)
                ->where('source', 'Adzuna')
                ->exists();

            if (!$existsToday) {
                MethodologyMetric::create([
                    'methodology_id'        => $mId,
                    'methodology_name'      => $mName,
                    'jobs_found_count'      => $totalFound,
                    'jobs_new_count'        => $totalNew,
                    'countries_breakdown'   => $countries,
                    'modality_breakdown'    => $modalities,
                    'run_date'              => Carbon::today(),
                    'source'                => 'Adzuna',
                ]);
            }

            $this->info("✅ {$mName}: {$totalNew} nuevas | 🌍 {$totalFound} encontradas");
        }

        $this->newLine();
        $this->info("🎯 Proceso completado:");
        $this->line("   🗺️ Mapeadas: {$this->stats['mapped']}");
        $this->line("   🛰️ Geocodificadas API: {$this->stats['api_hits']}");
        $this->line("   🏙️ Fallbacks (capital): {$this->stats['fallback']}");
        $this->line("   ⏭️ Omitidas: {$this->stats['skipped']}");
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
}
