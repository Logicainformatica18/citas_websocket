<?php

namespace App\Console\Commands\Scraping;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Competency;
use App\Models\JobOffer;
use App\Models\CompetencyMetric;
use App\Models\City;
use Carbon\Carbon;
use App\Helpers\RemotiveCountry;
use App\Helpers\RegionMapper;

class RemotiveByCompetenciesCommand extends Command
{
    protected $signature = 'remotive:competencies';
    protected $description = 'Importa ofertas desde Remotive por competencias con geolocalización estricta.';

    protected $stats = [
        'api_hits'  => 0,
        'mapped'    => 0,
        'skipped'   => 0,
    ];

   public function handle()
{
    // ✔ Solo competencias de carreras — usando description_en
    $competencies = Competency::select('id','name','description_en')
        ->whereNotNull('career_id')
        ->get()
        ->mapWithKeys(fn($c) => [
            $c->id => ($c->description_en ?: $c->name)
        ]);

    $this->info("🌎 Importando Remotive para {$competencies->count()} competencias…");

    foreach ($competencies as $competencyId => $competencyName) {

        $this->warn("\n🔎 Procesando: {$competencyName}");

        $totalFound = 0;
        $totalNew   = 0;

        try {

            // 📡 API Remotive
            $response = Http::timeout(20)->get(
                "https://remotive.com/api/remote-jobs",
                ['search' => $competencyName]
            );

            if ($response->failed()) {
                $this->error("❌ Error API Remotive para {$competencyName}");
                continue;
            }

            $jobs = $response->json()['jobs'] ?? [];
            $totalFound = count($jobs);

            foreach ($jobs as $job) {

                $externalId = $job['id'] ?? null;
                $title      = $job['title'] ?? 'N/A';
                $company    = $job['company_name'] ?? null;
                $desc       = strtolower($job['description'] ?? '');
                $urlJob     = $job['url'] ?? null;

                // -----------------------
                // MODALIDAD — si no dice remote NO se considera
                // -----------------------
                $modality = $this->detectModality($job);
                $isRemote = ($modality === 'remote');

                if (!$isRemote) continue;

                // -----------------------
                // UBICACIÓN
                // -----------------------
                $locationStr = $job['candidate_required_location'] ?? "Remote";

                [$rawCity, $rawCountryRaw] = $this->extractLocation($locationStr);

                $country = RemotiveCountry::normalize($rawCountryRaw);

                // -----------------------
                // GEOLOCALIZACIÓN
                // -----------------------
                if ($isRemote) {
                    $finalCity = "Remote";
                    $lat = $lng = null;
                } else {
                    [$finalCity, $lat, $lng] = $this->tryGeocode($rawCity, $country);

                    if (!$lat || !$lng) {
                        $this->stats['skipped']++;
                        continue;
                    }
                }

                // -----------------------
                // DUPLICADOS
                // -----------------------
                $existing = JobOffer::where('external_id', $externalId)
                    ->where('source', 'Remotive')
                    ->first();

                if ($existing) {
                    $existing->competencies()->syncWithoutDetaching([$competencyId]);
                    continue;
                }

                // -----------------------
                // REGIÓN
                // -----------------------
                $region = RegionMapper::resolve($country);

                // -----------------------
                // CREAR OFERTA
                // -----------------------
                $offer = JobOffer::create([
                    'title'            => $title,
                    'company'          => $company,
                    'country'          => $country,
                    'city'             => $finalCity,
                    'latitude'         => $lat,
                    'longitude'        => $lng,
                    'modality'         => $modality,

                    'salary_min'       => $this->extractMinSalary($job['salary'] ?? ''),
                    'salary_max'       => $this->extractMaxSalary($job['salary'] ?? ''),

                    'experience_level' => $this->extractExperience($desc),
                    'education_level'  => $this->extractEducation($desc),
                    'certifications'   => $this->extractCertifications($desc),
                    'skills'           => $this->extractSkills($desc),
                    'requirements'     => strip_tags($desc),

                    'source'           => 'Remotive',
                    'external_id'      => $externalId,
                    'url'              => $urlJob,
                    'search_query'     => $competencyName,

                    'published_at'     => isset($job['publication_date'])
                        ? Carbon::parse($job['publication_date'])
                        : now(),

                    'region'           => $region,
                ]);

                // Relación competencia ↔ oferta
                $offer->competencies()->syncWithoutDetaching([$competencyId]);

                $totalNew++;
            }

        } catch (\Throwable $e) {
            Log::error("❌ Error Remotive {$competencyName}: " . $e->getMessage());
        }

        // -----------------------
        // MÉTRICAS
        // -----------------------
        CompetencyMetric::updateOrCreate(
            [
                'competency_id' => $competencyId,
                'run_date'      => now()->toDateString(),
                'source'        => 'Remotive',
            ],
            [
                'competency_name'    => $competencyName,
                'jobs_found_count'   => $totalFound,
                'jobs_new_count'     => $totalNew,
                'updated_at'         => now(),
            ]
        );

        $this->info("✔ {$competencyName}: {$totalNew} nuevas / {$totalFound} encontradas");
    }

    $this->info("\n🎯 Remotive completo");
}



