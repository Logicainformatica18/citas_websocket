<?php

namespace App\Console\Commands\Scraping;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Competency;
use App\Models\JobOffer;
use App\Models\CompetencyMetric;
use App\Models\City;
use App\Helpers\CountryNormalizer;
use Carbon\Carbon;

class USAJOBSByCompetenciesCommand extends Command
{
    protected $signature = 'usajobs:competencies {--pages=1}';
    protected $description = '🇺🇸 Importa ofertas laborales desde USAJOBS por competencia.';

    protected $stats = [
        'api_hits' => 0,
        'mapped'   => 0,
        'skipped'  => 0,
    ];

   public function handle()
{
    // ✔ Competencias de carreras usando description_en → fallback name
    $competencies = Competency::select('id','name','description_en')
        ->whereNotNull('career_id')
        ->get()
        ->mapWithKeys(fn($c) => [
            $c->id => ($c->description_en ?: $c->name)
        ]);

    $this->info("🇺🇸 Importando USAJOBS para {$competencies->count()} competencias…");

    foreach ($competencies as $competencyId => $competencyName) {

        $this->warn("\n🔎 Buscando: {$competencyName}");

        $totalFound = 0;
        $totalNew   = 0;

        for ($page = 0; $page < (int)$this->option('pages'); $page++) {

            // 🔵 PETICIÓN A USAJOBS
            try {
                $response = Http::withHeaders([
                    'Host'              => 'data.usajobs.gov',
                    'User-Agent'        => config('app.name') . ' (developer@example.com)',
                    'Authorization-Key' => env('USAJOBS_API_KEY'),
                ])->get('https://data.usajobs.gov/api/Search', [
                    'Keyword'        => $competencyName,
                    'ResultsPerPage' => 100,
                    'Page'           => $page + 1,
                ]);

                $this->stats['api_hits']++;

                if ($response->failed()) {
                    $this->error("❌ Error API USAJOBS para {$competencyName}, página " . ($page + 1));
                    continue;
                }

                $jobs = $response->json()['SearchResult']['SearchResultItems'] ?? [];
                if (empty($jobs)) {
                    $this->info("⚠️ Sin resultados");
                    break;
                }

                $totalFound += count($jobs);

                // ==========================
                // 🔄 PROCESAR CADA OFERTA
                // ==========================
                foreach ($jobs as $item) {

                    $job = $item['MatchedObjectDescriptor'] ?? [];

                    $externalId = "usajobs-" . ($job['PositionID'] ?? uniqid());

                    // 🛑 DUPLICADO
                    if (JobOffer::where('external_id', $externalId)
                        ->where('source', 'USAJOBS')
                        ->exists()) {

                        $this->stats['skipped']++;
                        continue;
                    }

                    // ==========================
                    // 🌎 LOCALIZACIÓN
                    // ==========================
                    $locationRaw = $job['PositionLocation'][0]['LocationName'] ?? null;

                    $cityMatch = null;
                    if ($locationRaw) {
                        $cityMatch = City::whereRaw("LOWER(city_ascii) = ?", [strtolower($locationRaw)])
                            ->orWhereRaw("LOWER(city) = ?", [strtolower($locationRaw)])
                            ->first();
                    }

                    if ($cityMatch) {
                        $city    = $cityMatch->city;
                        $lat     = $cityMatch->lat;
                        $lng     = $cityMatch->lng;
                        $country = CountryNormalizer::normalize($cityMatch->country);
                        $this->stats['mapped']++;

                    } else {
                        // 🇺🇸 FALLBACK: CAPITAL USA
                        $city    = "Washington D.C.";
                        $lat     = 38.8951;
                        $lng     = -77.0364;
                        $country = "Estados Unidos";
                        $this->stats['skipped']++;
                    }

                    // ==========================
                    // 💼 MODALIDAD
                    // ==========================
                    $modality = 'no_remote';

                    // ==========================
                    // 📅 FECHA PUBLICACIÓN
                    // ==========================
                    $publishedAt = isset($job['PublicationStartDate'])
                        ? Carbon::parse($job['PublicationStartDate'])
                        : now();

                    // ==========================
                    // 💵 SALARIOS
                    // ==========================
                    $salaryMin = $this->cleanSalary($job['PositionRemuneration'][0]['MinimumRange'] ?? null);
                    $salaryMax = $this->cleanSalary($job['PositionRemuneration'][0]['MaximumRange'] ?? null);
                    $compType  = $this->normalizeCompType($job['PositionRemuneration'][0]['RateIntervalCode'] ?? null);

                    // ==========================
                    // 💾 CREAR OFERTA
                    // ==========================
                    $offer = JobOffer::create([
                        'title'              => $job['PositionTitle'] ?? '',
                        'company'            => $job['OrganizationName'] ?? '',
                        'country'            => $country,
                        'region'             => RegionHelper::fromCountry($country),
                        'city'               => $city,
                        'latitude'           => $lat,
                        'longitude'          => $lng,
                        'modality'           => $modality,
                        'salary_min'         => $salaryMin,
                        'salary_max'         => $salaryMax,
                        'currency'           => 'USD',
                        'compensation_type'  => $compType,
                        'source'             => 'USAJOBS',
                        'external_id'        => $externalId,
                        'url'                => $job['PositionURI'] ?? null,
                        'search_query'       => $competencyName,
                        'published_at'       => $publishedAt,
                    ]);

                    // Pivot competencia ↔ oferta
                    $offer->competencies()->syncWithoutDetaching([$competencyId]);

                    $totalNew++;
                }

            } catch (\Throwable $e) {
                Log::error("❌ Error USAJOBS {$competencyName}: " . $e->getMessage());
                continue;
            }
        }

        // ==========================
        // 📊 MÉTRICA POR COMPETENCIA
        // ==========================
        CompetencyMetric::updateOrCreate(
            [
                'competency_id' => $competencyId,
                'run_date'      => now()->toDateString(),
                'source'        => 'USAJOBS',
            ],
            [
                'competency_name'  => $competencyName,
                'jobs_found_count' => $totalFound,
                'jobs_new_count'   => $totalNew,
                'updated_at'       => now(),
            ]
        );

        $this->info("✔ {$competencyName}: {$totalNew} nuevas / {$totalFound} encontradas");
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
