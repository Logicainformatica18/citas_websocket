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

class ArbeitnowByMethodologiesCommand extends Command
{
    protected $signature = 'arbeitnow:methodologies';
    protected $description = '🌍 Recorre todas las metodologías y registra métricas de demanda laboral desde Arbeitnow (Europa/Asia) con país y geolocalización.';

    protected $stats = ['api_hits' => 0, 'fallback' => 0, 'mapped' => 0];
    protected $geoCache = [];

    // 🌎 Capitales por país (fallback si no hay city o lat/lng)
    protected $capitalMap = [
        'de' => ['city' => 'Berlín', 'lat' => 52.5200, 'lng' => 13.4050, 'country' => 'Germany'],
        'es' => ['city' => 'Madrid', 'lat' => 40.4168, 'lng' => -3.7038, 'country' => 'Spain'],
        'fr' => ['city' => 'París', 'lat' => 48.8566, 'lng' => 2.3522, 'country' => 'France'],
        'pt' => ['city' => 'Lisboa', 'lat' => 38.7169, 'lng' => -9.1399, 'country' => 'Portugal'],
        'it' => ['city' => 'Roma', 'lat' => 41.9028, 'lng' => 12.4964, 'country' => 'Italy'],
        'nl' => ['city' => 'Ámsterdam', 'lat' => 52.3676, 'lng' => 4.9041, 'country' => 'Netherlands'],
        'at' => ['city' => 'Viena', 'lat' => 48.2082, 'lng' => 16.3738, 'country' => 'Austria'],
        'pl' => ['city' => 'Varsovia', 'lat' => 52.2297, 'lng' => 21.0122, 'country' => 'Poland'],
        'ch' => ['city' => 'Berna', 'lat' => 46.9480, 'lng' => 7.4474, 'country' => 'Switzerland'],
        'gb' => ['city' => 'Londres', 'lat' => 51.5074, 'lng' => -0.1278, 'country' => 'United Kingdom'],
        'ie' => ['city' => 'Dublín', 'lat' => 53.3498, 'lng' => -6.2603, 'country' => 'Ireland'],
        'remote' => ['city' => 'Remoto', 'lat' => 0.0000, 'lng' => 0.0000, 'country' => 'Remote'],
    ];