    // -----------------------------------------------------
    // HELPERS
    // -----------------------------------------------------

    protected function extractLocation(string $txt): array
    {
        if (str_contains($txt, ',')) {
            [$city, $country] = array_map('trim', explode(',', $txt));
        } else {
            $city = $txt;
            $country = $txt;
        }
        return [$city, $country];
    }

    protected function tryGeocode(?string $city, ?string $country)
    {
        if (!$city || !$country || strtolower($city) === 'remote') {
            return [null, null, null];
        }

        // 1) Buscar ciudad exacta
        $found = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])
            ->first();

        if ($found) {
            $this->stats['mapped']++;
            return [$found->city, $found->lat, $found->lng];
        }

        // 2) Buscar con Nominatim
        try {
            $res = Http::withHeaders(['User-Agent' => 'Observatorio/1.0'])
                ->timeout(10)
                ->get("https://nominatim.openstreetmap.org/search", [
                    'q' => "$city, $country",
                    'format' => 'json',
                    'limit' => 1,
                ]);

            if ($res->ok() && count($res->json()) > 0) {
                $this->stats['api_hits']++;
                $d = $res->json()[0];
                return [$city, (float)$d['lat'], (float)$d['lon']];
            }
        } catch (\Throwable $e) {}

        return [null, null, null];
    }

    protected function detectModality(array $job): string
    {
        $cat   = strtolower($job['job_type'] ?? '');
        $title = strtolower($job['title'] ?? '');
        $desc  = strtolower($job['description'] ?? '');

        return match (true) {
            str_contains($cat, 'remote'),
            str_contains($title, 'remote'),
            str_contains($desc, 'remote') => 'remote',

            default => 'no_remote',
        };
    }

    protected function extractMinSalary(string $salary): ?float
    {
        preg_match('/(\d+)/', $salary, $m);
        return $m[1] ?? null;
    }

    protected function extractMaxSalary(string $salary): ?float
    {
        preg_match_all('/(\d+)/', $salary, $m);
        return $m[1][1] ?? ($m[1][0] ?? null);
    }

    protected function extractExperience(string $text): ?string
    {
        return match (true) {
            str_contains($text, 'senior') => 'senior',
            str_contains($text, 'mid')    => 'mid',
            str_contains($text, 'junior') => 'junior',
            default => null,
        };
    }

    protected function extractEducation(string $text): ?string
    {
        return match (true) {
            str_contains($text, 'bachelor')  => 'bachelor',
            str_contains($text, 'master')    => 'master',
            str_contains($text, 'phd')       => 'phd',
            str_contains($text, 'technical') => 'technical',
            default => null,
        };
    }

    protected function extractCertifications(string $text): ?string
    {
        $certs = [];
        foreach (['aws','azure','google cloud','scrum','pmp','cisco','ccna','itil'] as $c) {
            if (str_contains($text, $c)) $certs[] = strtoupper($c);
        }
        return $certs ? implode(', ', $certs) : null;
    }

    protected function extractSkills(string $text): ?string
    {
        $out = [];
        foreach (['python','java','php','laravel','react','vue','sql','docker','aws','git','node'] as $s) {
            if (str_contains($text, $s)) $out[] = strtoupper($s);
        }
        return $out ? implode(', ', $out) : null;
    }
}
