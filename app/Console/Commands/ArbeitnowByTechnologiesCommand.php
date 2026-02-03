<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Technology;
use App\Models\JobOffer;
use App\Models\TechnologyMetric;
use App\Models\City;
use Carbon\Carbon;
use App\Helpers\RegionHelper;
use App\Services\ScraperRunService;

class ArbeitnowByTechnologiesCommand extends Command
{
    protected $signature = 'arbeitnow:technologies';
    protected $description = '🌍 Recorre todas las tecnologías y registra métricas de demanda laboral desde Arbeitnow (Europa/Asia) con país y geolocalización.';

    protected $stats = ['api_hits' => 0, 'fallback' => 0, 'mapped' => 0];
    protected $geoCache = [];

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
    // 🧾 Iniciar tracking del scraper
    $run = ScraperRunService::start(
        $this->signature, // arbeitnow:technologies (ejemplo)
        'Arbeitnow',
        'technologies'
    );

    // 📊 Contadores globales
    $totalFoundAll   = 0;
    $totalInsertedAll = 0;
    $totalSkippedAll  = 0;

    $technologies = Technology::select('technologies.id', 'technologies.name')
        ->whereIn('technologies.id', function ($q) {
            $q->select('course_technology.technology_id')
                ->from('course_technology')
                ->join('career_course', 'career_course.course_id', '=', 'course_technology.course_id');
        })
        ->pluck('name', 'id');

    $this->info("🌐 Iniciando scraping de Arbeitnow por tecnología ({$technologies->count()} tecnologías)...");

    try {
        foreach ($technologies as $techId => $techName) {
            $this->warn("\n💡 Procesando tecnología: {$techName}");

            $totalFound = 0;
            $totalNew = 0;
            $countries = [];
            $modalities = [];

            $response = Http::timeout(25)->get(
                'https://www.arbeitnow.com/api/job-board-api',
                ['search' => $techName]
            );

            if ($response->failed()) {
                $this->error("❌ Falló la API para {$techName}");
                continue;
            }

            $jobs = $response->json()['data'] ?? [];
            $totalFound = count($jobs);
            $totalFoundAll += $totalFound;

            foreach ($jobs as $job) {
                $title     = $job['title'] ?? 'N/A';
                $company   = $job['company_name'] ?? null;
                $location  = trim($job['location'] ?? '');
                $isRemote  = $job['remote'] ?? false;
                $urlJob    = $job['url'] ?? null;

                // 🧭 Modalidad (robusta)
                $modality = $this->extractModality($location, $isRemote);
                $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;

                // 🌍 País y coordenadas
                $countryCode = $this->detectCountryCode($location, $isRemote);
                [$city, $lat, $lng, $country] = $this->getCoordsFromCountry($location, $countryCode);

                if (!$lat || !$lng) {
                    if (isset($this->capitalMap[$countryCode])) {
                        $capital = $this->capitalMap[$countryCode];
                        $city = $capital['city'];
                        $lat  = $capital['lat'];
                        $lng  = $capital['lng'];
                        $country = $capital['country'] ?? $country;
                        $this->stats['fallback']++;
                    } else {
                        $totalSkippedAll++;
                        continue;
                    }
                }

                $countries[$country ?? 'Unknown'] =
                    ($countries[$country ?? 'Unknown'] ?? 0) + 1;

                $externalId = $job['slug'] ?? md5($urlJob ?? uniqid('arbeitnow_'));

                // 🔍 Duplicados
                $existingOffer = JobOffer::where('source', 'Arbeitnow')
                    ->where('external_id', $externalId)
                    ->first();

                if ($existingOffer) {
                    $existingOffer->technologies()->syncWithoutDetaching([$techId]);
                    $totalSkippedAll++;
                    continue;
                }

                $region = RegionHelper::fromCountry($country);

                // 💾 Crear oferta
                $offer = JobOffer::create([
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
                    'search_query' => $techName,
                    'published_at' => isset($job['created_at'])
                        ? (is_numeric($job['created_at'])
                            ? Carbon::createFromTimestamp($job['created_at'])
                            : Carbon::parse($job['created_at']))
                        : now(),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                    'region'       => $region,
                ]);

                $offer->technologies()->syncWithoutDetaching([$techId]);

                $totalNew++;
                $totalInsertedAll++;
            }

            // 📊 Métricas diarias
            $today = now()->toDateString();
            if (!TechnologyMetric::whereDate('run_date', $today)
                ->where('technology_id', $techId)
                ->where('source', 'Arbeitnow')
                ->exists()) {

                TechnologyMetric::create([
                    'technology_id'       => $techId,
                    'technology_name'     => $techName,
                    'jobs_found_count'    => $totalFound,
                    'jobs_new_count'      => $totalNew,
                    'countries_breakdown' => $countries,
                    'modality_breakdown'  => $modalities,
                    'run_date'            => Carbon::today(),
                    'source'              => 'Arbeitnow',
                ]);
            }

            $this->info("✅ {$techName}: {$totalNew} nuevas | 🌍 {$totalFound} encontradas");
            sleep(1.5);
        }

        // ✅ Marcar ejecución exitosa (UNA sola vez)
        ScraperRunService::success(
            $run,
            $totalFoundAll,
            $totalInsertedAll,
            $totalSkippedAll
        );

        $this->info("\n🎯 Proceso completado correctamente.");

    } catch (\Throwable $e) {
        // ❌ Fallo global del comando
        ScraperRunService::failed($run, $e);
        throw $e;
    }
}



    // 🔍 Detección del país por texto
    protected function detectCountryCode($location, $isRemote)
    {
        $loc = strtolower($location ?? '');
        if ($isRemote) return 'remote';

        return match (true) {
            str_contains($loc, 'germany') || str_contains($loc, 'berlin') => 'de',
            str_contains($loc, 'spain') || str_contains($loc, 'madrid') => 'es',
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
    $text = strtolower($location);

    /* =========================
       1️⃣ REMOTO EXPLÍCITO
    ========================= */
    if (
        $isRemote ||
        str_contains($text, 'remote') ||
        str_contains($text, 'anywhere') ||
        str_contains($text, 'work from home') ||
        str_contains($text, 'home office') ||
        str_contains($text, 'teletrabajo')
    ) {
        return 'remote';
    }

    /* =========================
       2️⃣ HÍBRIDO
    ========================= */
    if (
        str_contains($text, 'hybrid') ||
        str_contains($text, 'híbrido') ||
        str_contains($text, 'mix') ||
        str_contains($text, 'partial remote')
    ) {
        return 'hybrid';
    }

    /* =========================
       3️⃣ PRESENCIAL EXPLÍCITO
    ========================= */
    if (
        str_contains($text, 'on-site') ||
        str_contains($text, 'onsite') ||
        str_contains($text, 'office') ||
        str_contains($text, 'in office') ||
        str_contains($text, 'presencial') ||
        str_contains($text, 'oficina')
    ) {
        return 'presencial';
    }

    /* =========================
       4️⃣ NO PRECISA
    ========================= */
    return 'no_precisa';
}
}