    public function handle()
    {
        $methodologies = Methodology::pluck('name', 'id');
        $this->info("🌐 Iniciando scraping de Arbeitnow por metodología ({$methodologies->count()} metodologías)...");

        foreach ($methodologies as $methodologyId => $methodologyName) {
            $this->warn("\n💡 Procesando metodología: {$methodologyName}");

            $totalFound = 0;
            $totalNew = 0;
            $countries = [];
            $modalities = [];

            try {
                $response = Http::timeout(25)->get('https://www.arbeitnow.com/api/job-board-api', [
                    'search' => $methodologyName,
                ]);

                if ($response->failed()) {
                    $this->error("❌ Falló la API para {$methodologyName}");
                    continue;
                }

                $jobs = $response->json()['data'] ?? $response->json() ?? [];
                $totalFound = count($jobs);

                foreach ($jobs as $job) {
                    $title = $job['title'] ?? 'N/A';
                    $company = $job['company_name'] ?? null;
                    $location = trim($job['location'] ?? '');
                    $isRemote = $job['remote'] ?? false;
                    $urlJob = $job['url'] ?? null;

                    $countryCode = $this->detectCountryCode($location, $isRemote);
                    [$city, $lat, $lng, $country] = $this->getCoordsFromCountry($location, $countryCode);

                    // 🧭 Fallback si no hay coordenadas
                    if (!$lat || !$lng) {
                        if (isset($this->capitalMap[$countryCode])) {
                            $capital = $this->capitalMap[$countryCode];
                            $city = $capital['city'];
                            $lat = $capital['lat'];
                            $lng = $capital['lng'];
                            $country = $capital['country'];
                            $this->stats['fallback']++;
                        } else {
                            continue;
                        }
                    }

                    $modality = $this->extractModality($location, $isRemote);
                    $countries[$country ?? 'Unknown'] = ($countries[$country ?? 'Unknown'] ?? 0) + 1;
                    $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;

                    $externalId = $job['slug'] ?? md5($urlJob ?? uniqid('arbeitnow_'));

                    // 🔁 Evitar duplicados reales (país por nombre completo)
                    $exists = JobOffer::where('source', 'Arbeitnow')
                        ->where(function ($q) use ($externalId, $title, $company, $country, $methodologyName, $urlJob) {
                            $q->where('external_id', $externalId)
                              ->orWhere(function ($q2) use ($title, $company, $country, $methodologyName, $urlJob) {
                                  $q2->where('title', $title)
                                     ->where('company', $company)
                                     ->where('country', $country ?? 'Unknown')
                                     ->where('search_query', $methodologyName)
                                     ->where(function ($q3) use ($urlJob) {
                                         $q3->where('url', $urlJob)
                                            ->orWhere('url', 'like', '%' . substr($urlJob, -25) . '%');
                                     });
                              });
                        })
                        ->exists();

                    if ($exists) continue;

                    // 💾 Guardar oferta
                    JobOffer::create([
                        'title'        => $title,
                        'company'      => $company,
                        'country'      => $country ?? 'Unknown',
                        'city'         => $city,
                        'latitude'     => $lat,
                        'longitude'    => $lng,
                        'modality'     => $modality,
                        'source'       => 'Arbeitnow',
                        'external_id'  => $externalId,
                        'url'          => $urlJob,
                        'currency'     => 'EUR',
                        'salary_min'   => null,
                        'salary_max'   => null,
                        'search_query' => $methodologyName,
                        'published_at' => isset($job['date']) ? Carbon::parse($job['date']) : now(),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);

                    $totalNew++;
                }

                // 📊 Evitar duplicar métricas diarias
                $today = now()->toDateString();
                $existsToday = MethodologyMetric::whereDate('run_date', $today)
                    ->where('methodology_id', $methodologyId)
                    ->where('source', 'Arbeitnow')
                    ->exists();

                if (!$existsToday) {
                    MethodologyMetric::create([
                        'methodology_id' => $methodologyId,
                        'methodology_name' => $methodologyName,
                        'jobs_found_count' => $totalFound,
                        'jobs_new_count' => $totalNew,
                        'countries_breakdown' => $countries,
                        'modality_breakdown' => $modalities,
                        'run_date' => Carbon::today(),
                        'source' => 'Arbeitnow',
                    ]);
                }

                $this->info("✅ {$methodologyName}: {$totalNew} nuevas | 🌍 {$totalFound} encontradas");
            } catch (\Throwable $th) {
                Log::error("⚠️ Error en {$methodologyName}: " . $th->getMessage());
            }

            sleep(1.5);
        }

        $this->info("\n🎯 Proceso completado: métricas registradas en `methodology_metrics`.");
    }

    // 🌍 Identifica país por patrones
    protected function detectCountryCode($location, $isRemote)
    {
        $loc = strtolower($location ?? '');
        if ($isRemote) return 'remote';

        return match (true) {
            str_contains($loc, 'germany') || str_contains($loc, 'berlin') || str_contains($loc, 'deutschland') => 'de',
            str_contains($loc, 'spain') || str_contains($loc, 'madrid') || str_contains($loc, 'barcelona') => 'es',
            str_contains($loc, 'france') || str_contains($loc, 'paris') => 'fr',
            str_contains($loc, 'portugal') || str_contains($loc, 'lisbon') => 'pt',
            str_contains($loc, 'italy') || str_contains($loc, 'rome') => 'it',
            str_contains($loc, 'netherlands') || str_contains($loc, 'amsterdam') => 'nl',
            str_contains($loc, 'austria') || str_contains($loc, 'vienna') => 'at',
            str_contains($loc, 'poland') || str_contains($loc, 'warsaw') => 'pl',
            str_contains($loc, 'switzerland') || str_contains($loc, 'zurich') => 'ch',
            str_contains($loc, 'uk') || str_contains($loc, 'london') || str_contains($loc, 'united kingdom') => 'gb',
            str_contains($loc, 'ireland') || str_contains($loc, 'dublin') => 'ie',
            default => null,
        };
    }

    protected function getCoordsFromCountry(?string $city, ?string $countryHint)
    {
        if ($city && strtolower($city) !== 'remote') {
            $cityNorm = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $city));

            $found = City::whereRaw('LOWER(city_ascii) = ?', [$cityNorm])
                ->when($countryHint, fn($q) => $q->whereRaw('LOWER(iso2) = ?', [strtolower($countryHint)]))
                ->first();

            if ($found) {
                $this->stats['mapped']++;
                return [$found->city, $found->lat, $found->lng, $found->country ?? strtoupper($found->iso2)];
            }

            [$lat, $lng, $countryName] = $this->getCoords($city, $countryHint);
            if ($lat && $lng) {
                $this->stats['api_hits']++;
                return [$city, $lat, $lng, $countryName];
            }
        }

        return [$city, null, null, $countryHint];
    }

    protected function getCoords($city, $country)
    {
        $key = strtolower(trim("{$city},{$country}"));
        if (isset($this->geoCache[$key])) return $this->geoCache[$key];

        try {
            $res = Http::timeout(10)->get('https://nominatim.openstreetmap.org/search', [
                'q' => "{$city}" . ($country ? ", {$country}" : ''),
                'format' => 'json',
                'addressdetails' => 1,
                'limit' => 1,
            ]);

            if ($res->ok() && count($res->json()) > 0) {
                $data = $res->json()[0];
                $countryName = $data['address']['country'] ?? null;
                return $this->geoCache[$key] = [(float) $data['lat'], (float) $data['lon'], $countryName];
            }
        } catch (\Throwable $e) {
            Log::warning("🌍 Error geocodificando {$city}, {$country}: " . $e->getMessage());
        }

        return $this->geoCache[$key] = [null, null, null];
    }

   protected function extractModality(string $location, bool $isRemote): string
{
    $loc = strtolower($location);

    return match (true) {
        // 🌎 100% remoto (sin restricción de país)
        $isRemote,
        str_contains($loc, 'remote'),
        str_contains($loc, 'anywhere'),
        str_contains($loc, 'work from home'),
        str_contains($loc, 'home office') => 'remote',

        // 🏠 Híbrido
        str_contains($loc, 'hybrid'),
        str_contains($loc, 'híbrido') => 'hybrid',

        // 🏢 Presencial o sin indicio de remoto
        default => 'no_remote',
    };
}

}
