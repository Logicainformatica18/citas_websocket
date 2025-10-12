<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\JobOffer;
use Carbon\Carbon;
use App\Models\City; // 👈 agrega esta línea arriba
class AdzunaImportCommand extends Command
{
    protected $signature = 'adzuna:import {--query=developer} {--country=us} {--pages=1}';
    protected $description = '🌐 Importa ofertas laborales desde Adzuna, con geolocalización automática y fallback por capital.';

    protected $stats = [
        'api_hits' => 0,
        'fallback' => 0,
        'mapped' => 0,
    ];

 protected $capitalMap = [
    // 🌏 Oceanía
    'au' => ['city' => 'Sídney', 'lat' => -33.8688, 'lng' => 151.2093],
    'nz' => ['city' => 'Wellington', 'lat' => -41.2865, 'lng' => 174.7762],

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

    // 🌍 África
    'za' => ['city' => 'Pretoria', 'lat' => -25.7461, 'lng' => 28.1881],
];


    public function handle()
    {
        $query   = strtolower($this->option('query'));
        $country = strtolower($this->option('country'));
        $pages   = (int) $this->option('pages');

        $appId   = config('services.adzuna.app_id');
        $appKey  = config('services.adzuna.app_key');
        $baseUrl = config('services.adzuna.base_url', 'https://api.adzuna.com/v1/api/jobs');

        $this->info("🌍 Importando desde Adzuna ({$country}) para '{$query}' ({$pages} página/s)");

        $totalSaved = $totalDuplicates = $totalUnmapped = 0;

        for ($page = 1; $page <= $pages; $page++) {
            $url = "{$baseUrl}/{$country}/search/{$page}?app_id={$appId}&app_key={$appKey}&results_per_page=100&what=" . urlencode($query);

            try {
                $response = Http::timeout(25)->get($url);
                if ($response->failed()) {
                    $this->error("❌ Error al consultar la API (página {$page})");
                    continue;
                }

                $results = $response->json('results') ?? [];
                if (empty($results)) {
                    $this->warn("⚠️ Página {$page} sin resultados");
                    continue;
                }

                $saved = $duplicates = $unmapped = 0;

                foreach ($results as $job) {
                    $title   = $job['title'] ?? 'N/A';
                    $company = $job['company']['display_name'] ?? null;
                    $urlJob  = $job['redirect_url'] ?? null;

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
        $this->stats['fallback']++;
        $this->warn("⚠️ {$countryCode}: sin coordenadas válidas, usando capital {$city}");
    } else {
        $this->warn("⚠️ {$countryCode}: sin coordenadas ni capital conocida, usando Lima");
        $city = 'Lima';
        $latitude = -12.0464;
        $longitude = -77.0428;
        $this->stats['fallback']++;
    }
}



                 if (!empty($job['id']) && JobOffer::where('external_id', $job['id'])->exists()) {
    $duplicates++;
    continue;
}


                    JobOffer::create([
                        'title'        => $title,
                        'company'      => $company,
                        'country'      => strtoupper($countryCode),
                        'city'         => $city,
                        'latitude'     => $latitude,
                        'longitude'    => $longitude,
                        'modality'     => null,
                        'salary_min'   => $job['salary_min'] ?? null,
                        'salary_max'   => $job['salary_max'] ?? null,
                        'currency'     => $job['salary_currency'] ?? 'USD',
                        'source'       => 'Adzuna',
                        'external_id'  => $job['id'] ?? null,
                        'url'          => $urlJob,
                        'search_query' => $query,
                        'published_at' => isset($job['created']) ? Carbon::parse($job['created']) : now(),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);

                    $saved++;
                }

                $this->line("📄 Página {$page}: ✅ {$saved} nuevas | 🔁 {$duplicates} duplicadas | ⚠️ {$unmapped} sin coordenadas");
                $totalSaved += $saved;
                $totalDuplicates += $duplicates;
                $totalUnmapped += $unmapped;

                sleep(1.5);
            } catch (\Throwable $e) {
                $this->error("⚠️ Error en página {$page}: " . $e->getMessage());
                Log::error("Adzuna API error (página {$page}): " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("🎯 Importación finalizada para '{$query}':");
        $this->line("   ✅ {$totalSaved} guardadas");
        $this->line("   🔁 {$totalDuplicates} duplicadas");
        $this->line("   ⚠️ {$totalUnmapped} sin coordenadas");
        $this->line("   📊 Asignadas desde capitales: {$this->stats['fallback']}");
    }

protected function getCoordsFromCountry(?string $city, ?string $countryCode)
{
    // ⚙️ 1️⃣ Si existe ciudad válida, buscar en tabla cities
    if ($city && strtolower($city) !== 'remote') {

        // 🔍 Buscar ciudad normalizada en BD
        $foundCity = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])
            ->when($countryCode, fn($q) => $q->whereRaw('LOWER(iso2) = ?', [strtolower($countryCode)]))
            ->first();

        if ($foundCity) {
            $this->stats['mapped']++;
            $this->line("🏙️ {$countryCode} → {$foundCity->city} (DB: {$foundCity->lat}, {$foundCity->lng})");
            return [$foundCity->city, $foundCity->lat, $foundCity->lng];
        }

        // ⚙️ 2️⃣ Si no está en BD, intentar con Nominatim (API)
        [$lat, $lng] = $this->getCoords($city, $countryCode);
        if ($lat && $lng) {
            $this->stats['api_hits']++;
            $this->line("🛰️ {$countryCode} → {$city} (API: {$lat}, {$lng})");
            return [$city, $lat, $lng];
        }
    }

    // ⚠️ Si llega aquí, devolver valores nulos (para que el caller decida el fallback)
    return [$city, null, null];
}


    protected function getCoords(?string $city, ?string $country)
    {
        try {
            $res = Http::withHeaders([
                'User-Agent' => 'LaravelJobScraper/1.0'
            ])->timeout(10)->get('https://nominatim.openstreetmap.org/search', [
                'q' => "{$city}, {$country}",
                'format' => 'json',
                'limit' => 1,
            ]);

            if ($res->ok() && count($res->json()) > 0) {
                $data = $res->json()[0];
                $this->stats['api_hits']++;
                return [(float) $data['lat'], (float) $data['lon']];
            }
        } catch (\Throwable $th) {
            Log::warning("🌍 Error geocodificando {$city}, {$country}: " . $th->getMessage());
        }

        return [null, null];
    }
}
