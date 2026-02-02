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

class WantedlyByMethodologiesCommand extends Command
{
    protected $signature = 'wantedly:methodologies {--pages=1} {--lang=es}';
    protected $description = '🇯🇵 Importa ofertas desde Wantedly (JP/SG) por metodología, con traducción, modalidad, país, región y geolocalización.';

    protected $stats = [
        'api_hits'   => 0,
        'fallback'   => 0,
        'mapped'     => 0,
        'skipped'    => 0,
        'translated' => 0,
    ];

    protected static array $translationCache = [];

    // ✔️ ISO2 como Wantedly Languages
    protected $capitalMap = [
        'JP' => ['city' => 'Tokio',     'lat' => 35.6895, 'lng' => 139.6917],
        'SG' => ['city' => 'Singapur',  'lat' => 1.3521,  'lng' => 103.8198],
    ];

    public function handle()
    {
        $pages      = (int) $this->option('pages');
        $targetLang = strtolower($this->option('lang'));

        $methodologies = Methodology::whereIn('methodologies.id', function ($q) {
            $q->select('course_methodology.methodology_id')
                ->from('course_methodology')
                ->join('career_course', 'career_course.course_id', '=', 'course_methodology.course_id');
        })->pluck('name', 'id');

        $this->info("🌏 Importando Wantedly por metodologías → Traduciendo: {$targetLang}");

        foreach ($methodologies as $methId => $methName) {

            $totalFound = $totalNew = 0;
            $countries  = [];
            $modalities = [];

            $this->warn("🔎 Metodología: {$methName}");

            for ($page = 1; $page <= $pages; $page++) {

                $url = "https://www.wantedly.com/api/v1/projects?keyword=" . urlencode($methName) . "&page={$page}";

                try {

                    $response = Http::timeout(25)->get($url);
                    if ($response->failed()) continue;

                    $results = $response->json('data') ?? [];
                    $totalFound += count($results);

                    foreach ($results as $job) {

                        $externalId = $job['id'] ?? null;

                        // ✔️ evitar duplicados
                        $existing = JobOffer::where('source','Wantedly')
                                            ->where('external_id',$externalId)
                                            ->first();

                        // ✔️ pivot methodology_job
                        if ($existing) {
                            $existing->methodologies()->syncWithoutDetaching([$methId]);
                            continue;
                        }

                        // ----------------------------------------
                        //   DATOS BASE
                        // ----------------------------------------
                        $title     = $job['title'] ?? 'N/A';
                        $company   = $job['company']['name'] ?? null;
                        $desc      = strip_tags($job['description'] ?? '');
                        $cityRaw   = $job['location'] ?? 'Tokyo';
                        $urlJob    = "https://www.wantedly.com/projects/" . ($job['id'] ?? '');
                        $published = isset($job['published_at'])
                            ? Carbon::parse($job['published_at'])
                            : now();

                        // ----------------------------------------
                        //   PAÍS
                        // ----------------------------------------
                        $countryIso = $this->detectCountryFromCity($cityRaw);
                        $country = CountryNormalizer::normalize($this->detectCountryFromCity($cityRaw));

                        $region     = RegionHelper::fromCountry($country);

                        // ----------------------------------------
                        //   MODALIDAD
                        // ----------------------------------------
                        $modality = $this->detectModality($title, $desc, $cityRaw);

                        // ----------------------------------------
                        //   GEOLOCALIZACIÓN
                        // ----------------------------------------
                        [$city, $latitude, $longitude] =
                            $this->getCoordsFromCountry($cityRaw, $countryIso);

                        if (!$latitude || !$longitude) {
                            if (isset($this->capitalMap[$countryIso])) {
                                $capital = $this->capitalMap[$countryIso];
                                $city      = $capital['city'];
                                $latitude  = $capital['lat'];
                                $longitude = $capital['lng'];
                                $this->stats['fallback']++;
                            }
                        }

                        // ----------------------------------------
                        //   TRADUCCIÓN
                        // ----------------------------------------
                        if ($this->stats['translated'] % 5 === 0) {
                            usleep(800000);
                        }

                        $titleTranslated = $this->translateText($title, 'auto', $targetLang);
                        $descTranslated  = $this->translateText($desc,  'auto', $targetLang);

                        // ----------------------------------------
                        //   SKILLS Y PERFIL (igual que languages)
                        // ----------------------------------------
                        $experience     = $this->extractExperience($desc);
                        $education      = $this->extractEducation($desc);
                        $certifications = $this->extractCertifications($desc);
                        $skills         = $this->extractSkills($desc);

                        // ----------------------------------------
                        //   GUARDAR OFERTA
                        // ----------------------------------------
                        $offer = JobOffer::create([
                            'title'            => $title,
                            'title_es'         => $targetLang === 'es' ? $titleTranslated : null,
                            'title_en'         => $targetLang === 'en' ? $titleTranslated : null,

                            'company'          => $company,
                            'country'          => $country,
                            'region'           => $region,
                            'city'             => $city,
                            'latitude'         => $latitude,
                            'longitude'        => $longitude,
                            'modality'         => $modality,

                            'salary_min'       => null,
                            'salary_max'       => null,
                            'currency'         => null,

                            'experience_level' => $experience,
                            'education_level'  => $education,
                            'certifications'   => $certifications,
                            'skills'           => $skills,
                            'requirements'     => $desc,

                            'source'           => 'Wantedly',
                            'external_id'      => $externalId,
                            'url'              => $urlJob,
                            'search_query'     => $methName,

                            'description'      => $desc,
                            'description_es'   => $targetLang === 'es' ? $descTranslated : null,
                            'description_en'   => $targetLang === 'en' ? $descTranslated : null,

                            'published_at'     => $published,
                        ]);

                        // ✔️ pivot methodology_job
                        $offer->methodologies()->syncWithoutDetaching([$methId]);

                        // ----------------------------------------
                        //   MÉTRICAS
                        // ----------------------------------------
                        $totalNew++;
                        $countries[$country] = ($countries[$country] ?? 0) + 1;
                        $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;

                        $this->stats['translated']++;
                    }

                } catch (\Throwable $e) {
                    Log::error("❌ Error Wantedly metodología {$methName} página {$page}: " . $e->getMessage());
                }
            }

            // GUARDAR MÉTRICAS
            MethodologyMetric::updateOrCreate(
                [
                    'methodology_id' => $methId,
                    'run_date'       => Carbon::today(),
                    'source'         => 'Wantedly',
                ],
                [
                    'methodology_name'     => $methName,
                    'jobs_found_count'     => $totalFound,
                    'jobs_new_count'       => $totalNew,
                    'countries_breakdown'  => $countries,
                    'modality_breakdown'   => $modalities,
                ]
            );

            $this->info("✔️ {$methName}: {$totalNew} nuevas | 🌍 {$totalFound} encontradas");
        }

        $this->info("\n🎯 FIN DEL PROCESO");
    }

