<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Language;
use App\Models\JobOffer;
use App\Models\LanguageMetric;
use App\Models\City;
use Carbon\Carbon;
use App\Helpers\RegionHelper;


class ArbeitnowByLanguagesCommand extends Command
{
    protected $signature = 'arbeitnow:languages';
    protected $description = '🌍 Recorre todos los lenguajes y registra métricas de demanda laboral desde Arbeitnow (Europa/Asia), con geolocalización y modalidades estandarizadas.';

    protected $stats = ['api_hits' => 0, 'fallback' => 0, 'mapped' => 0];

    protected $capitalMap = [
        'de' => ['city' => 'Berlín', 'lat' => 52.5200, 'lng' => 13.4050],
        'es' => ['city' => 'Madrid', 'lat' => 40.4168, 'lng' => -3.7038],
        'fr' => ['city' => 'París', 'lat' => 48.8566, 'lng' => 2.3522],
        'pt' => ['city' => 'Lisboa', 'lat' => 38.7169, 'lng' => -9.1399],
        'it' => ['city' => 'Roma', 'lat' => 41.9028, 'lng' => 12.4964],
        'nl' => ['city' => 'Ámsterdam', 'lat' => 52.3676, 'lng' => 4.9041],
        'pl' => ['city' => 'Varsovia', 'lat' => 52.2297, 'lng' => 21.0122],
        'at' => ['city' => 'Viena', 'lat' => 48.2082, 'lng' => 16.3738],
        'gb' => ['city' => 'Londres', 'lat' => 51.5074, 'lng' => -0.1278],
        'ie' => ['city' => 'Dublín', 'lat' => 53.3498, 'lng' => -6.2603],
        'ch' => ['city' => 'Berna', 'lat' => 46.9480, 'lng' => 7.4474],
        'se' => ['city' => 'Estocolmo', 'lat' => 59.3293, 'lng' => 18.0686],
        'dk' => ['city' => 'Copenhague', 'lat' => 55.6761, 'lng' => 12.5683],
        'no' => ['city' => 'Oslo', 'lat' => 59.9139, 'lng' => 10.7522],
        'fi' => ['city' => 'Helsinki', 'lat' => 60.1699, 'lng' => 24.9384],
        // Asia
        'in' => ['city' => 'Nueva Delhi', 'lat' => 28.6139, 'lng' => 77.2090],
        'jp' => ['city' => 'Tokio', 'lat' => 35.6895, 'lng' => 139.6917],
        'sg' => ['city' => 'Singapur', 'lat' => 1.3521, 'lng' => 103.8198],
        'kr' => ['city' => 'Seúl', 'lat' => 37.5665, 'lng' => 126.9780],
        'cn' => ['city' => 'Pekín', 'lat' => 39.9042, 'lng' => 116.4074],
        'ph' => ['city' => 'Manila', 'lat' => 14.5995, 'lng' => 120.9842],
        'vn' => ['city' => 'Hanói', 'lat' => 21.0285, 'lng' => 105.8542],
        'th' => ['city' => 'Bangkok', 'lat' => 13.7563, 'lng' => 100.5018],
        'id' => ['city' => 'Yakarta', 'lat' => -6.2088, 'lng' => 106.8456],
        'my' => ['city' => 'Kuala Lumpur', 'lat' => 3.1390, 'lng' => 101.6869],
        'tw' => ['city' => 'Taipéi', 'lat' => 25.0330, 'lng' => 121.5654],
        'hk' => ['city' => 'Hong Kong', 'lat' => 22.3193, 'lng' => 114.1694],
        // Remoto
        'remote' => ['city' => 'Remoto', 'lat' => 0.0000, 'lng' => 0.0000],
    ];

