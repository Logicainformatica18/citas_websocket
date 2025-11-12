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
    // 🔹 Trae solo metodologías realmente vinculadas a carreras
    $methodologies = Methodology::select('methodologies.id', 'methodologies.name')
        ->whereIn('methodologies.id', function ($q) {
            $q->select('course_methodology.methodology_id')
                ->from('course_methodology')
                ->join('career_course', 'career_course.course_id', '=', 'course_methodology.course_id');
        })
        ->get();

    $this->info("🌐 Iniciando scraping de Arbeitnow por metodología ({$methodologies->count()} metodologías)...");

    foreach ($methodologies as $methodology) {
        $methodologyId = $methodology->id;
        $methodologyName = $methodology->name;

        $this->warn("\n💡 Procesando metodología: {$methodologyName}");

        $totalFound = 0;
        $totalNew = 0;
        $countries = [];
        $modalities = [];

        try {
            // 🌍 Llamado directo con el nombre de la metodología
            $response = Http::timeout(25)->get('https://www.arbeitnow.com/api/job-board-api', [
                'search' => $methodologyName,
            ]);

            if ($response->failed()) {
                $this->error("❌ Falló la API para {$methodologyName}");
                continue;
            }

            $jobs = $response->json()['data'] ?? [];
            $totalFound = count($jobs);

            // ⚙️ Si la API no devuelve nada, usar fallback filtrando manualmente
            if ($totalFound === 0) {
                $backup = Http::timeout(25)->get('https://www.arbeitnow.com/api/job-board-api');

                if ($backup->ok()) {
                    $allJobs = $backup->json()['data'] ?? [];

                    $jobs = collect($allJobs)
                        ->filter(function ($job) use ($methodologyName) {
                            $text = strtolower(
                                html_entity_decode(
                                    strip_tags(($job['title'] ?? '') . ' ' . ($job['description'] ?? ''))
                                )
                            );
                            $needle = strtolower($methodologyName);
                            $needle = preg_quote($needle, '/');
                            return preg_match("/\\b{$needle}\\b/i", $text);
                        })
                        ->values()
                        ->all();

                    $this->stats['fallback'] += count($jobs);
                    $totalFound = count($jobs);
                }
            }

            if ($totalFound === 0) {
                $this->warn("⚠️ Sin resultados para {$methodologyName}");
                continue;
            }

            // 🧩 Procesar cada oferta
            foreach ($jobs as $job) {
                $title = $job['title'] ?? 'N/A';
                $company = $job['company_name'] ?? null;
                $location = trim($job['location'] ?? '');
                $description = $job['description'] ?? '';
                $urlJob = $job['url'] ?? null;
                $isRemote = $job['remote'] ?? false;

                // 🗺️ País y coordenadas
                $countryCode = $this->detectCountryCode($location, $isRemote);
                [$city, $lat, $lng, $country] = $this->getCoordsFromCountry($this->extractCity($location), $countryCode);

                // Fallback capital
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

                // Duplicados
                $externalId = $job['slug'] ?? md5($urlJob ?? uniqid('arbeitnow_'));
               $existingOffer = JobOffer::where('source', 'Arbeitnow')
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
    ->first();

if ($existingOffer) {
    // 🧩 Si ya existe, asociar la metodología si aún no lo está
    $existingOffer->methodologies()->syncWithoutDetaching([$methodologyId]);
    continue; // ❌ No crear una nueva
}


                // 💾 Guardar
                $offer = JobOffer::create([
                    'title'        => $title,
                    'company'      => $company,
                    'country'      => $country ?? 'Unknown',
                    'city'         => $city,
                    'latitude'     => $lat,
                    'longitude'    => $lng,
                    'modality'     => $this->extractModality($location, $isRemote),
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

                // 🔗 Relación metodología ↔ oferta
                $offer->methodologies()->syncWithoutDetaching([$methodologyId]);

                // Contadores
                $totalNew++;
                $countries[$country ?? 'Unknown'] = ($countries[$country ?? 'Unknown'] ?? 0) + 1;
                $modality = $this->extractModality($location, $isRemote);
                $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;
            }

            // 📊 Guardar métricas si aún no existen hoy
            $today = now()->toDateString();
            $existsToday = MethodologyMetric::whereDate('run_date', $today)
                ->where('methodology_id', $methodologyId)
                ->where('source', 'Arbeitnow')
                ->exists();

            if (!$existsToday) {
                MethodologyMetric::create([
                    'methodology_id'       => $methodologyId,
                    'methodology_name'     => $methodologyName,
                    'jobs_found_count'     => $totalFound,
                    'jobs_new_count'       => $totalNew,
                    'countries_breakdown'  => $countries,
                    'modality_breakdown'   => $modalities,
                    'run_date'             => Carbon::today(),
                    'source'               => 'Arbeitnow',
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

        // 🏠 Híbrido  aspera
        str_contains($loc, 'hybrid'),
        str_contains($loc, 'híbrido') => 'hybrid',

        // 🏢 Presencial o sin indicio de remoto
        default => 'no_remote',
    };
}
protected function extractCity(?string $location): ?string
{
    if (empty($location)) return null;
    $parts = explode(',', $location);
    return trim($parts[0]); // Ejemplo: "Berlin, Germany" → "Berlin"
}
protected function detectCountryCode($location, $isRemote)
{
    $loc = strtolower($location ?? '');
    if ($isRemote) return 'remote';

    return match (true) {
        str_contains($loc, 'germany') || str_contains($loc, 'deutschland') || str_contains($loc, 'berlin') => 'de',
        str_contains($loc, 'spain') || str_contains($loc, 'espa') || str_contains($loc, 'madrid') => 'es',
        str_contains($loc, 'france') || str_contains($loc, 'paris') => 'fr',
        str_contains($loc, 'portugal') || str_contains($loc, 'lisbon') => 'pt',
        str_contains($loc, 'italy') || str_contains($loc, 'rome') => 'it',
        str_contains($loc, 'netherlands') || str_contains($loc, 'amsterdam') => 'nl',
        str_contains($loc, 'austria') || str_contains($loc, 'vienna') => 'at',
        str_contains($loc, 'poland') || str_contains($loc, 'warsaw') => 'pl',
        str_contains($loc, 'uk') || str_contains($loc, 'united kingdom') || str_contains($loc, 'london') => 'gb',
        str_contains($loc, 'ireland') || str_contains($loc, 'dublin') => 'ie',
        str_contains($loc, 'switzerland') || str_contains($loc, 'zurich') => 'ch',
        str_contains($loc, 'sweden') => 'se',
        str_contains($loc, 'norway') => 'no',
        str_contains($loc, 'denmark') => 'dk',
        str_contains($loc, 'finland') => 'fi',
        // Asia
        str_contains($loc, 'japan') => 'jp',
        str_contains($loc, 'india') => 'in',
        str_contains($loc, 'singapore') => 'sg',
        str_contains($loc, 'korea') => 'kr',
        str_contains($loc, 'china') => 'cn',
        str_contains($loc, 'philippines') => 'ph',
        str_contains($loc, 'vietnam') => 'vn',
        str_contains($loc, 'thailand') => 'th',
        str_contains($loc, 'indonesia') => 'id',
        str_contains($loc, 'malaysia') => 'my',
        str_contains($loc, 'taiwan') => 'tw',
        str_contains($loc, 'hong kong') => 'hk',
        default => null,
    };
}

}
