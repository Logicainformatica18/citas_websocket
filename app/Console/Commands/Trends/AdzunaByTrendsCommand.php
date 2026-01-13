<?php

namespace App\Console\Commands\Trends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\TechnologyTrend;
use App\Models\TrendMarketSignal;
use App\Models\City;
use Carbon\Carbon;
use App\Helpers\RegionHelper;

class AdzunaByTrendsCommand extends Command
{
    protected $signature = 'adzuna:trends 
        {--country=us} 
        {--pages=1}
        {--year=} 
        {--quarter=}';

    protected $description = '📡 Rastrea ofertas laborales desde Adzuna usando TechnologyTrends como semilla y guarda señales de mercado';

    protected $stats = [
        'api_hits' => 0,
        'fallback' => 0,
        'mapped' => 0,
        'skipped' => 0,
    ];

    protected $capitalMap = [
        'au' => ['city' => 'Sídney', 'lat' => -33.8688, 'lng' => 151.2093],
        'nz' => ['city' => 'Wellington', 'lat' => -41.2865, 'lng' => 174.7762],

        'us' => ['city' => 'Washington D.C.', 'lat' => 38.8951, 'lng' => -77.0364],
        'ca' => ['city' => 'Ottawa', 'lat' => 45.4215, 'lng' => -75.6997],
        'mx' => ['city' => 'Ciudad de México', 'lat' => 19.4326, 'lng' => -99.1332],
        'br' => ['city' => 'Brasilia', 'lat' => -15.7939, 'lng' => -47.8828],

        'es' => ['city' => 'Madrid', 'lat' => 40.4168, 'lng' => -3.7038],
        'fr' => ['city' => 'París', 'lat' => 48.8566, 'lng' => 2.3522],
        'de' => ['city' => 'Berlín', 'lat' => 52.5200, 'lng' => 13.4050],
        'it' => ['city' => 'Roma', 'lat' => 41.9028, 'lng' => 12.4964],
        'gb' => ['city' => 'Londres', 'lat' => 51.5074, 'lng' => -0.1278],
        'nl' => ['city' => 'Ámsterdam', 'lat' => 52.3676, 'lng' => 4.9041],
        'be' => ['city' => 'Bruselas', 'lat' => 50.8503, 'lng' => 4.3517],
        'ch' => ['city' => 'Berna', 'lat' => 46.9480, 'lng' => 7.4474],
        'pl' => ['city' => 'Varsovia', 'lat' => 52.2297, 'lng' => 21.0122],

        'in' => ['city' => 'Nueva Delhi', 'lat' => 28.6139, 'lng' => 77.2090],
        'sg' => ['city' => 'Singapur', 'lat' => 1.3521, 'lng' => 103.8198],

        'za' => ['city' => 'Pretoria', 'lat' => -25.7461, 'lng' => 28.1881],
    ];

