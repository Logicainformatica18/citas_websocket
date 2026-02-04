<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Technology;
use App\Models\JobOffer;
use App\Models\TechnologyMetric;
use App\Models\City;
use App\Helpers\CountryNormalizer;
use Carbon\Carbon;
use App\Services\ScraperRunService;

class ReedByTechnologiesCommand extends Command
{
    protected $signature = 'reed:technologies {--pages=1}';
    protected $description = '🇬🇧 Importa ofertas laborales desde Reed UK por tecnología.';

    protected $stats = [
        'api_hits'  => 0,
        'mapped'    => 0,
        'skipped'   => 0,
    ];
 protected function detectModality(string $location, string $desc, string $title): string
{
    $text = strtolower($location . ' ' . $desc . ' ' . $title);

    return match (true) {

        // 🌍 REMOTO
        str_contains($text, 'remote'),
        str_contains($text, 'work from home'),
        str_contains($text, 'home based'),
        str_contains($text, 'fully remote'),
        str_contains($text, 'hybrid remote')
            => 'remote',

        // 🧩 HÍBRIDO
        str_contains($text, 'hybrid')
            => 'hybrid',

        // 🏢 PRESENCIAL
        str_contains($text, 'onsite'),
        str_contains($text, 'on-site'),
        str_contains($text, 'office based'),
        str_contains($text, 'in office')
            => 'presencial',

        // ❓ NO PRECISA
        default => 'no_precisa',
    };
}
  public function handle()
{
    // ▶️ INICIAR RUN
    $run = ScraperRunService::start(
        $this->signature,
        'Reed',
        'technologies'
    );

    try {

        // 🔹 Última tecnología procesada (cursor)
        $lastTechnologyId = TechnologyMetric::where('source', 'reed')
            ->orderByDesc('created_at')
            ->value('technology_id');

        // 🔹 Query base (solo tecnologías ISIL)
        $baseQuery = Technology::whereIn('technologies.id', function ($q) {
                $q->select('course_technology.technology_id')
                  ->from('course_technology')
                  ->join('career_course', 'career_course.course_id', '=', 'course_technology.course_id');
            })
            ->orderBy('technologies.id');

        $technologiesQuery = clone $baseQuery;

        if ($lastTechnologyId) {
            $technologiesQuery->where('technologies.id', '>', $lastTechnologyId);
        }

        $technologies = $technologiesQuery->get();

        // 🔁 Reinicio automático
        if ($technologies->isEmpty()) {
            $technologies = $baseQuery->get();
        }

        $this->info("🇬🇧 Importando desde Reed para {$technologies->count()} tecnologías…");

        // 🔢 CONTADORES GLOBALES
        $totalFoundAll    = 0;
        $totalInsertedAll = 0;
        $totalSkippedAll  = 0;

        foreach ($technologies as $technology) {

            $technologyId   = $technology->id;
            $technologyName = $technology->name;

            $this->warn("\n🔎 Buscando tecnología: {$technologyName}");

            $totalFound = 0;
            $totalNew   = 0;

            for ($page = 0; $page < (int) $this->option('pages'); $page++) {

                // 🌐 API REED
                $response = Http::withBasicAuth(env('REED_API_KEY'), '')
                    ->get('https://www.reed.co.uk/api/1.0/search', [
                        'keywords'      => $technologyName,
                        'resultsToTake' => 100,
                        'resultsToSkip' => $page * 100,
                    ]);

                $this->stats['api_hits']++;

                if ($response->failed()) {
                    $this->error("❌ Error API {$technologyName}, page {$page}");
                    $totalSkippedAll++;
                    continue;
                }

                $jobs = $response->json()['results'] ?? [];

                if (empty($jobs)) {
                    break;
                }

                foreach ($jobs as $job) {

                    $totalFound++;
                    $totalFoundAll++;

                    $externalId = 'reed-' . $job['jobId'];

                    // 🔁 DEDUPE
                    $existing = JobOffer::where('external_id', $externalId)
                        ->where('source', 'reed')
                        ->first();

                    if ($existing) {
                        $existing->technologies()
                            ->syncWithoutDetaching([$technologyId]);
                        $this->stats['skipped']++;
                        $totalSkippedAll++;
                        continue;
                    }

                    // 🇬🇧 UK
                    $countryIso = 'GB';
                    $country    = CountryNormalizer::normalize('GB');

                    // 🏙️ UBICACIÓN
                    $locationRaw = $job['locationName'] ?? null;

                    $cityMatch = City::where('city_ascii', $locationRaw)
                        ->orWhere('city', $locationRaw)
                        ->first();

                    if ($cityMatch) {
                        $city    = $cityMatch->city;
                        $lat     = $cityMatch->lat;
                        $lng     = $cityMatch->lng;
                        $country = CountryNormalizer::normalize($cityMatch->country);
                    } else {
                        $fallback = $this->fallbackCapital($countryIso);
                        $city     = $fallback['city'];
                        $lat      = $fallback['lat'];
                        $lng      = $fallback['lng'];
                        $country  = $fallback['country'];
                    }

                    // 🧭 MODALIDAD
                    $modality = $this->detectModality(
                        $locationRaw ?? '',
                        $job['jobDescription'] ?? '',
                        $job['jobTitle'] ?? ''
                    );

                    // 📅 FECHA
                    $publishedAt = $this->parseReedDate($job['date'] ?? null);

                    // 💾 CREAR OFERTA
                    $offer = JobOffer::create([
                        'title'        => $job['jobTitle'] ?? '',
                        'company'      => $job['employerName'] ?? '',
                        'country'      => $country,
                        'city'         => $city,
                        'latitude'     => $lat,
                        'longitude'    => $lng,
                        'modality'     => $modality,
                        'salary_min'   => $job['minimumSalary'] ?? null,
                        'salary_max'   => $job['maximumSalary'] ?? null,
                        'source'       => 'reed',
                        'external_id'  => $externalId,
                        'url'          => $job['jobUrl'] ?? null,
                        'search_query' => $technologyName,
                        'published_at' => $publishedAt,
                    ]);

                    // 🔗 PIVOT
                    $offer->technologies()
                        ->syncWithoutDetaching([$technologyId]);

                    $totalNew++;
                    $totalInsertedAll++;
                    $this->stats['mapped']++;
                }
            }

            // 📊 MÉTRICA DIARIA (UNA POR TECNOLOGÍA)
            TechnologyMetric::updateOrCreate(
                [
                    'technology_id' => $technologyId,
                    'run_date'      => now()->toDateString(),
                    'source'        => 'reed',
                ],
                [
                    'technology_name' => $technologyName,
                    'jobs_found_count'=> $totalFound,
                    'jobs_new_count'  => $totalNew,
                    'updated_at'      => now(),
                ]
            );
        }

        // ✅ RUN OK
        ScraperRunService::success(
            $run,
            $totalFoundAll,
            $totalInsertedAll,
            $totalSkippedAll
        );

        $this->info("\n🟢 REED (TECNOLOGÍAS) COMPLETADO");

    } catch (\Throwable $e) {

        // ❌ RUN FAILED
        ScraperRunService::failed($run, $e);
        throw $e;
    }
}




    // ---- Helpers ----

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
