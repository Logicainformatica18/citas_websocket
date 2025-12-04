<?php

namespace App\Console\Commands\Scraping;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use App\Models\Competency;
use App\Models\JobOffer;
use App\Models\CompetencyMetric;
use App\Models\City;

use App\Helpers\RegionHelper;
use App\Helpers\CountryNormalizer;

class GreenhouseByCompetenciesCommand extends Command
{
    protected $signature = 'greenhouse:competencies {--company=*}';
    protected $description = '🌱 Importa ofertas desde Greenhouse usando competencias + contexto semántico.';

    protected $stats = [
        'mapped'   => 0,
        'skipped'  => 0,
    ];

   public function handle()
{
    $companies = $this->option('company');

    if (empty($companies)) {
        $this->error("❌ Debes pasar empresas (ejemplo: --company=stripe --company=cloudflare)");
        return;
    }

    // SOLO competencias asociadas a carreras y habilitadas
    $competencies = Competency::whereNotNull('career_id')
        ->where('enabled', 1)
        ->get();

    $this->info("🌱 Importando desde Greenhouse para {$competencies->count()} competencias…");

    foreach ($companies as $companySlug) {

        $this->warn("\n🏢 Empresa: {$companySlug}");

        $url = "https://boards-api.greenhouse.io/v1/boards/{$companySlug}/jobs";

        try {
            $response = Http::timeout(20)->get($url);

            if ($response->failed()) {
                $this->error("❌ No se pudo obtener datos de: {$companySlug}");
                continue;
            }

            $jobs = $response->json('jobs') ?? [];
            $contentAvailable = $this->companyHasContent($jobs);

            $this->line(
                $contentAvailable
                    ? "✔ Esta empresa SÍ expone descripción"
                    : "⚠ Solo título (sin descripción)"
            );

            // ==========================================================
            // RECORRER TODAS LAS COMPETENCIAS
            // ==========================================================

            foreach ($competencies as $comp) {

                // ✔ Aquí se usa description_en si existe
                $pattern = $this->getSearchPattern($comp);

                $escaped = preg_quote($pattern, '/');
                $regex = "/{$escaped}/i";

                // Filtrar ofertas que coincidan con el patrón
                $resultsForComp = array_filter($jobs, function ($job) use ($regex, $contentAvailable) {

                    $title = $job['title'] ?? '';
                    $content = $contentAvailable ? ($job['content'] ?? '') : '';

                    return preg_match($regex, $title)
                        || ($contentAvailable && preg_match($regex, $content));
                });

                $this->line("\n🔎 {$comp->name} (buscando: {$pattern}) → " . count($resultsForComp));

                $new = [];

                // ==========================================================
                // INSERTAR OFERTAS
                // ==========================================================

                foreach ($resultsForComp as $job) {

                    $title = $job['title'] ?? 'N/A';
                    $companyName = $job['company_name'] ?? ucfirst($companySlug);
                    $urlJob = $job['absolute_url'] ?? null;
                    $content = $contentAvailable ? ($job['content'] ?? '') : '';
                    $loc = strtolower($job['location']['name'] ?? '');

                    // País
                    $countryCode = $this->extractCountryCodeOrNull($loc);
                    if (!$countryCode) {
                        $this->stats['skipped']++;
                        continue;
                    }

                    $countryFull = CountryNormalizer::normalize($countryCode);
                    $cityRaw = $this->extractCity($loc);

                    // Coordenadas reales
                    [$cityClean, $lat, $lng] = $this->getCoords($cityRaw, $countryCode);
                    if (!$lat || !$lng) {
                        $this->stats['skipped']++;
                        continue;
                    }

                    $modality = $this->detectModality($loc, $content);
                    $externalId = $job['id'];

                    // DUPLICADOS
                    $existing = JobOffer::where('external_id', $externalId)
                        ->where('source', 'Greenhouse')
                        ->first();

                    if ($existing) {
                        $existing->competencies()->syncWithoutDetaching([$comp->id]);
                        continue;
                    }

                    $region = RegionHelper::fromCountry($countryFull);

                    // Crear oferta
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
                        'search_query'      => $pattern,
                        'published_at'      => $job['updated_at'] ?? now(),
                        'region'            => $region,
                    ]);

                    $offer->competencies()->syncWithoutDetaching([$comp->id]);

                    $new[] = $externalId;
                }

                // ==========================================================
                // MÉTRICAS
                // ==========================================================

                CompetencyMetric::updateOrCreate(
                    [
                        'competency_id' => $comp->id,
                        'run_date'      => now()->toDateString(),
                        'source'        => 'Greenhouse'
                    ],
                    [
                        'competency_name'     => $comp->name,
                        'jobs_found_count'    => count($resultsForComp),
                        'jobs_new_count'      => count($new),
                        'countries_breakdown' => [],
                        'modality_breakdown'  => [],
                    ]
                );

                $this->info("✔ {$comp->name}: " . count($new) . " nuevas");
            }

        } catch (\Throwable $e) {
            $this->error("⚠ Error en Greenhouse: " . $e->getMessage());
            Log::error("Greenhouse error: " . $e->getMessage());
        }
    }

    $this->newLine();
    $this->info("🎯 Finalizado:");
    $this->line("   ⏭️ Skipped:  {$this->stats['skipped']}");
}


    // =====================================================================
    //                              HELPERS
    // =====================================================================

  protected function getSearchPattern($competency)
{
    return $competency->description_en ?: $competency->name;
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

    protected function detectModality($loc, $desc)
    {
        $t = strtolower($loc . ' ' . $desc);

        return match (true) {
            str_contains($t, 'remote') => 'remote',
            str_contains($t, 'hybrid') => 'hybrid',
            default => 'no_remote',
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
