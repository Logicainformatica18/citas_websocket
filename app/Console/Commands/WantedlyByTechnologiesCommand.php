<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Technology;
use App\Models\JobOffer;
use App\Models\TechnologyMetric;
use App\Models\City;
use Carbon\Carbon;
use App\Helpers\CountryNormalizer;
use App\Helpers\RegionHelper;


class WantedlyByTechnologiesCommand extends Command
{
    protected $signature = 'wantedly:technologies {--pages=1} {--lang=es}';
    protected $description = '🇯🇵 Importa ofertas desde Wantedly (JP/SG) por tecnología, con traducción, modalidad, país, región y geolocalización.';

    protected $stats = [
        'api_hits'   => 0,
        'fallback'   => 0,
        'mapped'     => 0,
        'skipped'    => 0,
        'translated' => 0,
    ];

    protected static array $translationCache = [];

    // ✔️ ISO2 como Languages y Methodologies
    protected $capitalMap = [
        'JP' => ['city' => 'Tokio',     'lat' => 35.6895, 'lng' => 139.6917],
        'SG' => ['city' => 'Singapur',  'lat' => 1.3521,  'lng' => 103.8198],
    ];

 public function handle()
{
    $run = \App\Services\ScraperRunService::start(
        $this->signature,
        'Wantedly',
        'technologies'
    );

    $source = 'wantedly_technologies';

    \App\Services\SourceStatusService::start(
        source: $source,
        runId: $run->id,
        config: [],
        apiUrl: 'https://www.wantedly.com/api/v1/projects'
    );

    $connectionOk = false;
    $startedAt = now();

    \App\Services\SourceStatusService::progress($source, 0, 0, 0);

    try {

        $lastTechnologyId = TechnologyMetric::where('source', 'wantedly')
            ->orderByDesc('created_at')
            ->value('technology_id');

        $baseQuery = Technology::whereIn('technologies.id', function ($q) {
                $q->select('ct.technology_id')
                  ->from('course_technology as ct')
                  ->join('career_course as cc', 'cc.course_id', '=', 'ct.course_id');
            })
            ->orderBy('technologies.id');

        $query = clone $baseQuery;

        if ($lastTechnologyId) {
            $query->where('technologies.id', '>', $lastTechnologyId);
        }

        $technologies = $query->get();

        if ($technologies->isEmpty()) {
            $this->warn('🔁 Cursor agotado, reiniciando tecnologías');
            $technologies = $baseQuery->get();
        }

        $this->info("🌏 Wantedly → {$technologies->count()} tecnologías (SIN traducción)");

        $totalFoundAll    = 0;
        $totalInsertedAll = 0;
        $totalSkippedAll  = 0;

        foreach ($technologies as $technology) {

            $techId   = $technology->id;
            $techName = $technology->name;

            $this->warn("\n🔎 {$techName}");

            $page = 1;
            $emptyPages = 0;
            $maxEmptyPages = 2;

            $totalFound = 0;
            $totalNew   = 0;
            $countries  = [];
            $modalities = [];

            // 🔥 menos páginas
            while ($page <= 5) {

                $url = "https://www.wantedly.com/api/v1/projects?keyword="
                    . urlencode($techName)
                    . "&page={$page}";

                try {

                    $response = Http::timeout(25)->get($url);
                    $this->stats['api_hits']++;

                    if ($response->failed()) {
                        \App\Services\SourceStatusService::connectionFailed($source, $techName);
                        break;
                    }

                    $connectionOk = true;

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
                            $existing->technologies()
                                ->syncWithoutDetaching([$techId]);
                            $totalSkippedAll++;
                            continue;
                        }

                        $title     = $job['title'] ?? 'N/A';
                        $company   = $job['company']['name'] ?? null;
                        $desc      = strip_tags($job['description'] ?? '');
                        $cityRaw   = $job['location'] ?? 'Tokyo';
                        $urlJob    = "https://www.wantedly.com/projects/{$externalId}";
                        $published = isset($job['published_at'])
                            ? Carbon::parse($job['published_at'])
                            : now();

                        $countryIso = $this->detectCountryFromCity($cityRaw);
                        $country    = CountryNormalizer::normalize($countryIso);
                        $region     = RegionHelper::fromCountry($country);
                        $modality   = $this->detectModality($title, $desc, $cityRaw);

                        [$city, $lat, $lng] =
                            $this->getCoordsFromCountry($cityRaw, $countryIso);

                        if ((!$lat || !$lng) && isset($this->capitalMap[$countryIso])) {
                            $c = $this->capitalMap[$countryIso];
                            $city = $c['city'];
                            $lat  = $c['lat'];
                            $lng  = $c['lng'];
                            $this->stats['fallback']++;
                        }

                        if ($modality === 'remote') {
                            $lat = $lng = null;
                        }

                        $experience     = $this->extractExperience($desc);
                        $education      = $this->extractEducation($desc);
                        $certifications = $this->extractCertifications($desc);
                        $skills         = $this->extractSkills($desc);

                        $offer = JobOffer::create([
                            'title'            => $title,
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
                            'search_query'     => $techName,
                            'description'      => $desc,
                            'published_at'     => $published,
                        ]);

                        $offer->technologies()
                            ->syncWithoutDetaching([$techId]);

                        $newInPage++;
                        $totalNew++;
                        $totalInsertedAll++;

                        $countries[$country]   = ($countries[$country] ?? 0) + 1;
                        $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;
                    }

                    // 🔥 progreso en vivo
                    \App\Services\SourceStatusService::progress(
                        $source,
                        $totalFoundAll,
                        $totalInsertedAll,
                        $totalSkippedAll
                    );

                    if ($newInPage === 0) {
                        $emptyPages++;
                        if ($emptyPages >= $maxEmptyPages) break;
                    } else {
                        $emptyPages = 0;
                    }

                    $page++;
                    usleep(300000); // más rápido que sleep(1)

                } catch (\Throwable $e) {
                    Log::error("❌ Wantedly tech {$techName}: ".$e->getMessage());
                    break;
                }
            }

            TechnologyMetric::updateOrCreate(
                [
                    'technology_id' => $techId,
                    'run_date'      => Carbon::today(),
                    'source'        => 'wantedly',
                ],
                [
                    'technology_name'     => $techName,
                    'jobs_found_count'    => $totalFound,
                    'jobs_new_count'      => $totalNew,
                    'countries_breakdown' => $countries,
                    'modality_breakdown'  => $modalities,
                    'updated_at'          => now(),
                ]
            );

            $this->info("✔ {$techName}: {$totalNew} nuevas / {$totalFound}");
        }

