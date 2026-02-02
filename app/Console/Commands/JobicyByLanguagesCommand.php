<?php

namespace App\Console\Commands\Certification;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Language;
use App\Models\JobOffer;
use App\Models\LanguageMetric;
use App\Models\City;
use Carbon\Carbon;
use App\Helpers\RegionHelper;
use App\Helpers\CountryNormalizer;

class JobicyByLanguagesCommand extends Command
{
    protected $signature = 'jobicy:languages';
    protected $description = '🌍 Importa ofertas laborales desde Jobicy por lenguaje, con geolocalización, modalidad y métricas diarias.';

    protected $stats = [
        'mapped'   => 0,
        'api_hits' => 0,
        'fallback' => 0,
        'skipped'  => 0,
    ];

    protected $capitalMap = [
        'us' => ['city' => 'Washington D.C.', 'lat' => 38.8951, 'lng' => -77.0364],
        'ca' => ['city' => 'Ottawa', 'lat' => 45.4215, 'lng' => -75.6997],
        'gb' => ['city' => 'Londres', 'lat' => 51.5074, 'lng' => -0.1278],
        'au' => ['city' => 'Sídney', 'lat' => -33.8688, 'lng' => 151.2093],
        'es' => ['city' => 'Madrid', 'lat' => 40.4168, 'lng' => -3.7038],
        'mx' => ['city' => 'Ciudad de México', 'lat' => 19.4326, 'lng' => -99.1332],
        'br' => ['city' => 'Brasilia', 'lat' => -15.7939, 'lng' => -47.8828],
        'in' => ['city' => 'Nueva Delhi', 'lat' => 28.6139, 'lng' => 77.2090],
        'de' => ['city' => 'Berlín', 'lat' => 52.5200, 'lng' => 13.4050],
        'fr' => ['city' => 'París', 'lat' => 48.8566, 'lng' => 2.3522],
    ];

