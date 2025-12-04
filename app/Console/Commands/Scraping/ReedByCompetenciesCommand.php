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
use App\Helpers\RegionHelper;
use Carbon\Carbon;

class ReedByCompetenciesCommand extends Command
{
    protected $signature = 'reed:competencies {--pages=1}';
    protected $description = '🇬🇧 Importa ofertas laborales desde Reed UK por competencia.';

    protected $stats = [
        'api_hits'  => 0,
        'mapped'    => 0,
        'skipped'   => 0,
    ];

  public function handle()
{
    $pages = (int)$this->option('pages');

    // ✔ SOLO competencias asociadas a carreras
    // ✔ description_en → fallback name
    $competencies = Competency::select('id', 'name', 'description_en')
        ->whereNotNull('career_id')
        ->get()
        ->mapWithKeys(fn($c) => [$c->id => ($c->description_en ?: $c->name)]);

    $this->info("🇬🇧 Importando desde Reed para {$competencies->count()} competencias…");

    foreach ($competencies as $competencyId => $competencyName) {

        $this->warn("\n🔎 Buscando: {$competencyName}");

        $totalFound = 0;
        $totalNew   = 0;

        for ($page = 0; $page < $pages; $page++) {

            $response = Http::withBasicAuth(env('REED_API_KEY'), '')
                ->timeout(25)
                ->get('https://www.reed.co.uk/api/1.0/search', [
                    'keywords'      => $competencyName,
                    'resultsToTake' => 100,
                    'resultsToSkip' => $page * 100,
                ]);

            $this->stats['api_hits']++;

            if ($response->failed()) {
                $this->error("❌ Error API para {$competencyName} (page {$page})");
                continue;
            }

            $jobs = $response->json()['results'] ?? [];
            $totalFound += count($jobs);

            if (empty($jobs)) break;

            foreach ($jobs as $job) {

                $externalId = "reed-" . ($job['jobId'] ?? uniqid());

                // ✔ DEDUPE
                $existing = JobOffer::where('external_id', $externalId)
                    ->where('source', 'reed')
                    ->first();

                if ($existing) {
                    $existing->competencies()->syncWithoutDetaching([$competencyId]);
                    $this->stats['skipped']++;
                    continue;
                }

                // 🇬🇧 UK STATIC COUNTRY
                $countryIso = 'GB';
                $country    = CountryNormalizer::normalize('GB');

                // 🏙 UBICACIÓN → BÚSQUEDA CITY
                $locationRaw = trim($job['locationName'] ?? '');
                $cityMatch = City::whereRaw('LOWER(city_ascii) = ?', strtolower($locationRaw))
                    ->orWhereRaw('LOWER(city) = ?', strtolower($locationRaw))
                    ->first();

                if ($cityMatch) {
                    $city = $cityMatch->city;
                    $lat  = $cityMatch->lat;
                    $lng  = $cityMatch->lng;
                    $country = CountryNormalizer::normalize($cityMatch->country);
                } else {
                    // Londres fallback
                    $fallback = $this->fallbackCapital($countryIso);
                    $city     = $fallback['city'];
                    $lat      = $fallback['lat'];
                    $lng      = $fallback['lng'];
                    $country  = $fallback['country'];
                }

                $publishedAt = $this->parseReedDate($job['date'] ?? null);

                // ✔ CREAR OFERTA
                $offer = JobOffer::create([
                    'title'          => $job['jobTitle'] ?? '',
                    'company'        => $job['employerName'] ?? '',
                    'country'        => $country,
                    'region'         => RegionHelper::fromCountry($country),
                    'city'           => $city,
                    'latitude'       => $lat,
                    'longitude'      => $lng,
                    'modality'       => 'no_remote',

                    'salary_min'     => $job['minimumSalary'] ?? null,
                    'salary_max'     => $job['maximumSalary'] ?? null,
                    'currency'       => null,
                    'compensation_type' => null,

                    'source'         => 'reed',
                    'external_id'    => $externalId,
                    'url'            => $job['jobUrl'] ?? null,
                    'search_query'   => $competencyName,
                    'published_at'   => $publishedAt,
                ]);

                $offer->competencies()->syncWithoutDetaching([$competencyId]);
                $totalNew++;
                $this->stats['mapped']++;
            }
        }

        // 📊 MÉTRICA ÚNICA POR COMPETENCIA
        CompetencyMetric::updateOrCreate(
            [
                'competency_id' => $competencyId,
                'run_date'      => now()->toDateString(),
                'source'        => 'reed',
            ],
            [
                'competency_name'     => $competencyName,
                'jobs_found_count'    => $totalFound,
                'jobs_new_count'      => $totalNew,
                'countries_breakdown' => [],
                'modality_breakdown'  => [],
            ]
        );

        $this->info("✔ {$competencyName}: {$totalNew} nuevas / {$totalFound} encontradas");
    }

    $this->info("\n🟢 REED COMPLETADO");
    $this->info("API Hits: {$this->stats['api_hits']}");
    $this->info("Nuevas: {$this->stats['mapped']}");
    $this->info("Saltadas: {$this->stats['skipped']}");
}


    /**
     * 📅 Convierte fecha DD/MM/YYYY a Carbon
     */
    private function parseReedDate(?string $date)
    {
        if (!$date) return now();

        $parts = explode('/', $date);

        if (count($parts) === 3) {
            [$day, $month, $year] = $parts;
            return Carbon::createFromFormat('Y-m-d', "$year-$month-$day");
        }

        return now();
    }

    /**
     * 🌐 Capital fallback por ISO2
     */
    public static function fallbackCapital(string $iso2): array
    {
        $iso2 = strtoupper($iso2);

        $capitals = [
            'GB' => ['city'=>'London','lat'=>51.5072,'lng'=>-0.1276,'country'=>'Reino Unido'],
            'UNK'=> ['city'=>'Unknown','lat'=>0,'lng'=>0,'country'=>'Desconocido']
        ];

        return $capitals[$iso2] ?? $capitals['UNK'];
    }
}
