<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Methodology;
use App\Models\JobOffer;
use App\Models\MethodologyMetric;
use App\Models\City;
use App\Helpers\RegionHelper;
use App\Services\ScraperRunService;
class GreenhouseByMethodologiesCommand extends Command
{
    protected $signature = 'greenhouse:methodologies {--company=*}';
    protected $description = '🌱 Importa ofertas desde Greenhouse con búsqueda por metodología o contexto semántico.';

    protected $stats = [
        'mapped'   => 0,
        'skipped'  => 0,
        'fallback' => 0,
    ];

 public function handle()
{
    // ▶️ Iniciar RUN del scraper
    $run = ScraperRunService::start(
        $this->signature,
        'Greenhouse',
        'methodologies'
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

        // ✔ Solo metodologías activas + asociadas a carreras
        $methods = Methodology::with('context')
            ->where('enabled', 1)
            ->whereIn('methodologies.id', function ($q) {
                $q->select('course_methodology.methodology_id')
                    ->from('course_methodology')
                    ->join('career_course','career_course.course_id','=','course_methodology.course_id');
            })
            ->get();

        $this->info("🌱 Importando desde Greenhouse para {$methods->count()} metodologías…");

        foreach ($companies as $companySlug) {

            $this->warn("\n🏢 Empresa: {$companySlug}");

            $url = "https://boards-api.greenhouse.io/v1/boards/{$companySlug}/jobs";

            try {
                $response = Http::timeout(20)->get($url);

                if ($response->failed()) {
                    $this->error("❌ No se pudo obtener datos de: {$companySlug}");
                    $totalSkippedAll++;
                    continue;
                }

                $jobs = $response->json('jobs') ?? [];
                $contentAvailable = $this->companyHasContent($jobs);

                foreach ($methods as $method) {

                    // 🔥 Patrón real de búsqueda
                    $pattern = $this->getSearchPattern($method);
                    $escaped = preg_quote($pattern, '/');
                    $regex   = "/{$escaped}/i";

                    // 🔎 Filtrar trabajos
                    $results = array_filter($jobs, function ($job) use ($regex, $contentAvailable) {
                        $title   = $job['title'] ?? '';
                        $content = $contentAvailable ? ($job['content'] ?? '') : '';
                        return preg_match($regex, $title)
                            || ($contentAvailable && preg_match($regex, $content));
                    });

                    $this->line("\n🔎 {$method->name} (buscando: {$pattern}) → " . count($results));

                    $new = [];

                    foreach ($results as $job) {

                        $totalFoundAll++;

                        $content     = $contentAvailable ? ($job['content'] ?? '') : '';
                        $title       = $job['title'] ?? 'N/A';
                        $companyName = $job['company_name'] ?? ucfirst($companySlug);

                        // 📍 ubicación cruda
                        $loc = strtolower($job['location']['name'] ?? '');

                        $city        = $this->extractCity($loc);
                        $countryCode = $this->extractCountryCodeOrNull($loc);

                        // ❌ sin país
                        if (!$countryCode) {
                            $this->stats['skipped']++;
                            $totalSkippedAll++;
                            continue;
                        }

                        $countryFull = \App\Helpers\CountryNormalizer::normalize($countryCode);

                        // 🌍 Coordenadas
                        [$cityClean, $lat, $lng] = $this->getCoords($city, $countryCode);

                        if (!$lat || !$lng) {
                            $this->stats['skipped']++;
                            $totalSkippedAll++;
                            continue;
                        }

                        // 🧭 Modalidad
                        $modality = $this->detectModality($loc, $content);

                        $externalId = $job['id'];

                        // 🚫 Duplicado
                        $existing = JobOffer::where('external_id',$externalId)
                            ->where('source','Greenhouse')
                            ->first();

                        if ($existing) {
                            $existing->methodologies()
                                ->syncWithoutDetaching([$method->id]);
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

                        $offer->methodologies()
                              ->syncWithoutDetaching([$method->id]);

                        $new[] = $externalId;
                        $totalInsertedAll++;
                    }

                    // 📊 Métricas por metodología
                    MethodologyMetric::updateOrCreate(
                        [
                            'methodology_id' => $method->id,
                            'run_date'       => now()->toDateString(),
                            'source'         => 'Greenhouse'
                        ],
                        [
                            'methodology_name'   => $method->name,
                            'jobs_found_count'   => count($results),
                            'jobs_new_count'     => count($new),
                            'countries_breakdown'=> [],
                            'modality_breakdown' => [],
                        ]
                    );

                    $this->info("✔ {$method->name}: " . count($new) . " nuevas");
                }

            } catch (\Throwable $e) {
                $totalSkippedAll++;
                Log::error("Greenhouse error ({$companySlug}): " . $e->getMessage());
                $this->error("⚠️ Error: " . $e->getMessage());
            }
        }

        // ✅ RUN exitoso
        ScraperRunService::success(
            $run,
            $totalFoundAll,
            $totalInsertedAll,
            $totalSkippedAll
        );

        $this->newLine();
        $this->info("🎯 Finalizado. Métodos procesados.");

    } catch (\Throwable $e) {
        // ❌ RUN fallido
        ScraperRunService::failed($run, $e);
        throw $e;
    }
}


    // =====================================================
    // 🔥 UTILIDADES
    // =====================================================

    protected function getSearchPattern($method)
    {
        // Combinar metodología + contexto
        if ($method->context_id && $method->context && $method->context->search_context) {
            return $method->name . ' ' . $method->context->search_context;
        }
        return $method->name;
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
            'united states' => 'us','usa'=>'us','us'=>'us',
            'canada'=>'ca','mexico'=>'mx','brazil'=>'br',
            'spain'=>'es','france'=>'fr','germany'=>'de','italy'=>'it',
            'argentina'=>'ar','chile'=>'cl','peru'=>'pe','colombia'=>'co',
            'uk'=>'gb','united kingdom'=>'gb','ireland'=>'ie',
            'australia'=>'au','new zealand'=>'nz',
            'india'=>'in','singapore'=>'sg',
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
