<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Methodology;
use App\Models\JobOffer;
use App\Models\MethodologyMetric;
use App\Models\City;
use Carbon\Carbon;
use App\Helpers\CountryNormalizer;
use App\Helpers\RegionHelper;
use App\Services\ScraperRunService;

class WantedlyByMethodologiesCommand extends Command
{
    protected $signature = 'wantedly:methodologies {--pages=1} {--lang=es}';
    protected $description = '🇯🇵 Importa ofertas desde Wantedly por metodologías con traducción y geolocalización.';

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
        'methodologies'
    );

    try {

        $targetLang = strtolower($this->option('lang', 'es'));

        /* ===============================
           CURSOR
        =============================== */
        $lastMethodologyId = MethodologyMetric::where('source', 'wantedly')
            ->orderByDesc('created_at')
            ->value('methodology_id');

        /* ===============================
           BASE QUERY (INMUTABLE)
        =============================== */
        $baseQuery = Methodology::whereIn('methodologies.id', function ($q) {
                $q->select('cm.methodology_id')
                  ->from('course_methodology as cm')
                  ->join('career_course as cc', 'cc.course_id', '=', 'cm.course_id');
            })
            ->orderBy('methodologies.id');

        $query = clone $baseQuery;

        if ($lastMethodologyId) {
            $query->where('methodologies.id', '>', $lastMethodologyId);
        }

        $methodologies = $query->get();

        if ($methodologies->isEmpty()) {
            $this->warn('🔁 Cursor agotado, reiniciando metodologías');
            $methodologies = $baseQuery->get();
        }

        $this->info("🌏 Wantedly → {$methodologies->count()} metodologías | traducción: {$targetLang}");

        $totalFoundAll    = 0;
        $totalInsertedAll = 0;
        $totalSkippedAll  = 0;

        /* ===============================
           LOOP POR METODOLOGÍA
        =============================== */
        foreach ($methodologies as $methodology) {

            $methId   = $methodology->id;
            $methName = $methodology->name;

            $this->warn("\n🔎 Metodología: {$methName}");

            $page = 1;
            $emptyPages = 0;
            $maxEmptyPages = 2;

            $totalFound = 0;
            $totalNew   = 0;
            $countries  = [];
            $modalities = [];

            while ($page <= 20) { // safety limit

                $url = "https://www.wantedly.com/api/v1/projects?keyword="
                    . urlencode($methName)
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
                        $existing->methodologies()
                            ->syncWithoutDetaching([$methId]);
                        $totalSkippedAll++;
                        continue;
                    }

                    /* ================= BASE ================= */
                    $title     = $job['title'] ?? 'N/A';
                    $company   = $job['company']['name'] ?? null;
                    $desc      = strip_tags($job['description'] ?? '');
                    $cityRaw   = $job['location'] ?? 'Tokyo';
                    $urlJob    = "https://www.wantedly.com/projects/{$externalId}";
                    $published = isset($job['published_at'])
                        ? Carbon::parse($job['published_at'])
                        : now();

                    /* ================= GEO ================= */
                    $countryIso = $this->detectCountryFromCity($cityRaw);
                    $country    = CountryNormalizer::normalize($countryIso);
                    $region     = RegionHelper::fromCountry($country);
                    $modality   = $this->detectModality($title, $desc, $cityRaw);

                    [$city, $lat, $lng] =
                        $this->getCoordsFromCountry($cityRaw, $countryIso);

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

                    /* ================= TRADUCCIÓN ================= */
                    $titleTranslated = $this->translateCached($title, $targetLang);
                    $descTranslated  = $this->translateCached($desc,  $targetLang);

                    /* ================= EXTRACCIÓN ================= */
                    $experience     = $this->extractExperience($desc);
                    $education      = $this->extractEducation($desc);
                    $certifications = $this->extractCertifications($desc);
                    $skills         = $this->extractSkills($desc);

                    /* ================= CREATE ================= */
                    $offer = JobOffer::create([
                        'title'            => $title,
                        'title_es'         => $targetLang === 'es' ? $titleTranslated : null,
                        'title_en'         => $targetLang === 'en' ? $titleTranslated : null,
                        'company'          => $company,
                        'country'          => $country,
                        'region'           => $region,
                        'city'             => $city,
                        'latitude'         => $lat,
                        'longitude'        => $lng,
                        'modality'         => $modality,
                        'experience_level' => $experience,
                        'education_level'  => $education,
                        'certifications'   => $certifications,
                        'skills'           => $skills,
                        'requirements'     => $desc,
                        'source'           => 'wantedly',
                        'external_id'      => $externalId,
                        'url'              => $urlJob,
                        'search_query'     => $methName,
                        'description'      => $desc,
                        'description_es'   => $targetLang === 'es' ? $descTranslated : null,
                        'description_en'   => $targetLang === 'en' ? $descTranslated : null,
                        'published_at'     => $published,
                    ]);

                    $offer->methodologies()
                        ->syncWithoutDetaching([$methId]);

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

            /* ================= METRIC ================= */
            MethodologyMetric::updateOrCreate(
                [
                    'methodology_id' => $methId,
                    'run_date'       => Carbon::today(),
                    'source'         => 'wantedly',
                ],
                [
                    'methodology_name'    => $methName,
                    'jobs_found_count'    => $totalFound,
                    'jobs_new_count'      => $totalNew,
                    'countries_breakdown' => $countries,
                    'modality_breakdown'  => $modalities,
                    'updated_at'          => now(),
                ]
            );

            $this->info("✔ {$methName}: {$totalNew} nuevas / {$totalFound}");
        }

        ScraperRunService::success(
            $run,
            $totalFoundAll,
            $totalInsertedAll,
            $totalSkippedAll
        );

        $this->info("\n🟢 WANTEDLY METODOLOGÍAS COMPLETADO");

    } catch (\Throwable $e) {
        ScraperRunService::failed($run, $e);
        throw $e;
    }
}


    /* =====================================================
       HELPERS (idénticos a WantedlyByLanguages)
    ===================================================== */

    protected function detectCountryFromCity(?string $city): string
    {
        $city = strtolower(trim($city ?? ''));

        return match (true) {
            str_contains($city, 'singapore'),
            str_contains($city, 'singapur') => 'SG',

            str_contains($city, 'tokyo'),
            str_contains($city, 'osaka'),
            str_contains($city, 'nagoya'),
            str_contains($city, 'japan'),
            str_contains($city, 'japón')    => 'JP',

            default => 'JP',
        };
    }
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