    public function handle()
    {
        // 🔹 Solo lenguajes asociados a carreras ISIL
        $languages = Language::whereIn('languages.id', function ($q) {
            $q->select('course_language.language_id')
              ->from('course_language')
              ->join('career_course', 'career_course.course_id', '=', 'course_language.course_id');
        })->pluck('name', 'id');

        $this->info("🌍 Iniciando importación desde Jobicy para {$languages->count()} lenguajes...");

        foreach ($languages as $languageId => $languageName) {
            $this->warn("\n💡 Procesando lenguaje: {$languageName}");

            try {
                // 🌐 Consulta global Jobicy
                $response = Http::timeout(25)->get('https://jobicy.com/api/v2/remote-jobs');
                if ($response->failed()) {
                    $this->error("❌ Error al consultar Jobicy API para {$languageName}");
                    continue;
                }

                $jobs = collect($response->json('jobs') ?? [])
                    ->filter(fn($job) => str_contains(
                        strtolower(($job['jobTitle'] ?? '') . ' ' . ($job['jobDescription'] ?? '')),
                        strtolower($languageName)
                    ))
                    ->values();

                $totalFound = $jobs->count();
                $totalNew = 0;
                $countries = [];
                $modalities = [];

                foreach ($jobs as $job) {
                    $externalId = $job['id'] ?? null;
                    if ($externalId && JobOffer::where('external_id', $externalId)->exists()) {
                        continue;
                    }

                    // ==============================
                    // 🌍 Normalización de país (CountryNormalizer)
                    // ==============================
                    $countryRaw = $job['jobGeo'] ?? null;
                    $country = CountryNormalizer::normalize($countryRaw);

                    if ($country === 'Desconocido') {
                        $this->stats['skipped']++;
                        Log::info("⏭️ Omitido: {$job['jobTitle']} (sin país detectado)");
                        continue;
                    }

                    // ==============================
                    // 🧭 Geolocalización
                    // ==============================
                    $code = strtolower(substr($countryRaw ?? '', 0, 2));
                    $city = $this->capitalMap[$code]['city'] ?? 'Remote';
                    [$city, $lat, $lng] = $this->getCoordsFromCountry($city, $code);

                    // ==============================
                    // 💼 Datos extendidos
                    // ==============================
                    $title   = $job['jobTitle'] ?? 'N/A';
                    $company = $job['companyName'] ?? null;
                    $urlJob  = $job['url'] ?? null;
                    $desc    = strip_tags($job['jobDescription'] ?? '');
                    $modality = $this->detectModality(
                        $desc,
                        is_array($job['jobType']) ? implode(' ', $job['jobType']) : ($job['jobType'] ?? '')
                    );

                    // 💰 Salarios
                    $salaryMin = $job['annualSalaryMin'] ?? null;
                    $salaryMax = $job['annualSalaryMax'] ?? null;
                    $currency  = $job['salaryCurrency'] ?? 'USD';

                    // 📊 Experiencia y certificaciones
                    $experienceYears = null;
                    $certifications  = [];

                    if (preg_match('/(\d+)\+?\s*(years?|años?)\s+of\s+experience/i', $desc, $m)) {
                        $experienceYears = (int) $m[1];
                    }

                    if (preg_match_all('/(AWS|Azure|Scrum|PMP|Certification|Certified)/i', $desc, $matches)) {
                        $certifications = array_unique($matches[0]);
                    }

                    // ==============================
                    // 💾 Crear registro
                    // ==============================
                    $offer = JobOffer::create([
                        'title'             => $title,
                        'company'           => $company,
                        'country'           => $country,
                        'region'            => RegionHelper::fromCountry($country),
                        'city'              => $city,
                        'latitude'          => $lat,
                        'longitude'         => $lng,
                        'modality'          => $modality,
                        'experience_level'  => $experienceYears,
                        'certifications'    => !empty($certifications) ? implode(', ', $certifications) : null,
                        'description'       => $desc,
                        'salary_min'        => $salaryMin,
                        'salary_max'        => $salaryMax,
                        'currency'          => $currency,
                        'source'            => 'Jobicy',
                        'external_id'       => $externalId,
                        'url'               => $urlJob,
                        'search_query'      => $languageName,
                        'published_at'      => isset($job['pubDate']) ? Carbon::parse($job['pubDate']) : now(),
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);

                    // 🔗 Relación lenguaje ↔ oferta
                    $offer->languages()->syncWithoutDetaching([$languageId]);

                    // 📊 Contadores
                    $totalNew++;
                    $countries[$country] = ($countries[$country] ?? 0) + 1;
                    $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;

                    $this->line("✅ {$title} ({$country}) 💰{$salaryMin}-{$salaryMax} {$currency}");
                }

                // ==============================
                // 📈 Guardar métricas
                // ==============================
                LanguageMetric::updateOrCreate(
                    [
                        'language_id' => $languageId,
                        'run_date'    => Carbon::today(),
                        'source'      => 'Jobicy',
                    ],
                    [
                        'language_name'       => $languageName,
                        'jobs_found_count'    => $totalFound,
                        'jobs_new_count'      => $totalNew,
                        'countries_breakdown' => $countries,
                        'modality_breakdown'  => $modalities,
                        'updated_at'          => now(),
                    ]
                );

                $this->info("📊 {$languageName}: {$totalNew} nuevas | 🌍 {$totalFound} totales | ⏭️ Omitidas: {$this->stats['skipped']}");

            } catch (\Throwable $e) {
                Log::error("⚠️ Error en {$languageName}: " . $e->getMessage());
                $this->error("❌ {$languageName}: " . $e->getMessage());
            }

            usleep(random_int(600000, 1200000)); // delay anti-baneo
        }

        $this->info("\n🎯 Proceso completado Jobicy. ⏭️ Omitidas sin país: {$this->stats['skipped']}");
    }

    // 🧠 Detección de modalidad
    protected function detectModality(string $desc, string $type): string
    {
        $text = strtolower($desc . ' ' . $type);

        return match (true) {
            str_contains($text, 'fully remote'),
            str_contains($text, 'work from anywhere'),
            str_contains($text, 'remote worldwide'),
            str_contains($text, 'anywhere') => 'fully_remote',

            str_contains($text, 'hybrid'),
            str_contains($text, 'partial remote') => 'hybrid',

            str_contains($text, 'remote local'),
            str_contains($text, 'remote in'),
            str_contains($text, 'local remote') => 'remote_local',

            default => 'no_precisa',
        };
    }

    // 🌍 Coordenadas por país o capital
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
        }

        if ($countryCode && isset($this->capitalMap[$countryCode])) {
            $capital = $this->capitalMap[$countryCode];
            $this->stats['fallback']++;
            return [$capital['city'], $capital['lat'], $capital['lng']];
        }

        $this->stats['skipped']++;
        return [$city ?? 'Remote', null, null];
    }
}
