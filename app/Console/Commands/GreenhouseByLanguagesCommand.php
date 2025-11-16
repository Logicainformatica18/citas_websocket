<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Language;
use App\Models\JobOffer;
use App\Models\LanguageMetric;
use App\Models\City;
use App\Helpers\RegionHelper;
use App\Helpers\CountryNormalizer;

class GreenhouseByLanguagesCommand extends Command
{
    protected $signature = 'greenhouse:languages {--company=*}';
    protected $description = '🌱 Importa ofertas desde Greenhouse con búsqueda por lenguaje + contexto semántico.';

    protected $stats = [
        'mapped'   => 0,
        'skipped'  => 0,
    ];

    public function handle()
    {
        $companies = $this->option('company');

        if (empty($companies)) {
            $this->error("❌ Debes pasar empresas, ejemplo: --company=stripe --company=cloudflare");
            return;
        }

        // ✔ Lenguajes permitidos
        $languages = Language::with('context')
            ->where('enabled', 1)
            ->whereIn('languages.id', function ($q) {
                $q->select('course_language.language_id')
                    ->from('course_language')
                    ->join('career_course', 'career_course.course_id', '=', 'course_language.course_id');
            })
            ->get();

        $this->info("🌱 Importando desde Greenhouse para {$languages->count()} lenguajes…");

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

                $this->line($contentAvailable
                    ? "✔ Esta empresa SÍ expone descripción"
                    : "⚠ Solo título (sin descripción)");

                foreach ($languages as $lang) {

                    // 🔥 Build search string: lenguaje + contexto
                    $pattern = $this->getSearchPattern($lang);

                    $escaped = preg_quote($pattern, '/');
                    $regex = "/{$escaped}/i";

                    // Filtrar vacantes según coincidencia
                    $resultsForLang = array_filter($jobs, function ($job) use ($regex, $contentAvailable) {

                        $title = $job['title'] ?? '';
                        $content = $contentAvailable ? ($job['content'] ?? '') : '';

                        return preg_match($regex, $title)
                            || ($contentAvailable && preg_match($regex, $content));
                    });

                    $this->line("\n🔎 {$lang->name} (buscando: {$pattern}) → " . count($resultsForLang));

                    $new = [];

                    foreach ($resultsForLang as $job) {

                        $content = $contentAvailable ? ($job['content'] ?? '') : '';
                        $title = $job['title'] ?? 'N/A';
                        $companyName = $job['company_name'] ?? ucfirst($companySlug);
                        $urlJob = $job['absolute_url'] ?? null;

                        // Ubicación cruda
                        $loc = strtolower($job['location']['name'] ?? '');

                        // Extraer país
                        $countryCode = $this->extractCountryCodeOrNull($loc);

                        // ❌ Si NO detectamos país → DESCARTAR
                        if (!$countryCode) {
                            $this->stats['skipped']++;
                            continue;
                        }

                        // Normalizar país
                        $countryFull = CountryNormalizer::normalize($countryCode);

                        // Extraer ciudad
                        $cityRaw = $this->extractCity($loc);

                        // Buscar coordenadas REALES en tu tabla cities
                        [$cityClean, $lat, $lng] = $this->getCoords($cityRaw, $countryCode);

                        // ❌ Si no hay coordenadas → DESCARTAR
                        if (!$lat || !$lng) {
                            $this->stats['skipped']++;
                            continue;
                        }

                        // Detectar modalidad
                        $modality = $this->detectModality($loc, $content);

                        $externalId = $job['id'];

                        // Evitar duplicados
                        $existing = JobOffer::where('external_id', $externalId)
                            ->where('source', 'Greenhouse')
                            ->first();

                        if ($existing) {
                            $existing->languages()->syncWithoutDetaching([$lang->id]);
                            continue;
                        }

                        $region = RegionHelper::fromCountry($countryFull);

                        // Crear oferta limpia
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

                        $offer->languages()->syncWithoutDetaching([$lang->id]);

                        $new[] = $externalId;
                    }

                    // Guardar métricas
                    LanguageMetric::updateOrCreate(
                        [
                            'language_id' => $lang->id,
                            'run_date'    => now()->toDateString(),
                            'source'      => 'Greenhouse'
                        ],
                        [
                            'language_name'      => $lang->name,
                            'jobs_found_count'   => count($resultsForLang),
                            'jobs_new_count'     => count($new),
                            'countries_breakdown'=> [],
                            'modality_breakdown' => [],
                        ]
                    );

                    $this->info("✔ {$lang->name}: " . count($new) . " nuevas");
                }

            } catch (\Throwable $e) {
                $this->error("⚠ Error en Greenhouse: " . $e->getMessage());
                Log::error("Greenhouse error: " . $e->getMessage());
            }
        }

        // Resultado final
        $this->newLine();
        $this->info("🎯 Finalizado:");
        $this->line("   ⏭️ Skipped:  {$this->stats['skipped']}");
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
