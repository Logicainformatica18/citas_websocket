<?php

namespace App\Console\Commands\Scraping;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Competency;
use App\Models\JobOffer;
use App\Models\CompetencyMetric;
use App\Models\City;
use Carbon\Carbon;
use App\Helpers\RegionHelper;

class ArbeitnowByCompetenciesCommand extends Command
{
    protected $signature = 'arbeitnow:competencies';
    protected $description = '🌍 Recorre todas las competencias y registra métricas laborales desde Arbeitnow (Europa/Asia), con geolocalización y modalidades estandarizadas.';

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

        // Remoto
        'remote' => ['city' => 'Remoto', 'lat' => 0.0, 'lng' => 0.0],
    ];

    protected $geoCache = [];

public function handle()
{
    // SOLO competencias relacionadas a carreras, usando description_en como texto de búsqueda
    $competencies = Competency::select('competencies.id', 'competencies.description_en')
        ->whereNotNull('career_id')
        ->whereNotNull('description_en')
        ->get();

    $this->info("🌐 Iniciando scraping Arbeitnow por competencias ({$competencies->count()} competencias)...");

    foreach ($competencies as $comp) {
        $competencyId = $comp->id;
        $competencyName = $comp->description_en; // 👉 TEXTO REAL DE BÚSQUEDA

        $this->warn("\n💡 Procesando competencia: {$competencyName}");

        $totalFound = 0;
        $totalNew = 0;
        $countries = [];
        $modalities = [];

        try {

            // 🌍 BÚSQUEDA PRINCIPAL
            $response = Http::timeout(25)->get('https://www.arbeitnow.com/api/job-board-api', [
                'search' => $competencyName,
            ]);

            if ($response->failed()) {
                $this->error("❌ Falló la API para {$competencyName}");
                continue;
            }

            $jobs = $response->json()['data'] ?? [];
            $totalFound = count($jobs);

            // ⚙️ Fallback si no devuelve nada
            if ($totalFound === 0) {
                $backup = Http::timeout(25)->get('https://www.arbeitnow.com/api/job-board-api');

                if ($backup->ok()) {
                    $allJobs = $backup->json()['data'] ?? [];

                    $jobs = collect($allJobs)
                        ->filter(function ($job) use ($competencyName) {
                            $text = strtolower(strip_tags(($job['title'] ?? '') . ' ' . ($job['description'] ?? '')));
                            return str_contains($text, strtolower($competencyName));
                        })
                        ->values()
                        ->all();

                    $this->stats['fallback'] += count($jobs);
                    $totalFound = count($jobs);
                }
            }

            if ($totalFound === 0) {
                $this->warn("⚠️ Sin resultados para {$competencyName}");
                continue;
            }

            foreach ($jobs as $job) {

                $title = $job['title'] ?? 'N/A';
                $company = $job['company_name'] ?? null;
                $location = $job['location'] ?? '';
                $description = $job['description'] ?? '';
                $urlJob = $job['url'] ?? null;
                $isRemote = $job['remote'] ?? false;

                // 🧭 Detectar país
                $countryCode = $this->detectCountryCode($location, $isRemote);

                [$city, $lat, $lng, $country] = $this->getCoordsFromCountry(
                    $this->extractCity($location),
                    $countryCode
                );

                if (empty($country)) continue;

                // 🆔 ID externo único
                $externalId = $job['slug'] ?? md5($urlJob ?? uniqid('arbeitnow_'));

                // 🔎 Revisar duplicados
                $existingOffer = JobOffer::where('source', 'Arbeitnow')
                    ->where('external_id', $externalId)
                    ->first();

                if ($existingOffer) {
                    $existingOffer->competencies()->syncWithoutDetaching([$competencyId]);
                    continue;
                }

                $region = RegionHelper::fromCountry($country);

                // 💾 Crear oferta
                $offer = JobOffer::create([
                    'title' => $title,
                    'company' => $company,
                    'country' => $country,
                    'city' => $city,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'modality' => $this->extractModality($location, $isRemote),
                    'currency' => 'EUR',
                    'requirements' => strip_tags($description),
                    'source' => 'Arbeitnow',
                    'external_id' => $externalId,
                    'url' => $urlJob,
                    'search_query' => $competencyName, // 👉 TEXTO USADO EN LA BÚSQUEDA
                    'region' => $region,
                    'published_at' => isset($job['created_at'])
                        ? (is_numeric($job['created_at'])
                            ? Carbon::createFromTimestamp($job['created_at'])
                            : Carbon::parse($job['created_at']))
                        : now(),
                ]);

                // 🔗 Pivot Competencia ↔ Oferta
                $offer->competencies()->syncWithoutDetaching([$competencyId]);

                $totalNew++;
                $countries[$country] = ($countries[$country] ?? 0) + 1;

                $modality = $this->extractModality($location, $isRemote);
                $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;
            }

            // 📊 MÉTRICAS (solo una por día y competencia)
            CompetencyMetric::updateOrCreate(
                [
                    'competency_id' => $competencyId,
                    'run_date'      => now()->toDateString(),
                    'source'        => 'Arbeitnow',
                ],
                [
                    'competency_name'    => $competencyName,
                    'jobs_found_count'   => $totalFound,
                    'jobs_new_count'     => $totalNew,
                    'countries_breakdown'=> $countries,
                    'modality_breakdown' => $modalities,
                ]
            );

            $this->info("✅ {$competencyName}: {$totalNew} nuevas | 🌍 {$totalFound} encontradas");

        } catch (\Throwable $th) {
            Log::error("⚠️ Error en {$competencyName}: " . $th->getMessage());
        }

        sleep(1.5);
    }

    $this->info("\n🎯 Proceso completado: métricas registradas en `competency_metrics`.");
}


    protected function extractCity(?string $location): ?string
    {
        if (empty($location)) return null;
        $parts = explode(',', $location);
        return trim($parts[0]);
    }

    protected function detectCountryCode($location, $isRemote)
    {
        $loc = strtolower($location ?? '');
        if ($isRemote) return 'remote';

        return match (true) {
            str_contains($loc, 'germany') => 'de',
            str_contains($loc, 'spain')   => 'es',
            str_contains($loc, 'france')  => 'fr',
            str_contains($loc, 'netherlands') => 'nl',
            str_contains($loc, 'italy')   => 'it',
            str_contains($loc, 'portugal')=> 'pt',
            str_contains($loc, 'poland')  => 'pl',
            str_contains($loc, 'uk'),
            str_contains($loc, 'london')  => 'gb',
            str_contains($loc, 'ireland') => 'ie',
            str_contains($loc, 'switzerland') => 'ch',

            str_contains($loc, 'india')   => 'in',
            str_contains($loc, 'japan')   => 'jp',
            str_contains($loc, 'singapore') => 'sg',
            str_contains($loc, 'korea')     => 'kr',
            str_contains($loc, 'china')     => 'cn',
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

    protected function extractModality(string $location, bool $isRemote): string
    {
        $loc = strtolower($location);
        return match (true) {
            $isRemote,
            str_contains($loc, 'remote'),
            str_contains($loc, 'anywhere'),
            str_contains($loc, 'work from home'),
            str_contains($loc, 'home office') => 'remote',
            str_contains($loc, 'hybrid') => 'hybrid',
            default => 'no_precisa',
        };
    }
}
