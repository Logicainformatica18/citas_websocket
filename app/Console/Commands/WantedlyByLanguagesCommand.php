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
use App\Helpers\CountryNormalizer;
use App\Helpers\RegionHelper;
use App\Services\ScraperRunService;

class WantedlyByLanguagesCommand extends Command
{
    protected $signature = 'wantedly:languages {--lang=es}';

    protected $description = '🇯🇵 Importa ofertas desde Wantedly por lenguaje (stream real + dedupe).';

    protected $stats = [
        'api_hits'   => 0,
        'fallback'   => 0,
        'mapped'     => 0,
        'skipped'    => 0,
        'translated' => 0,
    ];

    protected static array $translationCache = [];

    protected $capitalMap = [
        'JP' => ['city' => 'Tokio',    'lat' => 35.6895, 'lng' => 139.6917],
        'SG' => ['city' => 'Singapur', 'lat' => 1.3521,  'lng' => 103.8198],
    ];

    public function handle()
    {
        /* ===============================
           INIT RUN
        =============================== */
        $run = ScraperRunService::start(
            $this->signature,
            'Wantedly',
            'languages'
        );

        try {

            $targetLang = strtolower($this->option('lang', 'es'));

            /* ===============================
               CURSOR
            =============================== */
            $lastLanguageId = LanguageMetric::where('source', 'wantedly')
                ->orderByDesc('created_at')
                ->value('language_id');

            /* ===============================
               BASE QUERY (INMUTABLE)
            =============================== */
            $baseQuery = Language::whereIn('languages.id', function ($q) {
                    $q->select('cl.language_id')
                        ->from('course_language as cl')
                        ->join('career_course as cc', 'cc.course_id', '=', 'cl.course_id');
                })
                ->orderBy('languages.id');

            /* ===============================
               QUERY CON CURSOR (CLONADA)
            =============================== */
            $query = clone $baseQuery;

            if ($lastLanguageId) {
                $query->where('languages.id', '>', $lastLanguageId);
            }

            $languages = $query->get();

            // 🔁 reinicio real
            if ($languages->isEmpty()) {
                $this->warn('🔁 Cursor llegó al final, reiniciando lenguajes');
                $languages = $baseQuery->get();
            }

            $this->info("🌏 Wantedly → {$languages->count()} lenguajes | traducción: {$targetLang}");

            if ($languages->isEmpty()) {
                $this->error('❌ No se encontraron lenguajes PE ISIL');
                return Command::FAILURE;
            }

            $totalFoundAll    = 0;
            $totalInsertedAll = 0;
            $totalSkippedAll  = 0;

            /* ===============================
               LOOP POR LENGUAJE
            =============================== */
            foreach ($languages as $language) {

                $languageId   = $language->id;
                $languageName = $language->name;

                $this->warn("\n💡 Lenguaje: {$languageName}");

                $page = 1;
                $emptyPages = 0;
                $maxEmptyPages = 2;

                $totalFound = 0;
                $totalNew   = 0;
                $countries  = [];
                $modalities = [];

                while ($page <= 20) {

                    $url = "https://www.wantedly.com/api/v1/projects?keyword="
                        . urlencode($languageName)
                        . "&page={$page}";

                    $response = Http::timeout(25)->get($url);
                    $this->stats['api_hits']++;

                    if ($response->failed()) break;

                    $results = $response->json('data') ?? [];
                    if (empty($results)) break;

                    $newInPage = 0;
                    $totalFound     += count($results);
                    $totalFoundAll += count($results);

                    foreach ($results as $job) {

                        $externalId = $job['id'] ?? null;
                        if (!$externalId) continue;

                        $existing = JobOffer::where('source', 'wantedly')
                            ->where('external_id', $externalId)
                            ->first();

                        if ($existing) {
                            $existing->languages()
                                ->syncWithoutDetaching([$languageId]);
                            $totalSkippedAll++;
                            continue;
                        }

                        /* ========= BASE ========= */
                        $title     = $job['title'] ?? 'N/A';
                        $company   = $job['company']['name'] ?? null;
                        $desc      = strip_tags($job['description'] ?? '');
                        $cityRaw   = $job['location'] ?? 'Tokyo';
                        $urlJob    = "https://www.wantedly.com/projects/{$externalId}";
                        $published = isset($job['published_at'])
                            ? Carbon::parse($job['published_at'])
                            : now();

                        /* ========= GEO ========= */
                        $countryIso = $this->detectCountryFromCity($cityRaw);
                        $country    = CountryNormalizer::normalize($countryIso);
                        $region     = RegionHelper::fromCountry($country);
                        $modality   = $this->detectModality($title, $desc, $cityRaw);

                        [$city, $lat, $lng] = $this->getCoordsFromCountry($cityRaw, $countryIso);

                        if ((!$lat || !$lng) && isset($this->capitalMap[$countryIso])) {
                            $cap  = $this->capitalMap[$countryIso];
                            $city = $cap['city'];
                            $lat  = $cap['lat'];
                            $lng  = $cap['lng'];
                            $this->stats['fallback']++;
                        }

                        if ($modality === 'remote') {
                            $lat = $lng = null;
                        }

                        /* ========= TRADUCCIÓN ========= */
                        $titleTranslated = $this->translateCached($title, $targetLang);
                        $descTranslated  = $this->translateCached($desc,  $targetLang);

                        /* ========= CREAR ========= */
                        $offer = JobOffer::create([
                            'title'          => $title,
                            'title_es'       => $targetLang === 'es' ? $titleTranslated : null,
                            'title_en'       => $targetLang === 'en' ? $titleTranslated : null,
                            'company'        => $company,
                            'country'        => $country,
                            'region'         => $region,
                            'city'           => $city,
                            'latitude'       => $lat,
                            'longitude'      => $lng,
                            'modality'       => $modality,
                            'requirements'   => $desc,
                            'source'         => 'wantedly',
                            'external_id'    => $externalId,
                            'url'            => $urlJob,
                            'search_query'   => $languageName,
                            'description'    => $desc,
                            'description_es' => $targetLang === 'es' ? $descTranslated : null,
                            'description_en' => $targetLang === 'en' ? $descTranslated : null,
                            'published_at'   => $published,
                        ]);

                        $offer->languages()
                            ->syncWithoutDetaching([$languageId]);

                        $newInPage++;
                        $totalNew++;
                        $totalInsertedAll++;

                        $countries[$country]   = ($countries[$country] ?? 0) + 1;
                        $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;
                    }

                    if ($newInPage === 0) {
                        $emptyPages++;
                        if ($emptyPages >= $maxEmptyPages) break;
                    } else {
                        $emptyPages = 0;
                    }

                    $page++;
                    sleep(1);
                }

                /* ========= MÉTRICA ========= */
                LanguageMetric::updateOrCreate(
                    [
                        'language_id' => $languageId,
                        'run_date'    => Carbon::today(),
                        'source'      => 'wantedly',
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

                $this->info("✔ {$languageName}: {$totalNew} nuevas / {$totalFound}");
            }

            ScraperRunService::success(
                $run,
                $totalFoundAll,
                $totalInsertedAll,
                $totalSkippedAll
            );

            $this->info("\n🟢 WANTEDLY LENGUAJES COMPLETADO");

        } catch (\Throwable $e) {
            ScraperRunService::failed($run, $e);
            throw $e;
        }
    }

    /* ===============================
       HELPERS
    =============================== */

    protected function translateCached(string $text, string $lang): ?string
    {
        if (!isset(self::$translationCache[$text])) {
            if ($this->stats['translated'] > 0 && $this->stats['translated'] % 5 === 0) {
                usleep(800000);
            }
            self::$translationCache[$text] = $this->translateText($text, 'auto', $lang);
            $this->stats['translated']++;
        }
        return self::$translationCache[$text];
    }

    protected function detectCountryFromCity(?string $city): string
    {
        $city = strtolower(trim($city ?? ''));
        return str_contains($city, 'singapore') ? 'SG' : 'JP';
    }

    protected function detectModality(string $title, string $description, ?string $city = null): string
    {
        $t = strtolower($title.' '.$description.' '.($city ?? ''));
        return str_contains($t, 'remote') ? 'remote'
             : (str_contains($t, 'hybrid') ? 'hybrid' : 'no_precisa');
    }

    protected function getCoordsFromCountry(?string $city, ?string $countryCode)
    {
        $found = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city ?? '')])->first();
        return $found ? [$found->city, $found->lat, $found->lng] : [$city, null, null];
    }
    protected function translateText(string $text, string $source, string $target): ?string
{
    $text = trim($text);

    if ($text === '' || $source === $target) {
        return $text;
    }

    try {
        $response = Http::timeout(10)->get(
            'https://translate.googleapis.com/translate_a/single',
            [
                'client' => 'gtx',
                'sl'     => $source,
                'tl'     => $target,
                'dt'     => 't',
                'q'      => $text,
            ]
        );

        if (!$response->ok()) {
            return $text;
        }

        $data = $response->json();

        if (!isset($data[0])) {
            return $text;
        }

        $translated = '';

        foreach ($data[0] as $part) {
            $translated .= $part[0] ?? '';
        }

        return trim($translated) ?: $text;

    } catch (\Throwable $e) {
        Log::warning('🌐 Translate failed', [
            'error' => $e->getMessage(),
            'text'  => mb_substr($text, 0, 80),
        ]);

        return $text; // fallback seguro
    }
}

}
