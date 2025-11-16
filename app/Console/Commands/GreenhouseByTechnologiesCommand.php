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
        $companies = $this->option('company');

        if (empty($companies)) {
            $this->error("❌ Debes pasar empresas: --company=stripe --company=cloudflare");
            return;
        }

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
                    continue;
                }

                $jobs = $response->json('jobs') ?? [];
                $contentAvailable = $this->companyHasContent($jobs);

                foreach ($techs as $tech) {

                    // ⚡ Patrón real de búsqueda
                    $pattern = $this->getSearchPattern($tech);

                    $escaped = preg_quote($pattern, '/');
                    $regex = "/{$escaped}/i";

                    $results = array_filter($jobs, function ($job) use ($regex, $contentAvailable) {
                        $title = $job['title'] ?? '';
                        $content = $contentAvailable ? ($job['content'] ?? '') : '';
                        return preg_match($regex, $title) ||
                            ($contentAvailable && preg_match($regex, $content));
                    });

                    $this->line("\n🔎 {$tech->name} (buscando: '{$pattern}') → " . count($results));

                    $new = [];

                    foreach ($results as $job) {

                        $content = $contentAvailable ? ($job['content'] ?? '') : '';
                        $title = $job['title'] ?? 'N/A';
                        $companyName = $job['company_name'] ?? ucfirst($companySlug);

                        // Ubicación original
                        $loc = strtolower($job['location']['name'] ?? '');

                        // Modality
                        $modality = $this->detectModality($loc, $content);

                        // Ciudad + Código de país
                        $city = $this->extractCity($loc);
                        $countryCode = $this->extractCountryCodeOrNull($loc);

                        // ❌ DESCARTAR si no detecto país
                        if (!$countryCode) {
                            $this->stats['skipped']++;
                            continue;
                        }

                        // Normalización completa
                        $countryFull = CountryNormalizer::normalize($countryCode);

                        // Coordenadas
                        [$cityClean, $lat, $lng] = $this->getCoords($city, $countryCode);

                        // ❌ DESCARTAR si no ubico coordenadas reales
                        if (!$lat || !$lng) {
                            $this->stats['skipped']++;
                            continue;
                        }

                        $city = $cityClean;

                        $externalId = $job['id'];

                        // Evitar duplicados
                        $existing = JobOffer::where('external_id', $externalId)
                            ->where('source', 'Greenhouse')
                            ->first();

                        if ($existing) {
                            $existing->technologies()->syncWithoutDetaching([$tech->id]);
                            continue;
                        }

                        $region = RegionHelper::fromCountry($countryFull);

                        // Crear oferta
                        $offer = JobOffer::create([
                            'title'         => $title,
                            'company'       => $companyName,
                            'country'       => $countryFull,
                            'city'          => $city,
                            'latitude'      => $lat,
                            'longitude'     => $lng,
                            'modality'      => $modality,
                            'requirements'  => strip_tags($content),
                            'source'        => 'Greenhouse',
                            'external_id'   => $externalId,
                            'url'           => $job['absolute_url'] ?? null,
                            'search_query'  => $pattern,
                            'published_at'  => $job['updated_at'] ?? now(),
                            'region'        => $region,
                        ]);

                        $offer->technologies()->syncWithoutDetaching([$tech->id]);
                        $new[] = $externalId;
                    }

                    // Guardar métricas
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
                $this->error("⚠️ Error: " . $e->getMessage());
                Log::error("Greenhouse technologies error: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("🎯 Finalizado. Skipped: {$this->stats['skipped']}");
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

    protected function detectModality($loc, $desc)
    {
        $t = strtolower($loc . ' ' . $desc);

        return match (true) {
            str_contains($t, 'remote') => 'remote',
            str_contains($t, 'hybrid') => 'hybrid',
            default                   => 'no_remote',
        };
    }
}
