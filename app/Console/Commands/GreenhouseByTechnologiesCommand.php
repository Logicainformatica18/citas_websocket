<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
 
use App\Models\JobOffer;
 
use App\Models\City;
use App\Helpers\RegionHelper;
use App\Helpers\CountryNormalizer;
use App\Models\Technology;
use App\Services\ScraperRunService;
use App\Models\TechnologyMetric;
 use App\Services\SourceStatusService;


class GreenhouseByTechnologiesCommand extends Command
{
    protected $signature = 'greenhouse:technologies {--company=*}';
    protected $description = '🌱 Importa ofertas desde Greenhouse con búsqueda por tecnología + contexto semántico.';

    protected $stats = [
        'mapped'   => 0,
        'skipped'  => 0,
    ];

 public function handle()
{
    // ▶️ Iniciar RUN del scraper
    $run = ScraperRunService::start(
        $this->signature,
        'Greenhouse',
        'technologies'
    );

    $source = 'greenhouse_technologies';

    SourceStatusService::start(
        source: $source,
        runId: $run->id,
        config: [],
        apiUrl: 'https://boards-api.greenhouse.io'
    );

    $connectionOk = false;
    $startedAt = now();

    SourceStatusService::progress($source, 0, 0, 0);

    try {

        $companies = $this->option('company');

        if (empty($companies)) {
            $this->error("❌ Debes pasar empresas, ejemplo: --company=stripe --company=cloudflare");
            return;
        }

        // 🔢 Contadores GLOBALES del run
        $totalFoundAll    = 0;
        $totalInsertedAll = 0;
        $totalSkippedAll  = 0;


        $lastTechnologyId = TechnologyMetric::where('source', 'Greenhouse')
            ->orderByDesc('created_at')
            ->value('technology_id');

        $baseQuery = Technology::where('enabled', 1)
            ->whereIn('technologies.id', function ($q) {
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

        if ($technologies->isEmpty()) {
            $technologies = $baseQuery->get();
        }

        $this->info("🌱 Importando desde Greenhouse para {$technologies->count()} tecnologías…");

        foreach ($companies as $companySlug) {

            $this->warn("\n🏢 Empresa: {$companySlug}");

            $url = "https://boards-api.greenhouse.io/v1/boards/{$companySlug}/jobs";

            $response = Http::timeout(20)->get($url);

            if ($response->failed()) {
                SourceStatusService::connectionFailed($source, "Error empresa {$companySlug}");
                $this->error("❌ No se pudo obtener datos de: {$companySlug}");
                $totalSkippedAll++;
                continue;
            }

            $connectionOk = true;

            $jobs = $response->json('jobs') ?? [];
            $contentAvailable = $this->companyHasContent($jobs);

            $this->line($contentAvailable
                ? "✔ Esta empresa SÍ expone descripción"
                : "⚠ Solo título (sin descripción)");

            foreach ($technologies as $tech) {

                $pattern = $tech->name;
                $escaped = preg_quote($pattern, '/');
                $regex   = "/{$escaped}/i";

                $resultsForTech = array_filter($jobs, function ($job) use ($regex, $contentAvailable) {

                    $title   = $job['title'] ?? '';
                    $content = $contentAvailable ? ($job['content'] ?? '') : '';

                    return preg_match($regex, $title)
                        || ($contentAvailable && preg_match($regex, $content));
                });

                $this->line("\n🔎 {$tech->name} → " . count($resultsForTech));

                $totalFoundAll += count($resultsForTech);

                $newForTech = 0;

                foreach ($resultsForTech as $job) {

                    $content     = $contentAvailable ? ($job['content'] ?? '') : '';
                    $title       = $job['title'] ?? 'N/A';
                    $companyName = $job['company_name'] ?? ucfirst($companySlug);
                    $urlJob      = $job['absolute_url'] ?? null;

                    $loc = strtolower($job['location']['name'] ?? '');

                    $countryCode = $this->extractCountryCodeOrNull($loc);
                    if (!$countryCode) {
                        $totalSkippedAll++;
                        continue;
                    }

                    $countryFull = CountryNormalizer::normalize($countryCode);

                    $cityRaw = $this->extractCity($loc);
                    [$cityClean, $lat, $lng] = $this->getCoords($cityRaw, $countryCode);

                    if (!$lat || !$lng) {
                        $totalSkippedAll++;
                        continue;
                    }

                    $modality = $this->detectModality($loc, $content);

                    $externalId = $job['id'];

                    $existing = JobOffer::where('external_id', $externalId)
                        ->where('source', 'Greenhouse')
                        ->first();

                    if ($existing) {
                        $existing->technologies()->syncWithoutDetaching([$tech->id]);
                        $totalSkippedAll++;
                        continue;
                    }

                    $region = RegionHelper::fromCountry($countryFull);

                    $offer = JobOffer::create([
                        'title'             => $title,
                        'company'           => $companyName,
                        'country'           => $countryFull,
                        'city'              => $cityClean,
                        'latitude'          => $lat,
                        'longitude'         => $lng,
                        'modality'          => $modality,
                        'experience_level'  => $this->extractExperience($content),
                        'education_level'   => $this->extractEducation($content),
                        'skills'            => $this->extractSkills($content),
                        'certifications'    => $this->extractCertifications($content),
                        'requirements'      => strip_tags($content),
                        'source'            => 'Greenhouse',
                        'external_id'       => $externalId,
                        'url'               => $urlJob,
                        'search_query'      => $tech->name,
                        'published_at'      => $job['updated_at'] ?? now(),
                        'region'            => $region,
                    ]);

                    $offer->technologies()->syncWithoutDetaching([$tech->id]);

                    $newForTech++;
                    $totalInsertedAll++;
                }

                TechnologyMetric::updateOrCreate(
                    [
                        'technology_id' => $tech->id,
                        'run_date'      => now()->toDateString(),
                        'source'        => 'Greenhouse',
                    ],
                    [
                        'technology_name'     => $tech->name,
                        'jobs_found_count'    => count($resultsForTech),
                        'jobs_new_count'      => $newForTech,
                        'countries_breakdown' => [],
                        'modality_breakdown'  => [],
                        'updated_at'          => now(),
                    ]
                );

                SourceStatusService::progress(
                    $source,
                    $totalFoundAll,
                    $totalInsertedAll,
                    $totalSkippedAll
                );

                $this->info("✔ {$tech->name}: {$newForTech} nuevas");
            }
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

        $this->info("\n🎯 Greenhouse → Tecnologías finalizado correctamente.");

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



    /* =======================================================
     *                    HELPERS
     * ======================================================= */

    protected function getSearchPattern($language)
    {
        if ($language->context_id && $language->context && $language->context->search_context) {
            return $language->name . ' ' . $language->context->search_context;
        }

        return $language->name;
    }

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
            'usa'           => 'us',
            'us'            => 'us',
            'canada'        => 'ca',
            'mexico'        => 'mx',
            'brazil'        => 'br',
            'spain'         => 'es',
            'france'        => 'fr',
            'germany'       => 'de',
            'italy'         => 'it',
            'argentina'     => 'ar',
            'chile'         => 'cl',
            'peru'          => 'pe',
            'colombia'      => 'co',
            'uk'            => 'gb',
            'united kingdom'=> 'gb',
            'ireland'       => 'ie',
            'australia'     => 'au',
            'new zealand'   => 'nz',
            'india'         => 'in',
            'singapore'     => 'sg',
        ];

        foreach ($map as $keyword => $code) {
            if (str_contains($lower, $keyword)) {
                return $code;
            }
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

        // 🌍 REMOTO
        str_contains($t, 'remote'),
        str_contains($t, 'work from home'),
        str_contains($t, 'home office'),
        str_contains($t, 'teletrabajo'),
        str_contains($t, 'anywhere') => 'remote',

        // 🏠 HÍBRIDO
        str_contains($t, 'hybrid'),
        str_contains($t, 'híbrido'),
        str_contains($t, 'mixto'),
        str_contains($t, 'partial remote') => 'hybrid',

        // 🏢 PRESENCIAL
        str_contains($t, 'onsite'),
        str_contains($t, 'on-site'),
        str_contains($t, 'office'),
        str_contains($t, 'presencial'),
        str_contains($t, 'in office'),
        str_contains($t, 'oficina') => 'presencial',

        // ❓ NO PRECISA
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
            default                      => null,
        };
    }

    protected function extractSkills($text)
    {
        $t = strtolower($text);
        $skills = [];

        foreach (['python','java','php','laravel','react','vue','sql','docker','aws','git','node'] as $skill) {
            if (str_contains($t, $skill)) $skills[] = strtoupper($skill);
        }

        return !empty($skills) ? implode(', ', $skills) : null;
    }

    protected function extractCertifications($text)
    {
        $t = strtolower($text);
        $found = [];

        foreach (['aws','azure','google cloud','scrum','pmp','ccna','itil'] as $cert) {
            if (str_contains($t, $cert)) $found[] = strtoupper($cert);
        }

        return !empty($found) ? implode(', ', $found) : null;
    }
}
