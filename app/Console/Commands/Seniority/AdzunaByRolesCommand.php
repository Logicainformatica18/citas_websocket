<?php

namespace App\Console\Commands\Seniority;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\JobOffer;
use App\Models\City;
use Carbon\Carbon;
use App\Helpers\RegionHelper;
use App\Services\ScraperRunService;

class AdzunaByRolesCommand extends Command
{
    protected $signature = 'adzuna:roles {--country=us} {--pages=1}';
    protected $description = '🌐 Importa ofertas por ROLE + Seniority (Junior/Mid/Senior) desde Adzuna.';

    protected array $roles = [
        'Software Engineer',
        'Backend Developer',
        'Frontend Developer',
        'Full Stack Developer',
        'DevOps Engineer',
        'Cloud Engineer',
        'Data Engineer',
        'Data Scientist',
        'Cybersecurity Analyst',
        'QA Engineer',
    ];

    protected array $seniorityLevels = [
        'Junior' => 'junior',
        'Mid'    => 'mid',
        'Senior' => 'senior',
    ];

    protected $capitalMap = [
        // 🌎 América
        'us' => ['city' => 'Washington D.C.', 'lat' => 38.8951, 'lng' => -77.0364],
        'ca' => ['city' => 'Ottawa', 'lat' => 45.4215, 'lng' => -75.6997],
        'mx' => ['city' => 'Ciudad de México', 'lat' => 19.4326, 'lng' => -99.1332],
        'br' => ['city' => 'Brasilia', 'lat' => -15.7939, 'lng' => -47.8828],

        // 🌍 Europa
        'es' => ['city' => 'Madrid', 'lat' => 40.4168, 'lng' => -3.7038],
        'fr' => ['city' => 'París', 'lat' => 48.8566, 'lng' => 2.3522],
        'de' => ['city' => 'Berlín', 'lat' => 52.5200, 'lng' => 13.4050],
        'it' => ['city' => 'Roma', 'lat' => 41.9028, 'lng' => 12.4964],
        'gb' => ['city' => 'Londres', 'lat' => 51.5074, 'lng' => -0.1278],
        'nl' => ['city' => 'Ámsterdam', 'lat' => 52.3676, 'lng' => 4.9041],
        'be' => ['city' => 'Bruselas', 'lat' => 50.8503, 'lng' => 4.3517],
        'ch' => ['city' => 'Berna', 'lat' => 46.9480, 'lng' => 7.4474],
        'pl' => ['city' => 'Varsovia', 'lat' => 52.2297, 'lng' => 21.0122],

        // 🌏 Asia
        'in' => ['city' => 'Nueva Delhi', 'lat' => 28.6139, 'lng' => 77.2090],
        'sg' => ['city' => 'Singapur', 'lat' => 1.3521, 'lng' => 103.8198],

        // 🌏 Oceanía
        'au' => ['city' => 'Sídney', 'lat' => -33.8688, 'lng' => 151.2093],
        'nz' => ['city' => 'Wellington', 'lat' => -41.2865, 'lng' => 174.7762],

        // 🌍 África
        'za' => ['city' => 'Pretoria', 'lat' => -25.7461, 'lng' => 28.1881],
    ];