    // ----------------------------------------
    //   DETECT COUNTRY
    // ----------------------------------------
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

    // ----------------------------------------
    //   MODALIDAD
    // ----------------------------------------
    protected function detectModality(string $title, string $description, ?string $city = null): string
    {
        $text = strtolower($title . ' ' . $description . ' ' . ($city ?? ''));

        return match (true) {
            str_contains($text, 'full remote'),
            str_contains($text, '完全リモート'),
            str_contains($text, 'work from anywhere'),
            str_contains($text, '100% remote') => 'remote',

            str_contains($text, 'hybrid'),
            str_contains($text, 'リモート可'),
            str_contains($text, 'partly remote') => 'hybrid',

            default => 'no_precisa',
        };
    }

    // ----------------------------------------
    //   GEOLOCALIZACIÓN
    // ----------------------------------------
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

        } catch (\Throwable $t) {
            Log::warning("🌍 Error geocodificando {$city}, {$country}: " . $t->getMessage());
        }

        return [null, null];
    }

    // -------------------------------
    //   EXTRACT EXPERIENCE
    // -------------------------------
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

    // -------------------------------
    //   EDUCATION
    // -------------------------------
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

    // -------------------------------
    //   CERTIFICATIONS
    // -------------------------------
    protected function extractCertifications(string $text): ?string
    {
        $t = strtolower($text);
        $found = [];

        foreach (['aws','azure','google cloud','scrum','pmp','cisco','ccna','itil'] as $cert) {
            if (str_contains($t, $cert)) $found[] = strtoupper($cert);
        }

        return $found ? implode(', ', $found) : null;
    }

    // -------------------------------
    //   SKILLS
    // -------------------------------
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