    protected $geoCache = [];

public function handle()
{
    $languages = Language::select('languages.id', 'languages.name')
        ->whereIn('languages.id', function ($q) {
            $q->select('course_language.language_id')
                ->from('course_language')
                ->join('career_course', 'career_course.course_id', '=', 'course_language.course_id');
        })
        ->get();

    $this->info("🌐 Iniciando scraping de Arbeitnow por lenguaje ({$languages->count()} lenguajes)...");

    foreach ($languages as $language) {
        $languageId = $language->id;
        $languageName = $language->name;

        $this->warn("\n💡 Procesando lenguaje: {$languageName}");

        $totalFound = 0;
        $totalNew = 0;
        $countries = [];
        $modalities = [];

        try {
  // 🌍 Intento principal: búsqueda directa
$response = Http::timeout(25)->get('https://www.arbeitnow.com/api/job-board-api', [
    'search' => $languageName,
]);

if ($response->failed()) {
    $this->error("❌ Falló la API para {$languageName}");
    continue;
}

$jobs = $response->json()['data'] ?? [];
$totalFound = count($jobs);

// ⚙️ Fallback si la búsqueda no devolvió nada
if ($totalFound === 0) {
    $backup = Http::timeout(25)->get('https://www.arbeitnow.com/api/job-board-api');

    if ($backup->ok()) {
        $allJobs = $backup->json()['data'] ?? [];

        $jobs = collect($allJobs)
            ->filter(function ($job) use ($languageName) {
                $text = strtolower(
                    html_entity_decode(
                        strip_tags(($job['title'] ?? '') . ' ' . ($job['description'] ?? ''))
                    )
                );

                // 🧠 Normalizar el nombre del lenguaje
                $needle = strtolower($languageName);
                $needle = str_replace(['#', '++'], ['sharp', 'pp'], $needle);
                $needle = preg_quote($needle, '/');

                // 🔍 Coincidencia estricta (palabra completa)
                return preg_match("/\\b{$needle}\\b/i", $text);
            })
            ->values()
            ->all();

        $this->stats['fallback'] += count($jobs);
        $totalFound = count($jobs);
    }
}

if ($totalFound === 0) {
    $this->warn("⚠️ Sin resultados para {$languageName}");
    continue;
}
foreach ($jobs as $job) {
    $title = $job['title'] ?? 'N/A';
    $company = $job['company_name'] ?? null;
    $location = $job['location'] ?? '';
    $description = $job['description'] ?? '';
    $urlJob = $job['url'] ?? null;
    $isRemote = $job['remote'] ?? false;

    // 🧭 Detección de país
    $countryCode = $this->detectCountryCode($location, $isRemote);
    [$city, $lat, $lng, $country] = $this->getCoordsFromCountry(
        $this->extractCity($location),
        $countryCode
    );

    // 🚫 Saltar si no hay país o coordenadas
    if (empty($country)) continue;

  $externalId = $job['slug'] ?? md5($urlJob ?? uniqid('arbeitnow_'));

$existingOffer = JobOffer::where('source', 'Arbeitnow')
    ->where(function ($q) use ($externalId, $title, $company, $country, $languageName, $urlJob) {
        $q->where('external_id', $externalId)
          ->orWhere(function ($q2) use ($title, $company, $country, $languageName, $urlJob) {
              $q2->where('title', $title)
                 ->where('company', $company)
                 ->where('country', $country ?? 'Unknown')
                 ->where('search_query', $languageName)
                 ->where(function ($q3) use ($urlJob) {
                     $q3->where('url', $urlJob)
                        ->orWhere('url', 'like', '%' . substr($urlJob, -25) . '%');
                 });
          });
    })
    ->first();


if ($existingOffer) {
    // 🧩 Si ya existe, asociar el lenguaje si aún no lo está
    $existingOffer->languages()->syncWithoutDetaching([$languageId]);
    continue; // ❌ No crear una nueva
}

$region = RegionHelper::fromCountry($country);


    $offer = JobOffer::create([
        'title'             => $title,
        'company'           => $company,
        'country'           => $country,
        'city'              => $city,
        'latitude'          => $lat,
        'longitude'         => $lng,
        'modality'          => $this->extractModality($location, $isRemote),
        'salary_min'        => null,
        'salary_max'        => null,
        'currency'          => 'EUR',
        'experience_level'  => null,
        'education_level'   => null,
        'certifications'    => null,
        'skills'            => null,
        'requirements'      => strip_tags($description),
        'source'            => 'Arbeitnow',
        'external_id'       => $job['slug'] ?? md5($urlJob ?? uniqid('arbeitnow_')),
        'url'               => $urlJob,
        'search_query'      => $languageName,
       'published_at' => isset($job['created_at'])
    ? (is_numeric($job['created_at'])
        ? Carbon::createFromTimestamp($job['created_at'])
        : Carbon::parse($job['created_at']))
    : now(),

        'created_at'        => now(),
        'updated_at'        => now(),
        'region' => $region,

    ]);

    // 🔗 Asociar pivot lenguaje ↔ oferta
    $offer->languages()->syncWithoutDetaching([$languageId]);

    // 📊 Contadores
    $totalNew++;
    $countries[$country] = ($countries[$country] ?? 0) + 1;
    $modality = $this->extractModality($location, $isRemote);
    $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;
}

            // 📈 Registrar métricas solo una vez por lenguaje
            $today = now()->toDateString();
            $existsToday = LanguageMetric::whereDate('run_date', $today)
                ->where('language_id', $languageId)
                ->where('source', 'Arbeitnow')
                ->exists();

            if (!$existsToday) {
                LanguageMetric::create([
                    'language_id' => $languageId,
                    'language_name' => $languageName,
                    'jobs_found_count' => $totalFound,
                    'jobs_new_count' => $totalNew,
                    'countries_breakdown' => $countries,
                    'modality_breakdown' => $modalities,
                    'run_date' => Carbon::today(),
                    'source' => 'Arbeitnow',
                ]);
            }

            $this->info("✅ {$languageName}: {$totalNew} nuevas | 🌍 {$totalFound} encontradas");
        } catch (\Throwable $th) {
            Log::error("⚠️ Error en {$languageName}: " . $th->getMessage());
        }

        sleep(1.5);
    } // 🔚 foreach $languages

    $this->info("\n🎯 Proceso completado: métricas registradas en `language_metrics`.");
}

protected function extractCity(?string $location): ?string
{
    if (empty($location)) return null;
    $parts = explode(',', $location);
    return trim($parts[0]); // Devuelve solo la primera parte (ej: "Berlin" de "Berlin, Germany")
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

protected function getCoordsFromCountry(?string $city, ?string $countryHint)
{
    if ($city && strtolower($city) !== 'remote') {
        $cityNorm = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $city));

        // 🗺️ 1️⃣ Buscar primero en tu tabla `cities`
        $found = City::whereRaw('LOWER(city_ascii) = ?', [$cityNorm])
            ->when($countryHint, fn($q) => $q->whereRaw('LOWER(iso2) = ?', [strtolower($countryHint)]))
            ->first();

        if ($found) {
            $this->stats['mapped']++;
            return [
                $found->city,
                $found->lat,
                $found->lng,
                $found->country ?? strtoupper($found->iso2), // 👈 usa el nombre del país completo
            ];
        }

        // 🌍 2️⃣ Si no está en DB, usar Nominatim
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
            $countryName = $data['address']['country'] ?? null; // 👈 nombre completo
            return $this->geoCache[$key] = [
                (float) $data['lat'],
                (float) $data['lon'],
                $countryName,
            ];
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

        // 🏢 Presencial / sin dato remoto
        default => 'no_remote',
    };
}


}
