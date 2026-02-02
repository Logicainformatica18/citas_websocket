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

class WantedlyByLanguagesCommand extends Command
{
    protected $signature = 'wantedly:languages {--pages=1} {--lang=es}';
    protected $description = '🇯🇵 Importa ofertas desde Wantedly (JP/SG) por lenguaje, con traducción, modalidad, país, región y geolocalización.';

    protected $stats = [
        'api_hits'   => 0,
        'fallback'   => 0,
        'mapped'     => 0,
        'skipped'    => 0,
        'translated' => 0,
    ];

    protected static array $translationCache = [];

    // ✔️ ISO2 como Adzuna
    protected $capitalMap = [
        'JP' => ['city' => 'Tokio',     'lat' => 35.6895, 'lng' => 139.6917],
        'SG' => ['city' => 'Singapur',  'lat' => 1.3521,  'lng' => 103.8198],
    ];

    public function handle()
    {
        $pages      = (int) $this->option('pages');
        $targetLang = strtolower($this->option('lang', 'es'));

        // ✔️ Igual que Adzuna: solo lenguajes usados en cursos/carreras
        $languages = Language::whereIn('languages.id', function ($q) {
            $q->select('course_language.language_id')
                ->from('course_language')
                ->join('career_course', 'career_course.course_id', '=', 'course_language.course_id');
        })->pluck('name', 'id');

        $this->info("🌏 Importando desde Wantedly → traducción a [{$targetLang}] ...");

        foreach ($languages as $languageId => $languageName) {
            $this->warn("\n💡 Lenguaje: {$languageName}");

            $totalFound = $totalNew = 0;
            $countries  = [];
            $modalities = [];

            for ($page = 1; $page <= $pages; $page++) {

                $url = "https://www.wantedly.com/api/v1/projects?keyword=" . urlencode($languageName) . "&page={$page}";

                try {
                    $response = Http::timeout(25)->get($url);
                    if ($response->failed()) {
                        $this->error("❌ Error en página {$page}");
                        continue;
                    }

                    $results = $response->json('data') ?? [];
                    $totalFound += count($results);

                    foreach ($results as $job) {

                        $externalId = $job['id'] ?? null;
                        $existing   = JobOffer::where('source','Wantedly')
                                            ->where('external_id',$externalId)
                                            ->first();

                        // ✔️ Si ya existe → solo agregar pivot
                        if ($existing) {
                            $existing->languages()->syncWithoutDetaching([$languageId]);
                            continue;
                        }

                        // ---------------------------
                        //   EXTRAER DATOS BASE
                        // ---------------------------
                        $title      = $job['title'] ?? 'N/A';
                        $company    = $job['company']['name'] ?? null;
                        $desc       = strip_tags($job['description'] ?? '');
                        $cityRaw    = $job['location'] ?? 'Tokyo';
                        $urlJob     = "https://www.wantedly.com/projects/" . ($job['id'] ?? '');
                        $published  = isset($job['published_at'])
                                        ? Carbon::parse($job['published_at'])
                                        : now();

                        // ---------------------------
                        //     NORMALIZACIÓN PAÍS
                        // ---------------------------
                        $countryIso = strtoupper($this->detectCountryFromCity($cityRaw)); // JP | SG
                     $country = CountryNormalizer::normalize($this->detectCountryFromCity($cityRaw));

                        $region     = RegionHelper::fromCountry($country);

                        // ---------------------------
                        //         MODALIDAD
                        // ---------------------------
                        $modality = $this->detectModality($title, $desc, $cityRaw);

                        // ---------------------------
                        //    GEOLOCALIZACIÓN
                        // ---------------------------
                        [$city, $latitude, $longitude] = $this->getCoordsFromCountry($cityRaw, $countryIso);

                        // ✔️ fallback capital si no hay coordenadas
                        if (!$latitude || !$longitude) {
                            if (isset($this->capitalMap[$countryIso])) {
                                $capital = $this->capitalMap[$countryIso];

                                $city      = $capital['city'];
                                $latitude  = $capital['lat'];
                                $longitude = $capital['lng'];
                                $this->stats['fallback']++;
                            }
                        }

                        // ✔️ Si es remoto → lat/lng nulo
                        if ($modality === 'remote') {
                            $latitude  = null;
                            $longitude = null;
                        }

                        // ---------------------------
                        //      TRADUCCIÓN
                        // ---------------------------
                        if ($this->stats['translated'] % 5 === 0) usleep(800000);

                        $titleTranslated = $this->translateText($title, 'auto', $targetLang);
                        $descTranslated  = $this->translateText($desc,  'auto', $targetLang);

                        // ---------------------------
                        //   EXTRAER SKILLS / PERFIL / CERTIFICADOS
                        //   (igual que Adzuna)
                        // ---------------------------
                        $experience      = $this->extractExperience($desc);
                        $education       = $this->extractEducation($desc);
                        $certifications  = $this->extractCertifications($desc);
                        $skills          = $this->extractSkills($desc);

                        // ---------------------------
                        //       GUARDAR OFERTA
                        // ---------------------------
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

                            // Wantedly NO tiene salario, pero dejamos preparado:
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
                            'search_query'     => $languageName,

                            'description'      => $desc,
                            'description_es'   => $targetLang === 'es' ? $descTranslated : null,
                            'description_en'   => $targetLang === 'en' ? $descTranslated : null,

                            'published_at'     => $published,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);

                        // ✔️ Asociar lenguaje
                        $offer->languages()->syncWithoutDetaching([$languageId]);

                        $totalNew++;
                        $countries[$country] = ($countries[$country] ?? 0) + 1;
                        $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;

                        $this->stats['translated']++;
                    }

                    sleep(1);

                } catch (\Throwable $e) {
                    Log::error("⚠️ Error Wantedly {$languageName} página {$page}: " . $e->getMessage());
                }
            }

            // ---------------------------
            //     MÉTRICAS DIARIAS
            // ---------------------------
            LanguageMetric::updateOrCreate(
                [
                    'language_id' => $languageId,
                    'run_date'    => Carbon::today(),
                    'source'      => 'Wantedly',
                ],
                [
                    'language_name'       => $languageName,
                    'jobs_found_count'    => $totalFound,
                    'jobs_new_count'      => $totalNew,
                    'countries_breakdown' => $countries,
                    'modality_breakdown'  => $modalities,
                ]
            );

            $this->info("✅ {$languageName}: {$totalNew} nuevas | 🌍 {$totalFound} encontradas");
        }