    public function handle()
    {
        $country = strtolower($this->option('country'));
        $pages = (int) $this->option('pages');
        $year = $this->option('year') ?? now()->year;
        $quarter = $this->option('quarter') ?? ceil(now()->month / 3);

        $topics = TechnologyTrend::where('year', $year)
            ->where('quarter', $quarter)
            ->get();

        $appId = config('services.adzuna.app_id');
        $appKey = config('services.adzuna.app_key');
        $baseUrl = config('services.adzuna.base_url', 'https://api.adzuna.com/v1/api/jobs');

        $this->info("📡 Iniciando scan Adzuna para {$topics->count()} tendencias");

        foreach ($topics as $topic) {

            $this->warn("\n🔍 Trend: {$topic->topic_name}");

            $totalFound = 0;
            $regions = [];

            for ($page = 1; $page <= $pages; $page++) {

                $keywords = [];

                // 🔑 Prioridad: keywords escaneadas por GPT-5 Search
                if (!empty($topic->scanned_keywords)) {
                    $decoded = json_decode($topic->scanned_keywords, true);
                    if (is_array($decoded)) {
                        $keywords = $decoded;
                    }
                }

                // 🔁 Fallback si aún no tiene keywords
                if (empty($keywords)) {
                    $keywords = [$topic->topic_name];
                }

                foreach ($keywords as $keyword) {

                    $url = "{$baseUrl}/{$country}/search/{$page}"
                        . "?app_id={$appId}&app_key={$appKey}"
                        . "&results_per_page=100"
                        . "&what=" . urlencode($keyword);

                    try {
                        $response = Http::timeout(25)->get($url);
                        if ($response->failed()) {
                            continue;
                        }

                        $this->stats['api_hits']++;

                        $results = $response->json('results') ?? [];
                        $totalFound += count($results);

                        foreach ($results as $job) {

                            $area = $job['location']['area'] ?? [];
                            $city = $area[1] ?? ($area[0] ?? null);
                            $countryName = $area[0] ?? $country;

                            $countryCode = strtoupper($countryName);
                            $countryFull = ucfirst(strtolower($countryName));

                            [$city, $lat, $lng] =
                                $this->getCoordsFromCountry($city, strtolower($countryCode));

                            if (!$lat || !$lng) {
                                if (isset($this->capitalMap[strtolower($countryCode)])) {
                                    $cap = $this->capitalMap[strtolower($countryCode)];
                                    $city = $cap['city'];
                                    $lat = $cap['lat'];
                                    $lng = $cap['lng'];
                                    $this->stats['fallback']++;
                                } else {
                                    $this->stats['skipped']++;
                                    continue;
                                }
                            }

                            $region = RegionHelper::fromCountry($countryFull);

                            $regions[$region] =
                                ($regions[$region] ?? 0) + 1;
                        }

                        sleep(1);

                    } catch (\Throwable $e) {
                        Log::error("Trend {$topic->topic_name} / {$keyword}: {$e->getMessage()}");
                    }
                }

                try {
                    $response = Http::timeout(25)->get($url);
                    if ($response->failed()) {
                        continue;
                    }

                    $this->stats['api_hits']++;

                    $results = $response->json('results') ?? [];
                    $totalFound += count($results);

                    foreach ($results as $job) {

                        $area = $job['location']['area'] ?? [];
                        $city = $area[1] ?? ($area[0] ?? null);
                        $countryName = $area[0] ?? $country;

                        $countryCode = strtoupper($countryName);
                        $countryFull = ucfirst(strtolower($countryName));

                        [$city, $lat, $lng] =
                            $this->getCoordsFromCountry($city, strtolower($countryCode));

                        if (!$lat || !$lng) {
                            if (isset($this->capitalMap[strtolower($countryCode)])) {
                                $cap = $this->capitalMap[strtolower($countryCode)];
                                $city = $cap['city'];
                                $lat = $cap['lat'];
                                $lng = $cap['lng'];
                                $this->stats['fallback']++;
                            } else {
                                $this->stats['skipped']++;
                                continue;
                            }
                        }

                        $region = RegionHelper::fromCountry($countryFull);

                        $regions[$region] =
                            ($regions[$region] ?? 0) + 1;
                    }

                    sleep(1);

                } catch (\Throwable $e) {
                    Log::error("Trend {$topic->topic_name}: {$e->getMessage()}");
                }
            }

            TrendMarketSignal::updateOrCreate(
                [
                    'topic_id' => $topic->id,
                    'topic_type' => $this->resolveType($topic->topic_category),
                    'year' => $year,
                    'quarter' => $quarter,
                ],
                [
                    'topic_name' => $topic->topic_name,
                    'topic_category' => $topic->topic_category,
                    'job_offer_count' => $totalFound,
                    'regions' => array_keys($regions),
                    'signal_strength' => $this->signalScore($totalFound, $topic->trend_score),
                    'last_scanned_at' => now(),
                ]
            );

            $this->info("✅ {$topic->topic_name}: {$totalFound} ofertas");
        }

        $this->info("\n🎯 Scan de tendencias completado");
    }

    /* ================= HELPERS ================= */

    protected function resolveType(string $category): string
    {
        return match (true) {
            str_contains(strtolower($category), 'certificacion') => 'certification',
            str_contains(strtolower($category), 'lenguaje') => 'language',
            str_contains(strtolower($category), 'tecnolog') => 'technology',
            str_contains(strtolower($category), 'skill') => 'skill',
            default => 'technology',
        };
    }

    protected function signalScore(int $jobs, int $trendScore): float
    {
        return round(
            ($trendScore * 0.6) + (log($jobs + 1) * 10 * 0.4),
            2
        );
    }

    protected function getCoordsFromCountry(?string $city, ?string $countryCode)
    {
        if ($city && strtolower($city) !== 'remote') {
            $foundCity = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])
                ->when(
                    $countryCode,
                    fn($q) =>
                    $q->whereRaw('LOWER(iso2) = ?', [$countryCode])
                )
                ->first();

            if ($foundCity) {
                $this->stats['mapped']++;
                return [$foundCity->city, $foundCity->lat, $foundCity->lng];
            }
        }

        return [$city, null, null];
    }
}