    public function handle()
    {
        $run = ScraperRunService::start(
            $this->signature,
            'Adzuna',
            'seniority_roles'
        );

        $country = strtolower($this->option('country'));
        $pages   = (int) $this->option('pages');

        $appId   = config('services.adzuna.app_id');
        $appKey  = config('services.adzuna.app_key');
        $baseUrl = config('services.adzuna.base_url', 'https://api.adzuna.com/v1/api/jobs');

        $totalFoundAll = 0;
        $totalProcessedAll = 0;

        try {

            foreach ($this->roles as $role) {

                foreach ($this->seniorityLevels as $seniorityLabel => $seniorityValue) {

                    $searchQuery = "{$seniorityLabel} {$role}";
                    $this->info("🔎 {$searchQuery}");

                    for ($page = 1; $page <= $pages; $page++) {

                        $url = "{$baseUrl}/{$country}/search/{$page}"
                            . "?app_id={$appId}&app_key={$appKey}"
                            . "&results_per_page=100"
                            . "&what=" . urlencode($searchQuery);

                        $response = Http::timeout(25)->get($url);

                        if ($response->failed()) {
                            continue;
                        }

                        $results = $response->json('results') ?? [];
                        $totalFoundAll += count($results);

                        foreach ($results as $job) {

                            $area = $job['location']['area'] ?? [];
                            $city = $area[1] ?? ($area[0] ?? null);
                            $countryCode = strtolower($area[0] ?? $country);

                            [$city, $lat, $lng] = $this->getCoordsFromCountry($city, $countryCode);

                            if (!$lat || !$lng) {
                                if (isset($this->capitalMap[$countryCode])) {
                                    $cap = $this->capitalMap[$countryCode];
                                    $city = $cap['city'];
                                    $lat = $cap['lat'];
                                    $lng = $cap['lng'];
                                } else {
                                    continue;
                                }
                            }

                            JobOffer::updateOrCreate(
                                [
                                    'external_id' => $job['id'],
                                ],
                                [
                                    'title' => $job['title'] ?? 'N/A',
                                    'company' => $job['company']['display_name'] ?? null,
                                    'country' => strtoupper($countryCode),
                                    'city' => $city,
                                    'latitude' => $lat,
                                    'longitude' => $lng,
                                    'modality' => 'no_precisa',
                                    'requirements' => strip_tags($job['description'] ?? null),
                                    'source' => 'Adzuna',
                                    'url' => $job['redirect_url'] ?? null,
                                    'search_query' => $searchQuery,
                                    'seniority' => $this->detectSeniority($job['title'] ?? '', $job['description'] ?? '', $seniorityValue), // 🔥 ESTE ES EL CAMPO QUE USA TU DASHBOARD
                                    'role_name' => $role,
                                    'published_at' => isset($job['created'])
                                        ? Carbon::parse($job['created'])
                                        : now(),
                                    'region' => RegionHelper::fromCountry(strtoupper($countryCode)),
                                ]
                            );

                            $totalProcessedAll++;
                        }

                        sleep(1);
                    }
                }
            }

            ScraperRunService::success(
                $run,
                $totalFoundAll,
                $totalProcessedAll,
                0
            );

        } catch (\Throwable $e) {
            ScraperRunService::failed($run, $e);
            throw $e;
        }
    }

    protected function getCoordsFromCountry(?string $city, ?string $countryCode)
    {
        if (!$city) return [null, null, null];

        $foundCity = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])
            ->when($countryCode, fn($q) => $q->whereRaw('LOWER(iso2) = ?', [strtolower($countryCode)]))
            ->first();

        if ($foundCity) {
            return [$foundCity->city, $foundCity->lat, $foundCity->lng];
        }

        return [null, null, null];
    }
    protected function detectSeniority(string $title, string $description, string $fallback): string
{
    $text = strtolower($title . ' ' . $description);

    // 🔎 1️⃣ Detectar por años
    if (preg_match('/(\d+)\+?\s*(years|year|años|año)/', $text, $matches)) {

        $years = (int) $matches[1];

        if ($years <= 2) {
            return 'junior';
        }

        if ($years <= 5) {
            return 'mid';
        }

        if ($years >= 6) {
            return 'senior';
        }
    }

    // 🔎 2️⃣ Detectar por título
    if (str_contains($text, 'junior') || str_contains($text, 'jr')) {
        return 'junior';
    }

    if (str_contains($text, 'mid') || str_contains($text, 'semi senior')) {
        return 'mid';
    }

    if (
        str_contains($text, 'senior') ||
        str_contains($text, 'sr') ||
        str_contains($text, 'lead') ||
        str_contains($text, 'principal') ||
        str_contains($text, 'director')
    ) {
        return 'senior';
    }

    // 🔎 3️⃣ Fallback a la query
    return $fallback ?? 'unspecified';
}
}