        $this->info("\n🎯 COMPLETADO");
    }

    // --------------------------
    //  DETECT COUNTRY
    // --------------------------
    protected function detectCountryFromCity(?string $city): string
    {
        $city = strtolower(trim($city ?? ''));

        return match (true) {
            str_contains($city, 'singapore') => 'SG',
            str_contains($city, 'singapur')  => 'SG',
            str_contains($city, 'tokyo'),
            str_contains($city, 'osaka'),
            str_contains($city, 'nagoya'),
            str_contains($city, 'japan'),
            str_contains($city, 'japón')     => 'JP',
            default                          => 'JP',
        };
    }

    // --------------------------
    //  DETECT MODALITY
    // --------------------------
    protected function detectModality(string $title, string $description, ?string $city = null): string
    {
        $text = strtolower($title.' '.$description.' '.($city ?? ''));

        return match (true) {
            str_contains($text, 'full remote'),
            str_contains($text, 'work from anywhere'),
            str_contains($text, '100% remote'),
            str_contains($text, '完全リモート')   => 'remote',

            str_contains($text, 'hybrid'),
            str_contains($text, 'リモート可'),
            str_contains($text, 'partly remote') => 'hybrid',

            default => 'no_precisa',
        };
    }

    // --------------------------
    //   EXTRACT EXPERIENCE
    // --------------------------
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

    // --------------------------
    //   EXTRACT EDUCATION
    // --------------------------
    protected function extractEducation(string $text): ?string
    {
        $t = strtolower($text);

        return match (true) {
            str_contains($t, 'bachelor'),
            str_contains($t, 'licencia') => 'bachelor',

            str_contains($t, 'master'),
            str_contains($t, 'maestr') => 'master',

            str_contains($t, 'phd'),
            str_contains($t, 'doctor') => 'phd',

            str_contains($t, 'technical'),
            str_contains($t, 'tecnico') => 'technical',

            default => null,
        };
    }

    // --------------------------
    //   EXTRACT CERTIFICATIONS
    // --------------------------
    protected function extractCertifications(string $text): ?string
    {
        $t = strtolower($text);
        $found = [];

        foreach (['aws','azure','google cloud','scrum','pmp','cisco','ccna','itil'] as $cert) {
            if (str_contains($t, $cert)) $found[] = strtoupper($cert);
        }

        return $found ? implode(', ', $found) : null;
    }

    // --------------------------
    //   EXTRACT SKILLS
    // --------------------------
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

