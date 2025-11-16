<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Methodology;
use App\Models\JobOffer;
use App\Models\MethodologyMetric;
use App\Models\City;
use App\Helpers\CountryNormalizer;
use Carbon\Carbon;

class USAJOBSByMethodologiesCommand extends Command
{
    protected $signature = 'usajobs:methodologies {--pages=1}';
    protected $description = '🇺🇸 Importa ofertas laborales desde USAJOBS por metodología.';

    protected $stats = [
        'api_hits' => 0,
        'mapped'   => 0,
        'skipped'  => 0,
    ];

    public function handle()
    {
        // SOLO metodologías que estén en cursos → carreras
        $methodologies = Methodology::whereIn('methodologies.id', function ($q) {
                $q->select('course_methodology.methodology_id')
                  ->from('course_methodology')
                  ->join('career_course', 'career_course.course_id', '=', 'course_methodology.course_id');
        })->pluck('name', 'id');

        $this->info("🇺🇸 Importando desde USAJOBS para {$methodologies->count()} metodologías…");

        foreach ($methodologies as $methodologyId => $methodologyName) {

            $this->warn("\n🔎 Buscando metodología: {$methodologyName}");

            for ($page = 0; $page < (int)$this->option('pages'); $page++) {

                // PETICIÓN USAJOBS
                $response = Http::withHeaders([
                    'Host'              => 'data.usajobs.gov',
                    'User-Agent'        => config('app.name') . ' (developer@example.com)',
                    'Authorization-Key' => env('USAJOBS_API_KEY'),
                ])->get('https://data.usajobs.gov/api/Search', [
                    'Keyword'        => $methodologyName,
                    'ResultsPerPage' => 100,
                    'Page'           => $page + 1,
                ]);

                $this->stats['api_hits']++;

                if ($response->failed()) {
                    $this->error("Error API para {$methodologyName}, página " . ($page + 1));
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

                    // DEDUPE
                    $existing = JobOffer::where('external_id', $externalId)
                        ->where('source', 'usajobs')
                        ->first();

                    if ($existing) {
                        // solo asociar metodología al pivot
                        $existing->methodologies()->syncWithoutDetaching([$methodologyId]);
                        $this->stats['skipped']++;
                        continue;
                    }

                    // PAÍS fijo
                    $countryIso = "US";
                    $country    = "Estados Unidos";

                    // UBICACIÓN
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
                        // fallback capital
                        $city    = 'Washington D.C.';
                        $lat     = 38.8951;
                        $lng     = -77.0364;
                        $country = 'Estados Unidos';
                    }

                    // Modalidad por defecto
                    $modality = 'no_remote';

                    // Fecha segura
                    $pubDate = isset($job['PublicationStartDate'])
                        ? Carbon::parse($job['PublicationStartDate'])
                        : now();

                    // SALARIO LIMPIO
                    $salaryMin = $this->cleanSalary($job['PositionRemuneration'][0]['MinimumRange'] ?? null);
                    $salaryMax = $this->cleanSalary($job['PositionRemuneration'][0]['MaximumRange'] ?? null);
                    $compType  = $this->normalizeCompType($job['PositionRemuneration'][0]['RateIntervalCode'] ?? null);

                    // NUEVA OFERTA
                    $offer = JobOffer::create([
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
                        'search_query'       => $methodologyName,
                        'published_at'       => $pubDate,
                    ]);

                    // Asociar metodología
                    $offer->methodologies()->syncWithoutDetaching([$methodologyId]);

                    // MÉTRICA
                    MethodologyMetric::create([
                        'methodology_id' => $methodologyId,
                        'methodology_name' => $methodologyName,
                        'jobs_found_count' => 1,
                        'run_date' => now()->toDateString(),
                        'source'    => 'usajobs',
                        'countries_breakdown' => [$countryIso => 1],
                        'modality_breakdown'  => [$modality => 1],
                    ]);

                    $this->stats['mapped']++;
                }
            }
        }

        $this->info("\n🟢 USAJOBS METODOLOGÍAS COMPLETADO");
        $this->info("API Hits: {$this->stats['api_hits']}");
        $this->info("Ofertas nuevas: {$this->stats['mapped']}");
        $this->info("Saltadas: {$this->stats['skipped']}");
    }

    // LIMPIADORES
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
