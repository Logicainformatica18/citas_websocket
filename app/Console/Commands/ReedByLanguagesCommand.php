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
use App\Helpers\RegionHelper;
use Carbon\Carbon;
use App\Services\ScraperRunService;
 use App\Services\SourceStatusService;
class ReedByLanguagesCommand extends Command
{
    protected $signature = 'reed:languages {--pages=1}';
    protected $description = '🇬🇧 Importa ofertas laborales desde Reed UK por lenguaje.';

    protected $stats = [
        'api_hits'  => 0,
        'mapped'    => 0,
        'skipped'   => 0,
    ];

public function handle()
{
    // ▶️ INICIAR RUN
    $run = ScraperRunService::start(
        $this->signature,
        'Reed',
        'languages'
    );

    $source = 'reed_languages';

    SourceStatusService::start(
        source: $source,
        runId: $run->id,
        config: [],
        apiUrl: 'https://www.reed.co.uk/api/1.0/search'
    );

    $connectionOk = false;
    $startedAt = now();

    SourceStatusService::progress($source, 0, 0, 0);

    try {

        $lastLanguageId = LanguageMetric::where('source', 'reed')
            ->orderByDesc('created_at')
            ->value('language_id');

        $baseQuery = Language::whereIn('languages.id', function ($q) {
                $q->select('course_language.language_id')
                  ->from('course_language')
                  ->join('career_course', 'career_course.course_id', '=', 'course_language.course_id');
            })
            ->orderBy('languages.id');

        $languagesQuery = clone $baseQuery;

        if ($lastLanguageId) {
            $languagesQuery->where('languages.id', '>', $lastLanguageId);
        }

        $languages = $languagesQuery->get();

        if ($languages->isEmpty()) {
            $languages = $baseQuery->get();
        }

        $this->info("🇬🇧 Importando desde Reed para {$languages->count()} lenguajes…");

        $totalFoundAll    = 0;
        $totalInsertedAll = 0;
        $totalSkippedAll  = 0;

        foreach ($languages as $language) {

            $languageId   = $language->id;
            $languageName = $language->name;

            $this->warn("\n🔎 Buscando: {$languageName}");

            $totalFound = 0;
            $totalNew   = 0;

            for ($page = 0; $page < (int) $this->option('pages'); $page++) {

                $response = Http::withBasicAuth(env('REED_API_KEY'), '')
                    ->get('https://www.reed.co.uk/api/1.0/search', [
                        'keywords'      => $languageName,
                        'resultsToTake' => 100,
                        'resultsToSkip' => $page * 100,
                    ]);

                $this->stats['api_hits']++;

                if ($response->failed()) {
                    SourceStatusService::connectionFailed($source, "{$languageName} page {$page}");
                    $this->error("❌ Error API para {$languageName}, page {$page}");
                    $totalSkippedAll++;
                    continue;
                }

                $connectionOk = true;

                $jobs = $response->json()['results'] ?? [];

                if (empty($jobs)) {
                    break;
                }

                foreach ($jobs as $job) {

                    $totalFound++;
                    $totalFoundAll++;

                    $externalId = 'reed-' . $job['jobId'];

                    if (JobOffer::where('external_id', $externalId)
                        ->where('source', 'reed')
                        ->exists()) {

                        $this->stats['skipped']++;
                        $totalSkippedAll++;
                        continue;
                    }

                    $countryIso = 'GB';
                    $country    = CountryNormalizer::normalize('GB');

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

                    $modality = $this->detectModality(
                        $locationRaw ?? '',
                        $job['jobDescription'] ?? '',
                        $job['jobTitle'] ?? ''
                    );

                    $publishedAt = $this->parseReedDate($job['date'] ?? null);

                    JobOffer::create([
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
                        'search_query' => $languageName,
                        'published_at' => $publishedAt,
                    ]);

                    $totalNew++;
                    $totalInsertedAll++;
                    $this->stats['mapped']++;
                }

                SourceStatusService::progress(
                    $source,
                    $totalFoundAll,
                    $totalInsertedAll,
                    $totalSkippedAll
                );
            }

            LanguageMetric::updateOrCreate(
                [
                    'language_id' => $languageId,
                    'run_date'    => now()->toDateString(),
                    'source'      => 'reed',
                ],
                [
                    'language_name'    => $languageName,
                    'jobs_found_count' => $totalFound,
                    'jobs_new_count'   => $totalNew,
                    'updated_at'       => now(),
                ]
            );
        }

        ScraperRunService::success(
            $run,
            $totalFoundAll,
            $totalInsertedAll,
            $totalSkippedAll
        );

        if ($connectionOk) {
            SourceStatusService::connectionOk($source);
        }

        SourceStatusService::success(
            source: $source,
            runId: $run->id,
            found: $totalFoundAll,
            inserted: $totalInsertedAll,
            skipped: $totalSkippedAll,
            durationSeconds: now()->diffInSeconds($startedAt)
        );

        $this->info("\n🟢 REED COMPLETADO");

    } catch (\Throwable $e) {

        ScraperRunService::failed($run, $e);

        SourceStatusService::failed(
            source: $source,
            runId: $run->id,
            e: $e,
            durationSeconds: now()->diffInSeconds($startedAt)
        );

        throw $e;
    }
}



    /**
     * 📅 Convierte fecha DD/MM/YYYY a Carbon
     */

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
            'US' => ['city'=>'Washington D.C.','lat'=>38.8951,'lng'=>-77.0364,'country'=>'Estados Unidos'],
            'CA' => ['city'=>'Ottawa','lat'=>45.4215,'lng'=>-75.6972,'country'=>'Canadá'],
            'MX' => ['city'=>'Ciudad de México','lat'=>19.4326,'lng'=>-99.1332,'country'=>'México'],
            'ES' => ['city'=>'Madrid','lat'=>40.4168,'lng'=>-3.7038,'country'=>'España'],
            'FR' => ['city'=>'Paris','lat'=>48.8566,'lng'=>2.3522,'country'=>'Francia'],
            'DE' => ['city'=>'Berlin','lat'=>52.52,'lng'=>13.405,'country'=>'Alemania'],
            'JP' => ['city'=>'Tokyo','lat'=>35.6895,'lng'=>139.6917,'country'=>'Japón'],
            'UNK'=> ['city'=>'Unknown','lat'=>0,'lng'=>0,'country'=>'Desconocido']
        ];

        return $capitals[$iso2] ?? $capitals['UNK'];
    }
}