        \App\Services\ScraperRunService::success(
            $run,
            $totalFoundAll,
            $totalInsertedAll,
            $totalSkippedAll
        );

        if ($connectionOk) {
            \App\Services\SourceStatusService::connectionOk($source);
        }

        \App\Services\SourceStatusService::success(
            source: $source,
            runId: $run->id,
            found: $totalFoundAll,
            inserted: $totalInsertedAll,
            skipped: $totalSkippedAll,
            durationSeconds: now()->diffInSeconds($startedAt)
        );

    } catch (\Throwable $e) {

        \App\Services\ScraperRunService::failed($run, $e);

        \App\Services\SourceStatusService::failed(
            source: $source,
            runId: $run->id,
            e: $e,
            durationSeconds: now()->diffInSeconds($startedAt)
        );

        throw $e;
    }
}


    // ---------------------------------------------------
    //   DETECT PAÍS
    // ---------------------------------------------------
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
            str_contains($city, 'japón') => 'JP',

            default => 'JP',
        };
    }

    // ---------------------------------------------------
    //   DETECT MODALIDAD
    // ---------------------------------------------------
    protected function detectModality(string $title, string $description, ?string $city=null): string
    {
        $text = strtolower($title.' '.$description.' '.($city ?? ''));

        return match (true) {
            str_contains($text,'full remote'),
            str_contains($text,'work from anywhere'),
            str_contains($text,'完全リモート'),
            str_contains($text,'100% remote') => 'remote',

            str_contains($text,'hybrid'),
            str_contains($text,'リモート可'),
            str_contains($text,'partly remote') => 'hybrid',

            default => 'no_precisa',
        };
    }

    // ---------------------------------------------------
    //   GEOLOCALIZACIÓN
    // ---------------------------------------------------
    protected function getCoordsFromCountry(?string $city, ?string $countryCode)
    {
        if ($city && strtolower($city) !== 'remote') {

            $foundCity = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])
                ->when($countryCode, fn($q)=>$q->whereRaw('LOWER(iso2)=?', [strtolower($countryCode)]))
                ->first();

            if ($foundCity) {
                $this->stats['mapped']++;
                return [$foundCity->city,$foundCity->lat,$foundCity->lng];
            }

            [$lat,$lng] = $this->getCoords($city,$countryCode);

            if ($lat && $lng) {
                $this->stats['api_hits']++;
                return [$city,$lat,$lng];
            }
        }

        return [$city,null,null];
    }

    protected function getCoords(?string $city, ?string $country)
    {
        try {
            $res = Http::withHeaders(['User-Agent'=>'LaravelJobScraper'])
                ->timeout(10)
                ->get('https://nominatim.openstreetmap.org/search',[
                    'q'=>"$city, $country",
                    'format'=>'json',
                    'limit'=>1,
                ]);

            if ($res->ok() && count($res->json()) > 0) {
                $d = $res->json()[0];
                return [(float)$d['lat'], (float)$d['lon']];
            }

        } catch (\Throwable $t){
            Log::warning("🌍 Error geocodificando $city,$country: ".$t->getMessage());
        }

        return [null,null];
    }

    // ---------------------------------------------------
    //   EXPERIENCIA
    // ---------------------------------------------------
    protected function extractExperience(string $text): ?string
    {
        $t = strtolower($text);

        return match(true){
            str_contains($t,'senior')=>'senior',
            str_contains($t,'mid')   =>'mid',
            str_contains($t,'junior')=>'junior',
            default=>null,
        };
    }

    // ---------------------------------------------------
    //   EDUCACIÓN
    // ---------------------------------------------------
    protected function extractEducation(string $text): ?string
    {
        $t = strtolower($text);

        return match(true){
            str_contains($t,'bachelor'),
            str_contains($t,'licencia') =>'bachelor',

            str_contains($t,'master'),
            str_contains($t,'maestr')   =>'master',

            str_contains($t,'phd'),
            str_contains($t,'doctor')   =>'phd',

            str_contains($t,'technical'),
            str_contains($t,'tecnico')  =>'technical',

            default=>null,
        };
    }

    // ---------------------------------------------------
    //   CERTIFICACIONES
    // ---------------------------------------------------
    protected function extractCertifications(string $text): ?string
    {
        $t = strtolower($text);
        $found = [];

        foreach (['aws','azure','google cloud','scrum','pmp','cisco','ccna','itil'] as $cert) {
            if (str_contains($t,$cert)) $found[] = strtoupper($cert);
        }

        return $found ? implode(', ',$found) : null;
    }

    // ---------------------------------------------------
    //   SKILLS
    // ---------------------------------------------------
    protected function extractSkills(string $text): ?string
    {
        $t = strtolower($text);
        $skills = [];

        foreach (['python','java','php','laravel','react','vue','sql','docker','aws','git','node'] as $skill) {
            if (str_contains($t,$skill)) $skills[] = strtoupper($skill);
        }

        return $skills ? implode(', ',$skills) : null;
    }

    // ---------------------------------------------------
    //   TRADUCCIÓN
    // ---------------------------------------------------
    public function translateText(string $text,string $from='auto',string $to='es'): string
    {
        if (empty(trim($text))) return '';

        $text = strip_tags($text);
        $text = preg_replace('/[\x{1F600}-\x{1F64F}]/u','',$text);
        $text = str_replace(["\r","\n","\t"],' ',$text);
        $text = mb_substr($text,0,500);

        $key = md5($text.$from.$to);
        if (isset(self::$translationCache[$key])) return self::$translationCache[$key];

        $mirrors = [
            'https://api.mymemory.translated.net/get',
            'https://translate.astian.org/translate',
            'https://libretranslate.com/translate',
        ];

        foreach ($mirrors as $endpoint){

            try {

                if (str_contains($endpoint,'mymemory')) {

                    $keyParam = env('MYMEMORY_API_KEY');
                    $url = "{$endpoint}?q=".urlencode($text)."&langpair={$from}|{$to}";
                    if ($keyParam) $url .= "&de=$keyParam";

                    $res = Http::retry(2,1000)->timeout(20)->get($url);

                    if ($res->ok()) {
                        $translated = html_entity_decode(
                            $res->json('responseData.translatedText') ?? '',
                            ENT_QUOTES | ENT_HTML5,'UTF-8'
                        );

                        if (!empty($translated) && strtolower($translated) !== strtolower($text)) {
                            return self::$translationCache[$key] = trim($translated);
                        }
                    }

                } else {

                    $res = Http::retry(2,1000)->timeout(20)->post($endpoint,[
                        'q'=>$text,
                        'source'=>$from,
                        'target'=>$to,
                        'format'=>'text',
                    ]);

                    if ($res->ok() && isset($res->json()['translatedText'])) {
                        $translated = $res->json()['translatedText'];
                        if (!empty($translated) && strtolower($translated) !== strtolower($text)) {
                            return self::$translationCache[$key] = trim($translated);
                        }
                    }
                }

            } catch (\Throwable $e){
                Log::warning("⚠️ Error traduciendo: ".$e->getMessage());
            }
        }

        return self::$translationCache[$key] = $text;
    }
}

