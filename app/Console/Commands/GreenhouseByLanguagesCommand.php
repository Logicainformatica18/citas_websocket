<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use App\Models\JobOffer;
use App\Models\City;
use App\Models\Language;
use App\Models\LanguageMetric;

use App\Helpers\RegionHelper;
use App\Helpers\CountryNormalizer;
use App\Services\ScraperRunService;

class GreenhouseByLanguagesCommand extends Command
{
    protected $signature = 'greenhouse:languages {--company=*}';
    protected $description = '🌱 Importa ofertas desde Greenhouse con búsqueda por lenguaje + contexto semántico.';

    public function handle()
    {
        // ▶️ Iniciar RUN del scraper
        $run = ScraperRunService::start(
            $this->signature,
            'Greenhouse',
            'languages'
        );

        try {

            $companies = $this->option('company');

            if (empty($companies)) {
                $this->error("❌ Debes pasar empresas, ejemplo: --company=stripe --company=cloudflare");
                return;
            }

            // 🔢 Contadores globales
            $totalFoundAll    = 0;
            $totalInsertedAll = 0;
            $totalSkippedAll  = 0;

            /*
            |--------------------------------------------------------------------------
            | 🔁 CURSOR CÍCLICO POR LENGUAJE
            |--------------------------------------------------------------------------
            */
            $lastLanguageId = LanguageMetric::where('source', 'Greenhouse')
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
                // 🔁 volver al inicio
                $languages = $baseQuery->get();
            }

            $this->info("🌱 Importando desde Greenhouse para {$languages->count()} lenguajes…");

            /*
            |--------------------------------------------------------------------------
            | 🏢 EMPRESAS
            |--------------------------------------------------------------------------
            */
            foreach ($companies as $companySlug) {

                $this->warn("\n🏢 Empresa: {$companySlug}");

                $url = "https://boards-api.greenhouse.io/v1/boards/{$companySlug}/jobs";

                $response = Http::timeout(20)->get($url);

                if ($response->failed()) {
                    $this->error("❌ No se pudo obtener datos de: {$companySlug}");
                    $totalSkippedAll++;
                    continue;
                }

                $jobs = $response->json('jobs') ?? [];
                $contentAvailable = $this->companyHasContent($jobs);

                $this->line(
                    $contentAvailable
                        ? "✔ Esta empresa SÍ expone descripción"
                        : "⚠ Solo título (sin descripción)"
                );

                /*
                |--------------------------------------------------------------------------
                | 🧠 LOOP POR LENGUAJE
                |--------------------------------------------------------------------------
                */
                foreach ($languages as $language) {

                    $pattern = $language->name;
                    $escaped = preg_quote($pattern, '/');
                    $regex   = "/{$escaped}/i";

                    $resultsForLang = array_filter($jobs, function ($job) use ($regex, $contentAvailable) {

                        $title   = $job['title'] ?? '';
                        $content = $contentAvailable ? ($job['content'] ?? '') : '';

                        return preg_match($regex, $title)
                            || ($contentAvailable && preg_match($regex, $content));
                    });

                    $this->line("\n🔎 {$language->name} → " . count($resultsForLang));

                    $totalFoundAll += count($resultsForLang);
                    $newForLang = 0;

                    foreach ($resultsForLang as $job) {

                        $content     = $contentAvailable ? ($job['content'] ?? '') : '';
                        $title       = $job['title'] ?? 'N/A';
                        $companyName = $job['company_name'] ?? ucfirst($companySlug);
                        $urlJob      = $job['absolute_url'] ?? null;

                        // 📍 Ubicación
                        $loc = strtolower($job['location']['name'] ?? '');

                        // 🌍 País
                        $countryCode = $this->extractCountryCodeOrNull($loc);
                        if (!$countryCode) {
                            $totalSkippedAll++;
                            continue;
                        }

                        $countryFull = CountryNormalizer::normalize($countryCode);

                        // 🏙 Ciudad + coords
                        $cityRaw = $this->extractCity($loc);
                        [$cityClean, $lat, $lng] = $this->getCoords($cityRaw, $countryCode);

                        if (!$lat || !$lng) {
                            $totalSkippedAll++;
                            continue;
                        }

                        // 🧭 Modalidad
                        $modality = $this->detectModality($loc, $content);

                        $externalId = $job['id'];

                        // 🔁 Duplicado
                        $existing = JobOffer::where('external_id', $externalId)
                            ->where('source', 'Greenhouse')
                            ->first();

                        if ($existing) {
                            $existing->languages()->syncWithoutDetaching([$language->id]);
                            $totalSkippedAll++;
                            continue;
                        }

                        // 💾 Crear oferta
                        $offer = JobOffer::create([
                            'title'            => $title,
                            'company'          => $companyName,
                            'country'          => $countryFull,
                            'city'             => $cityClean,
                            'latitude'         => $lat,
                            'longitude'        => $lng,
                            'modality'         => $modality,
                            'experience_level' => $this->extractExperience($content),
                            'education_level'  => $this->extractEducation($content),
                            'skills'           => $this->extractSkills($content),
                            'certifications'   => $this->extractCertifications($content),
                            'requirements'     => strip_tags($content),
                            'source'           => 'Greenhouse',
                            'external_id'      => $externalId,
                            'url'              => $urlJob,
                            'search_query'     => $language->name,
                            'published_at'     => $job['updated_at'] ?? now(),
                            'region'           => RegionHelper::fromCountry($countryFull),
                        ]);

                        $offer->languages()->syncWithoutDetaching([$language->id]);

                        $newForLang++;
                        $totalInsertedAll++;
                    }

                    // 📊 Métrica diaria por lenguaje
                    LanguageMetric::updateOrCreate(
                        [
                            'language_id' => $language->id,
                            'run_date'    => now()->toDateString(),
                            'source'      => 'Greenhouse',
                        ],
                        [
                            'language_name'      => $language->name,
                            'jobs_found_count'   => count($resultsForLang),
                            'jobs_new_count'     => $newForLang,
                            'countries_breakdown'=> [],
                            'modality_breakdown' => [],
                            'updated_at'         => now(),
                        ]
                    );

                    $this->info("✔ {$language->name}: {$newForLang} nuevas");
                }
            }

            // ✅ RUN OK
            ScraperRunService::success(
                $run,
                $totalFoundAll,
                $totalInsertedAll,
                $totalSkippedAll
            );

            $this->info("\n🎯 Greenhouse → Lenguajes finalizado correctamente.");

        } catch (\Throwable $e) {
            ScraperRunService::failed($run, $e);
            throw $e;
        }
    }

    /* =======================================================
     * HELPERS (idénticos, reutilizados)
     * ======================================================= */

    protected function companyHasContent(array $jobs): bool
    {
        foreach ($jobs as $job) {
            if (!empty($job['content'])) return true;
        }
        return false;
    }

    protected function extractCity($loc)
    {
        $parts = explode(',', $loc);
        return trim($parts[0] ?? null);
    }

    protected function extractCountryCodeOrNull($loc)
    {
        if (!$loc) return null;

        $lower = strtolower($loc);

        $map = [
            'united states' => 'us',
            'usa' => 'us',
            'canada' => 'ca',
            'mexico' => 'mx',
            'brazil' => 'br',
            'spain' => 'es',
            'france' => 'fr',
            'germany' => 'de',
            'italy' => 'it',
            'argentina' => 'ar',
            'chile' => 'cl',
            'peru' => 'pe',
            'colombia' => 'co',
            'uk' => 'gb',
            'united kingdom' => 'gb',
            'ireland' => 'ie',
            'australia' => 'au',
            'new zealand' => 'nz',
            'india' => 'in',
            'singapore' => 'sg',
        ];

        foreach ($map as $k => $code) {
            if (str_contains($lower, $k)) return $code;
        }

        return null;
    }

    protected function getCoords($city, $code)
    {
        if (!$city) return [null, null, null];

        $found = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])
            ->whereRaw('LOWER(iso2) = ?', [strtolower($code)])
            ->first();

        if ($found) {
            return [$found->city, $found->lat, $found->lng];
        }

        return [null, null, null];
    }

    protected function detectModality(string $loc, string $desc): string
    {
        $t = strtolower($loc . ' ' . $desc);

        return match (true) {
            str_contains($t, 'remote'),
            str_contains($t, 'work from home'),
            str_contains($t, 'home office'),
            str_contains($t, 'teletrabajo'),
            str_contains($t, 'anywhere') => 'remote',

            str_contains($t, 'hybrid'),
            str_contains($t, 'híbrido'),
            str_contains($t, 'mixto'),
            str_contains($t, 'partial remote') => 'hybrid',

            str_contains($t, 'onsite'),
            str_contains($t, 'office'),
            str_contains($t, 'presencial') => 'presencial',

            default => 'no_precisa',
        };
    }

    protected function extractExperience($text)
    {
        $t = strtolower($text);

        return match (true) {
            str_contains($t, 'senior') => 'senior',
            str_contains($t, 'mid')    => 'mid',
            str_contains($t, 'junior') => 'junior',
            default => null,
        };
    }

    protected function extractEducation($text)
    {
        $t = strtolower($text);

        return match (true) {
            str_contains($t, 'bachelor') => 'bachelor',
            str_contains($t, 'master')   => 'master',
            str_contains($t, 'phd')      => 'phd',
            default => null,
        };
    }

    protected function extractSkills($text)
    {
        $t = strtolower($text);
        $skills = [];

        foreach (['python','java','php','laravel','react','vue','sql','docker','aws','git','node'] as $skill) {
            if (str_contains($t, $skill)) $skills[] = strtoupper($skill);
        }

        return $skills ? implode(', ', $skills) : null;
    }

    protected function extractCertifications($text)
    {
        $t = strtolower($text);
        $found = [];

        foreach (['aws','azure','google cloud','scrum','pmp','ccna','itil'] as $cert) {
            if (str_contains($t, $cert)) $found[] = strtoupper($cert);
        }

        return $found ? implode(', ', $found) : null;
    }
}
