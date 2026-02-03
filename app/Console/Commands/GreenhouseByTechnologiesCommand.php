<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Technology;
use App\Models\JobOffer;
use App\Models\TechnologyMetric;
use App\Models\City;
use App\Helpers\RegionHelper;
use App\Helpers\CountryNormalizer;
use App\Services\ScraperRunService;
class GreenhouseByTechnologiesCommand extends Command
{
    protected $signature = 'greenhouse:technologies {--company=*}';
    protected $description = '🌱 Importa ofertas desde Greenhouse con búsqueda por tecnología o contexto semántico.';

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

    try {

        $companies = $this->option('company');

        if (empty($companies)) {
            $this->error("❌ Debes pasar empresas: --company=stripe --company=cloudflare");
            return;
        }

        // 🔢 Contadores GLOBALES
        $totalFoundAll    = 0;
        $totalInsertedAll = 0;
        $totalSkippedAll  = 0;

        // ✔ Solo tecnologías habilitadas + asociadas a carreras
        $techs = Technology::with('context')
            ->where('enabled', 1)
            ->whereIn('technologies.id', function ($q) {
                $q->select('course_technology.technology_id')
                    ->from('course_technology')
                    ->join('career_course', 'career_course.course_id', '=', 'course_technology.course_id');
            })
            ->get();

        $this->info("🔧 Importando desde Greenhouse para {$techs->count()} tecnologías…");

        foreach ($companies as $companySlug) {

            $this->warn("\n🏢 Empresa: {$companySlug}");

            $url = "https://boards-api.greenhouse.io/v1/boards/{$companySlug}/jobs";

            try {
                $response = Http::timeout(20)->get($url);

                if ($response->failed()) {
                    $this->error("❌ No se pudo obtener datos de la empresa {$companySlug}");
                    $totalSkippedAll++;
                    continue;
                }

                $jobs = $response->json('jobs') ?? [];
                $contentAvailable = $this->companyHasContent($jobs);

                foreach ($techs as $tech) {

                    // ⚡ Patrón real de búsqueda
                    $pattern = $this->getSearchPattern($tech);
                    $escaped = preg_quote($pattern, '/');
                    $regex   = "/{$escaped}/i";

                    $results = array_filter($jobs, function ($job) use ($regex, $contentAvailable) {
                        $title   = $job['title'] ?? '';
                        $content = $contentAvailable ? ($job['content'] ?? '') : '';
                        return preg_match($regex, $title)
                            || ($contentAvailable && preg_match($regex, $content));
                    });

                    $this->line("\n🔎 {$tech->name} (buscando: '{$pattern}') → " . count($results));

                    $new = [];

                    foreach ($results as $job) {

                        $totalFoundAll++;

                        $content     = $contentAvailable ? ($job['content'] ?? '') : '';
                        $title       = $job['title'] ?? 'N/A';
                        $companyName = $job['company_name'] ?? ucfirst($companySlug);

                        // 📍 ubicación
                        $loc = strtolower($job['location']['name'] ?? '');

                        // 🧭 Modalidad (tu helper, sin tocar)
                        $modality = $this->detectModality($loc, $content);

                        // 🌍 Ciudad + país
                        $city        = $this->extractCity($loc);
                        $countryCode = $this->extractCountryCodeOrNull($loc);

                        if (!$countryCode) {
                            $this->stats['skipped']++;
                            $totalSkippedAll++;
                            continue;
                        }

                        $countryFull = CountryNormalizer::normalize($countryCode);

                        // 📍 Coordenadas reales
                        [$cityClean, $lat, $lng] = $this->getCoords($city, $countryCode);

                        if (!$lat || !$lng) {
                            $this->stats['skipped']++;
                            $totalSkippedAll++;
                            continue;
                        }

                        $externalId = $job['id'];

                        // 🚫 Duplicado
                        $existing = JobOffer::where('external_id', $externalId)
                            ->where('source', 'Greenhouse')
                            ->first();

                        if ($existing) {
                            $existing->technologies()
                                ->syncWithoutDetaching([$tech->id]);
                            $totalSkippedAll++;
                            continue;
                        }

                        $region = RegionHelper::fromCountry($countryFull);

                        // 💾 Crear oferta
                        $offer = JobOffer::create([
                            'title'        => $title,
                            'company'      => $companyName,
                            'country'      => $countryFull,
                            'city'         => $cityClean,
                            'latitude'     => $lat,
                            'longitude'    => $lng,
                            'modality'     => $modality,
                            'requirements' => strip_tags($content),
                            'source'       => 'Greenhouse',
                            'external_id'  => $externalId,
                            'url'          => $job['absolute_url'] ?? null,
                            'search_query' => $pattern,
                            'published_at' => $job['updated_at'] ?? now(),
                            'region'       => $region,
                        ]);

                        $offer->technologies()
                              ->syncWithoutDetaching([$tech->id]);

                        $new[] = $externalId;
                        $totalInsertedAll++;
                    }

                    // 📊 Métricas por tecnología
                    TechnologyMetric::updateOrCreate(
                        [
                            'technology_id' => $tech->id,
                            'run_date'      => now()->toDateString(),
                            'source'        => 'Greenhouse'
                        ],
                        [
                            'technology_name'    => $tech->name,
                            'jobs_found_count'   => count($results),
                            'jobs_new_count'     => count($new),
                            'countries_breakdown'=> [],
                            'modality_breakdown' => [],
                        ]
                    );

                    $this->info("✔ {$tech->name}: " . count($new) . " nuevas");
                }

            } catch (\Throwable $e) {
                $totalSkippedAll++;
                Log::error("Greenhouse technologies error ({$companySlug}): " . $e->getMessage());
                $this->error("⚠️ Error: " . $e->getMessage());
            }
        }

        // ✅ Finalizar RUN exitoso
        ScraperRunService::success(
            $run,
            $totalFoundAll,
            $totalInsertedAll,
            $totalSkippedAll
        );

        $this->newLine();
        $this->info("🎯 Finalizado. Skipped: {$totalSkippedAll}");

    } catch (\Throwable $e) {
        // ❌ RUN fallido
        ScraperRunService::failed($run, $e);
        throw $e;
    }
}


    // ==========================
    // 🔥 UTILIDADES
    // ==========================

    protected function getSearchPattern($tech)
    {
        return ($tech->context_id && $tech->context && $tech->context->search_context)
            ? $tech->name . ' ' . $tech->context->search_context
            : $tech->name;
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
            'united states' => 'us', 'usa' => 'us', 'us' => 'us',
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
            'united kingdom' => 'gb', 'uk' => 'gb',
            'ireland' => 'ie',
            'australia' => 'au',
            'new zealand' => 'nz',
            'india' => 'in',
            'singapore' => 'sg',
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
        if (!$city) return [null,null,null];

        $found = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])
            ->whereRaw('LOWER(iso2) = ?', [strtolower($code)])
            ->first();

        return $found
            ? [$found->city, $found->lat, $found->lng]
            : [null,null,null];
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

}
