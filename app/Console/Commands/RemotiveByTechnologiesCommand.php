<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Technology;
use App\Models\JobOffer;
use App\Models\TechnologyMetric;
use App\Models\City;
use Carbon\Carbon;
use App\Helpers\RemotiveCountry;
use App\Helpers\RegionMapper;

class RemotiveByTechnologiesCommand extends Command
{
    protected $signature = 'remotive:technologies';
    protected $description = 'Importa ofertas desde Remotive por tecnologías, con geolocalización estricta.';

    protected $stats = [
        'api_hits'  => 0,
        'mapped'    => 0,
        'skipped'   => 0,
    ];

    public function handle()
    {
        // Solo tecnologías relacionadas a carreras ISIL
        $technologies = Technology::whereIn('technologies.id', function ($q) {
            $q->select('course_technology.technology_id')
                ->from('course_technology')
                ->join('career_course', 'career_course.course_id', '=', 'course_technology.course_id');
        })->pluck('name', 'id');

        $this->info("🌎 Importando desde Remotive para {$technologies->count()} tecnologías…");

        foreach ($technologies as $techId => $techName) {

            $this->warn("\n🔎 Procesando: {$techName}");

            $totalFound = $totalNew = $totalDuplicates = 0;

            try {
                // Petición Remotive
                $response = Http::timeout(20)
                    ->get("https://remotive.com/api/remote-jobs", [
                        'search' => $techName
                    ]);

                if ($response->failed()) {
                    $this->error("❌ Error API Remotive");
                    continue;
                }

                $jobs = $response->json()['jobs'] ?? [];
                $totalFound = count($jobs);

                foreach ($jobs as $job) {

                    $externalId = $job['id'] ?? null;
                    $title      = $job['title'] ?? 'N/A';
                    $company    = $job['company_name'] ?? null;
                    $urlJob     = $job['url'] ?? null;
                    $desc       = strtolower(strip_tags($job['description'] ?? ''));

                    // ==============================
                    // MODALIDAD
                    // ==============================
                    $modality = $this->detectModality($job);
                    $isRemote = ($modality === 'remote');

                    // ==============================
                    // UBICACIÓN REMOTIVE
                    // ==============================
                    $location = $job['candidate_required_location'] ?? 'Unknown';
                    [$rawCity, $rawCountryRaw] = $this->extractLocation($location);

                    // normalizar país
                    $country = RemotiveCountry::normalize($rawCountryRaw);

                    // ==============================
                    // GEOLOCALIZACIÓN
                    // ==============================
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

                    // ==============================
                    // DUPLICADOS
                    // ==============================
                    $existing = JobOffer::where('external_id', $externalId)
                        ->where('source', 'Remotive')
                        ->first();

                    if ($existing) {
                        $existing->technologies()->syncWithoutDetaching([$techId]);
                        $totalDuplicates++;
                        continue;
                    }

                    // ==============================
                    // REGIÓN
                    // ==============================
                    $region = RegionMapper::resolve($country);

                    // ==============================
                    // CREAR JOB OFFER
                    // ==============================
                    $offer = JobOffer::create([
                        'title'             => $title,
                        'company'           => $company,
                        'country'           => $country,
                        'city'              => $finalCity,
                        'latitude'          => $lat,
                        'longitude'         => $lng,
                        'modality'          => $modality,
                        'salary_min'        => $this->extractMinSalary($job['salary'] ?? ''),
                        'salary_max'        => $this->extractMaxSalary($job['salary'] ?? ''),
                        'experience_level'  => $this->extractExperience($desc),
                        'education_level'   => $this->extractEducation($desc),
                        'certifications'    => $this->extractCertifications($desc),
                        'skills'            => $this->extractSkills($desc),
                        'requirements'      => $desc,
                        'source'            => 'Remotive',
                        'external_id'       => $externalId,
                        'url'               => $urlJob,
                        'search_query'      => $techName,
               'published_at'      => isset($job['publication_date'])
                                ? Carbon::parse($job['publication_date'])
                                : now(),
                        'region'            => $region,
                    ]);

                    // Pivot technology_job
                    $offer->technologies()->syncWithoutDetaching([$techId]);

                    $totalNew++;
                }

            } catch (\Throwable $e) {
                Log::error("❌ Error Remotive {$techName}: ".$e->getMessage());
            }

            // ==============================
            // MÉTRICAS
            // ==============================
            $today = now()->toDateString();

            if (!TechnologyMetric::where('technology_id', $techId)
                    ->whereDate('run_date', $today)
                    ->where('source', 'Remotive')
                    ->exists())
            {
                TechnologyMetric::create([
                    'technology_id'     => $techId,
                    'technology_name'   => $techName,
                    'jobs_found_count'  => $totalFound,
                    'jobs_new_count'    => $totalNew,
                    'run_date'          => $today,
                    'source'            => 'Remotive',
                ]);
            }

            $this->info("✅ {$techName}: {$totalNew} nuevas / {$totalFound} encontradas");
        }

        $this->line("\n🎯 COMPLETADO");
        $this->line("🛰️ API hits: {$this->stats['api_hits']}");
        $this->line("🗺️ Mapeadas: {$this->stats['mapped']}");
        $this->line("⏭️ Skipped: {$this->stats['skipped']}");
    }



    // ---------------------------------------------------------
    // HELPERS
    // ---------------------------------------------------------

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

        // 1. Tabla cities
        $found = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])
            ->first();

        if ($found) {
            $this->stats['mapped']++;
            return [$found->city, $found->lat, $found->lng];
        }

        // 2. Nominatim
        try {
            $res = Http::withHeaders(['User-Agent' => 'Observatorio/1.0'])
                ->timeout(10)
                ->get("https://nominatim.openstreetmap.org/search", [
                    'q' => "$city, $country",
                    'format' => 'json',
                    'limit' => 1
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
            default => 'no_precisa',
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

    protected function extractExperience(string $txt): ?string
    {
        return match (true) {
            str_contains($txt, 'senior') => 'senior',
            str_contains($txt, 'mid')    => 'mid',
            str_contains($txt, 'junior') => 'junior',
            default => null,
        };
    }

    protected function extractEducation(string $txt): ?string
    {
        return match (true) {
            str_contains($txt, 'bachelor')  => 'bachelor',
            str_contains($txt, 'master')    => 'master',
            str_contains($txt, 'phd')       => 'phd',
            str_contains($txt, 'technical') => 'technical',
            default => null,
        };
    }

    protected function extractCertifications(string $txt): ?string
    {
        $certs = [];
        foreach (['aws','azure','google cloud','scrum','pmp','cisco','ccna','itil'] as $c) {
            if (str_contains($txt, $c)) $certs[] = strtoupper($c);
        }
        return $certs ? implode(', ', $certs) : null;
    }

    protected function extractSkills(string $txt): ?string
    {
        $skills = [];
        foreach (['python','java','php','laravel','react','vue','sql','docker','aws','git','node'] as $s) {
            if (str_contains($txt, $s)) $skills[] = strtoupper($s);
        }
        return $skills ? implode(', ', $skills) : null;
    }
}