protected function translateText(string $text, string $source, string $target): ?string
{
    $text = trim($text);
    if ($text === '' || $source === $target) return $text;

    try {
        $res = Http::timeout(10)->get(
            'https://translate.googleapis.com/translate_a/single',
            [
                'client' => 'gtx',
                'sl' => $source,
                'tl' => $target,
                'dt' => 't',
                'q'  => $text,
            ]
        );

        if (!$res->ok()) return $text;

        $data = $res->json();
        if (!isset($data[0])) return $text;

        $translated = '';
        foreach ($data[0] as $part) {
            $translated .= $part[0] ?? '';
        }

        return trim($translated) ?: $text;

    } catch (\Throwable $e) {
        Log::warning('Translate failed', [
            'error' => $e->getMessage(),
        ]);
        return $text;
    }
}

    protected function detectModality(string $title, string $description, ?string $city = null): string
    {
        $text = strtolower($title.' '.$description.' '.($city ?? ''));

        return match (true) {
            str_contains($text, 'full remote'),
            str_contains($text, '100% remote'),
            str_contains($text, '完全リモート') => 'remote',

            str_contains($text, 'hybrid'),
            str_contains($text, 'リモート可')   => 'hybrid',

            default => 'no_precisa',
        };
    }

    protected function getCoordsFromCountry(?string $city, ?string $countryCode)
    {
        if ($city && strtolower($city) !== 'remote') {

            $foundCity = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])
                ->when($countryCode, fn($q) =>
                    $q->whereRaw('LOWER(iso2) = ?', [strtolower($countryCode)])
                )
                ->first();

            if ($foundCity) {
                $this->stats['mapped']++;
                return [$foundCity->city, $foundCity->lat, $foundCity->lng];
            }

            [$lat, $lng] = $this->getCoords($city, $countryCode);

            if ($lat && $lng) {
                $this->stats['api_hits']++;
                return [$city, $lat, $lng];
            }
        }

        return [$city, null, null];
    }

    protected function getCoords(?string $city, ?string $country)
    {
        try {
            $res = Http::withHeaders(['User-Agent' => 'LaravelJobScraper/1.0'])
                ->timeout(10)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => "{$city}, {$country}",
                    'format' => 'json',
                    'limit' => 1,
                ]);

            if ($res->ok() && count($res->json()) > 0) {
                $d = $res->json()[0];
                return [(float)$d['lat'], (float)$d['lon']];
            }
        } catch (\Throwable $t) {}

        return [null, null];
    }

    protected function extractExperience(string $text): ?string
    {
        $t = strtolower($text);
        return match (true) {
            str_contains($t, 'senior') => 'senior',
            str_contains($t, 'mid')    => 'mid',
            str_contains($t, 'junior') => 'junior',
            default => null,
        };
    }

    protected function extractEducation(string $text): ?string
    {
        $t = strtolower($text);
        return match (true) {
            str_contains($t, 'bachelor'),
            str_contains($t, 'licencia') => 'bachelor',

            str_contains($t, 'master'),
            str_contains($t, 'maestr')   => 'master',

            str_contains($t, 'phd'),
            str_contains($t, 'doctor')   => 'phd',

            str_contains($t, 'technical'),
            str_contains($t, 'tecnico')  => 'technical',

            default => null,
        };
    }

    protected function extractCertifications(string $text): ?string
    {
        $t = strtolower($text);
        $found = [];

        foreach (['aws','azure','google cloud','scrum','pmp','cisco','ccna','itil'] as $cert) {
            if (str_contains($t, $cert)) $found[] = strtoupper($cert);
        }

        return $found ? implode(', ', $found) : null;
    }

    protected function extractSkills(string $text): ?string
    {
        $t = strtolower($text);
        $skills = [];

        foreach (['python','java','php','laravel','react','vue','sql','docker','aws','git','node'] as $skill) {
            if (str_contains($t, $skill)) $skills[] = strtoupper($skill);
        }

        return $skills ? implode(', ', $skills) : null;
    }
}
