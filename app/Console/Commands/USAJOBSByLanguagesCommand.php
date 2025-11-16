<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Language;
use App\Models\JobOffer;
use App\Models\LanguageMetric;
use App\Models\City;
use App\Helpers\CountryNormalizer;
use Carbon\Carbon;

class USAJOBSByLanguagesCommand extends Command
{
    protected $signature = 'usajobs:languages {--pages=1}';
    protected $description = '🇺🇸 Importa ofertas laborales desde USAJOBS por lenguaje.';

    protected $stats = [
        'api_hits' => 0,
        'mapped'   => 0,
        'skipped'  => 0,
    ];

    public function handle()
    {
             $languages = Language::whereIn('languages.id', function ($q) {
        $q->select('course_language.language_id')
            ->from('course_language')
            ->join('career_course', 'career_course.course_id', '=', 'course_language.course_id');
    })
    ->pluck('name', 'id');

        $this->info("🇺🇸 Importando desde USAJOBS para {$languages->count()} lenguajes…");

        foreach ($languages as $languageId => $languageName) {

            $this->warn("\n🔎 Buscando: {$languageName}");

            for ($page = 0; $page < (int)$this->option('pages'); $page++) {

                // 🔵 Petición USAJOBS
                $response = Http::withHeaders([
                    'Host'              => 'data.usajobs.gov',
                    'User-Agent'        => config('app.name') . ' (developer@example.com)',
                    'Authorization-Key' => env('USAJOBS_API_KEY'),
                ])->get('https://data.usajobs.gov/api/Search', [
                    'Keyword'        => $languageName,
                    'ResultsPerPage' => 100,
                    'Page'           => $page + 1,
                ]);

                $this->stats['api_hits']++;

                if ($response->failed()) {
                    $this->error("Error API para {$languageName}, página " . ($page + 1));
                    continue;
                }

                $jobs = $response->json()['SearchResult']['SearchResultItems'] ?? [];

                if (empty($jobs)) {
                    $this->info("⚠️ Sin resultados");
                    break;
                }

                foreach ($jobs as $item) {

                    $job = $item['MatchedObjectDescriptor'] ?? [];

                    $externalId = "usajobs-" . ($job['PositionID'] ?? uniqid());

                    // 🛑 DEDUPE
                    if (JobOffer::where('external_id', $externalId)
                        ->where('source', 'usajobs')
                        ->exists()) {
                        $this->stats['skipped']++;
                        continue;
                    }

                    // 🇺🇸 País fijo
                    $countryIso = "US";
                    $country    = "Estados Unidos";

                    // 🏙️ Ubicación
                    $locationRaw = $job['PositionLocation'][0]['LocationName'] ?? null;

                    $cityMatch = City::whereRaw("LOWER(city_ascii) = ?", [strtolower($locationRaw)])
                                    ->orWhereRaw("LOWER(city) = ?", [strtolower($locationRaw)])
                                    ->first();

                    if ($cityMatch) {
                        $city    = $cityMatch->city;
                        $lat     = $cityMatch->lat;
                        $lng     = $cityMatch->lng;
                        $country = CountryNormalizer::normalize($cityMatch->country);
                    } else {
                        // 🟡 FALLBACK: capital de EE.UU
                        $fallback = [
                            'city'    => 'Washington D.C.',
                            'lat'     => 38.8951,
                            'lng'     => -77.0364,
                            'country' => 'Estados Unidos'
                        ];
                        $city    = $fallback['city'];
                        $lat     = $fallback['lat'];
                        $lng     = $fallback['lng'];
                        $country = $fallback['country'];
                    }

                    // 🧩 Modalidad por defecto
                    $modality = 'no_remote';

                    // 📅 Fecha segura
                    $pubDate = isset($job['PublicationStartDate'])
                        ? Carbon::parse($job['PublicationStartDate'])
                        : now();

                    // 💵 SALARIO LIMPIO
                    $salaryMin = $this->cleanSalary($job['PositionRemuneration'][0]['MinimumRange'] ?? null);
                    $salaryMax = $this->cleanSalary($job['PositionRemuneration'][0]['MaximumRange'] ?? null);
                    $compType  = $this->normalizeCompType($job['PositionRemuneration'][0]['RateIntervalCode'] ?? null);

                    // 💾 GUARDAR OFERTA
                    JobOffer::create([
                        'title'              => $job['PositionTitle'] ?? '',
                        'company'            => $job['OrganizationName'] ?? '',
                        'country'            => $country,
                        'city'               => $city,
                        'latitude'           => $lat,
                        'longitude'          => $lng,
                        'modality'           => $modality,
                        'salary_min'         => $salaryMin,
                        'salary_max'         => $salaryMax,
                        'currency'           => 'USD',
                        'compensation_type'  => $compType,
                        'source'             => 'usajobs',
                        'external_id'        => $externalId,
                        'url'                => $job['PositionURI'] ?? null,
                        'search_query'       => $languageName,
                        'published_at'       => $pubDate,
                    ]);

                    // 📊 MÉTRICA
                    LanguageMetric::create([
                        'language_id' => $languageId,
                        'total'       => 1,
                        'country'     => $country,
                        'source'      => 'usajobs',
                        'run_date'    => now()->toDateString(),
                    ]);

                    $this->stats['mapped']++;
                }
            }
        }

        $this->info("\n🟢 USAJOBS COMPLETADO");
        $this->info("API Hits: {$this->stats['api_hits']}");
        $this->info("Ofertas nuevas: {$this->stats['mapped']}");
        $this->info("Saltadas: {$this->stats['skipped']}");
    }


    // ---------------------------------------------------------
    // 🧼 LIMPIADORES DE SALARIO
    // ---------------------------------------------------------

    protected function cleanSalary(?string $value): ?float
    {
        if (!$value) return null;
        $clean = str_replace([',', ' '], '', $value);
        return is_numeric($clean) ? (float)$clean : null;
    }

    protected function normalizeCompType(?string $code): ?string
    {
        return match ($code) {
            'PA' => 'yearly',
            'PH' => 'hourly',
            'PM' => 'monthly',
            'PD' => 'daily',
            default => null,
        };
    }
}
