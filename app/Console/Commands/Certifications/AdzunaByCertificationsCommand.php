<?php

namespace App\Console\Commands\Certifications;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Certification;
use App\Models\JobOffer;
use App\Models\CertificationMetric;
use App\Models\City;
use Carbon\Carbon;
use App\Helpers\RegionHelper;

class AdzunaByCertificationsCommand extends Command
{
    protected $signature = 'adzuna:certifications {--country=us} {--pages=1}';

    protected $description = '🏅 Importa ofertas laborales desde Adzuna por certificación, con geolocalización, modalidad y métricas diarias.';

    protected $stats = [
        'api_hits'  => 0,
        'fallback'  => 0,
        'mapped'    => 0,
        'skipped'   => 0,
    ];

    protected $capitalMap = [
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
        $country = strtolower($this->option('country'));
        $pages   = (int) $this->option('pages');

        $certifications = Certification::where('enabled', 1)
            ->pluck('name', 'id');

        $appId   = config('services.adzuna.app_id');
        $appKey  = config('services.adzuna.app_key');
        $baseUrl = config('services.adzuna.base_url', 'https://api.adzuna.com/v1/api/jobs');

        $this->info("🏅 Iniciando importación desde Adzuna para {$certifications->count()} certificaciones...");

        foreach ($certifications as $certId => $certName) {
            $this->warn("\n💡 Procesando certificación: {$certName}");

            $totalFound = $totalNew = $totalDuplicates = 0;
            $countries = [];
            $modalities = [];

            for ($page = 1; $page <= $pages; $page++) {

                $url = "{$baseUrl}/{$country}/search/{$page}"
                    . "?app_id={$appId}&app_key={$appKey}"
                    . "&results_per_page=100"
                    . "&what=" . urlencode($certName);

                try {
                    $response = Http::timeout(25)->get($url);
                    if ($response->failed()) continue;

                    $results = $response->json('results') ?? [];
                    $totalFound += count($results);

                    foreach ($results as $job) {

                        $desc = strtolower($job['description'] ?? '');

                        // 🔎 Validación real por texto
                        if (!str_contains($desc, strtolower($certName))) {
                            continue;
                        }

                        $externalId = $job['id'] ?? null;

                       $existing = JobOffer::where('external_id', $externalId)->first();

if ($existing) {

    // 🔍 Verificar si YA existe la relación
    $alreadyLinked = $existing->certifications()
        ->where('certification_id', $certId)
        ->exists();

    if (!$alreadyLinked) {
        $existing->certifications()->attach($certId);

        Log::info('🧩 CERT LINKED', [
            'job_offer_id' => $existing->id,
            'certification_id' => $certId,
            'certification' => $certName,
        ]);
    } else {
        Log::debug('ℹ️ CERT ALREADY LINKED', [
            'job_offer_id' => $existing->id,
            'certification_id' => $certId,
        ]);
    }

    $totalDuplicates++;
    continue;
}


                        // 📍 Ubicación
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
                                $lat  = $cap['lat'];
                                $lng  = $cap['lng'];
                                $this->stats['fallback']++;
                            } else {
                                $this->stats['skipped']++;
                                continue;
                            }
                        }

                        $offer = JobOffer::create([
                            'title'            => $job['title'] ?? 'N/A',
                            'company'          => $job['company']['display_name'] ?? null,
                            'country'          => $countryFull,
                            'city'             => $city,
                            'latitude'         => $lat,
                            'longitude'        => $lng,
                            'modality'         => $this->detectModality(
                                strtolower($job['title'] ?? ''),
                                $desc
                            ),
                            'experience_level' => $this->extractExperience($desc),
                            'education_level'  => $this->extractEducation($desc),
                            'requirements'     => strip_tags($job['description'] ?? null),
                            'source'           => 'Adzuna',
                            'external_id'      => $externalId,
                            'url'              => $job['redirect_url'] ?? null,
                            'published_at'     => Carbon::parse($job['created'] ?? now()),
                            'region'           => RegionHelper::fromCountry($countryFull),
                        ]);


                        // 🔗 Pivot certification
                        $offer->certifications()
                            ->syncWithoutDetaching([$certId]);

                            $offer->certifications()->attach($certId);



                        $totalNew++;

                        $countries[$countryCode] =
                            ($countries[$countryCode] ?? 0) + 1;

                        $modalities[$offer->modality] =
                            ($modalities[$offer->modality] ?? 0) + 1;
                    }

                    sleep(1);
                } catch (\Throwable $e) {
                    Log::error("Cert {$certName}: {$e->getMessage()}");
                }
            }

            // 📊 Métrica diaria
            CertificationMetric::firstOrCreate(
                [
                    'certification_id' => $certId,
                    'run_date'         => now()->toDateString(),
                    'source'           => 'Adzuna',
                ],
                [
                    'certification_name' => $certName,
                    'jobs_found_count'   => $totalFound,
                    'jobs_new_count'     => $totalNew,
                    'countries_breakdown'=> $countries,
                    'modality_breakdown' => $modalities,
                ]
            );

            $this->info("✅ {$certName}: {$totalNew} nuevas | 🌍 {$totalFound} encontradas");
        }

        $this->info("\n🎯 Proceso de certificaciones completado");
    }

    /* ================= HELPERS ================= */

    protected function detectModality(string $title, string $description): string
    {
        $text = "{$title} {$description}";

        return match (true) {
            str_contains($text, 'remote'),
            str_contains($text, 'home office'),
            str_contains($text, 'teletrabajo') => 'remote',

            str_contains($text, 'hybrid'),
            str_contains($text, 'híbrido') => 'hybrid',

            default => 'no_precisa',
        };
    }

    protected function getCoordsFromCountry(?string $city, ?string $countryCode)
    {
        if ($city && strtolower($city) !== 'remote') {
            $foundCity = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])
                ->when($countryCode, fn ($q) =>
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

    protected function extractExperience(string $text): ?string
    {
        return match (true) {
            str_contains($text, 'senior') => 'senior',
            str_contains($text, 'mid')    => 'mid',
            str_contains($text, 'junior') => 'junior',
            default => null,
        };
    }

    protected function extractEducation(string $text): ?string
    {
        return match (true) {
            str_contains($text, 'bachelor') => 'bachelor',
            str_contains($text, 'master')   => 'master',
            str_contains($text, 'phd')      => 'phd',
            default => null,
        };
    }
}
