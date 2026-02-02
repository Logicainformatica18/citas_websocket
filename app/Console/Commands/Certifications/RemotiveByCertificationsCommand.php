<?php

namespace App\Console\Commands\Certifications;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Certification;
use App\Models\JobOffer;
use App\Models\CertificationMetric;
use App\Models\City;
use Carbon\Carbon;
use App\Helpers\RemotiveCountry;
use App\Helpers\RegionMapper;

class RemotiveByCertificationsCommand extends Command
{
    protected $signature = 'remotive:certifications';

    protected $description = 'Importa ofertas desde Remotive por certificaciones usando keywordss desde BD.';

    protected $stats = [
        'api_hits' => 0,
        'mapped'   => 0,
        'skipped'  => 0,
    ];

    public function handle()
    {
        /**
         * ✅ Certificaciones con keywords
         */
        $certifications = Certification::select('id', 'name', 'keywords')->get();

        if ($certifications->isEmpty()) {
            $this->error('❌ No hay certificaciones');
            return;
        }

        $this->info("🌎 Importando desde Remotive para {$certifications->count()} certificaciones…");

        foreach ($certifications as $cert) {

            if (empty($cert->keywords)) {
                $this->warn("⚠️ {$cert->name} sin keywords, se omite");
                continue;
            }

            // 🔑 keywordss limpias
            $keywordss = collect(explode(',', strtolower($cert->keywords)))
                ->map(fn ($k) => trim($k))
                ->filter(fn ($k) => strlen($k) >= 2)
                ->values()
                ->all();

            if (empty($keywordss)) {
                $this->warn("⚠️ {$cert->name} keywords inválida");
                continue;
            }

            $this->warn("\n🏅 Procesando: {$cert->name}");
            $this->line("🔑 Keywordss: " . implode(', ', $keywordss));

            $totalFound = $totalNew = $totalDuplicates = 0;

            try {
                /**
                 * 📡 Remotive search (por keywords principal)
                 * Usamos la primera keywords como search base
                 */
                $response = Http::timeout(20)
                    ->get('https://remotive.com/api/remote-jobs', [
                        'search' => $keywordss[0],
                    ]);

                $this->stats['api_hits']++;

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

                    /**
                     * 🧠 Match flexible por keywords (title + description)
                     */
                    $text = strtolower($title . ' ' . $desc);

                    $matched = false;
                    foreach ($keywordss as $kw) {
                        if (str_contains($text, $kw)) {
                            $matched = true;
                            break;
                        }
                    }

                    if (!$matched) {
                        continue;
                    }

                    /**
                     * ⚙️ Modalidad
                     */
                    $modality = $this->detectModality($job);
                    $isRemote = ($modality === 'remote');

                    /**
                     * 🌍 Ubicación
                     */
                    $locationStr = $job['candidate_required_location'] ?? 'Unknown';
                    [$rawCity, $rawCountry] = $this->extractLocation($locationStr);

                    $country = RemotiveCountry::normalize($rawCountry);

                    /**
                     * 🗺️ Geolocalización
                     */
                    if ($isRemote) {
                        $finalCity = 'Remote';
                        $lat = $lng = null;
                    } else {
                        [$finalCity, $lat, $lng] = $this->tryGeocode($rawCity, $country);

                        if (!$lat || !$lng) {
                            $this->stats['skipped']++;
                            continue;
                        }
                    }

                    /**
                     * 🛑 Dedupe
                     */
                    $existing = JobOffer::where('external_id', $externalId)
                        ->where('source', 'Remotive')
                        ->first();

                    if ($existing) {
                        if (method_exists($existing, 'certifications')) {
                            $existing->certifications()
                                ->syncWithoutDetaching([$cert->id]);
                        }
                        $totalDuplicates++;
                        continue;
                    }

                    /**
                     * 🌐 Región
                     */
                    $region = RegionMapper::resolve($country);

                    /**
                     * 💾 Insert JobOffer
                     */
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
                        'requirements'     => $desc,
                        'source'           => 'Remotive',
                        'external_id'      => $externalId,
                        'url'              => $urlJob,
                        'search_query'     => implode(', ', $keywordss),
                        'published_at'     => isset($job['publication_date'])
                            ? Carbon::parse($job['publication_date'])
                            : now(),
                        'region'           => $region,
                    ]);

                    if (method_exists($offer, 'certifications')) {
                        $offer->certifications()
                            ->syncWithoutDetaching([$cert->id]);
                    }

                    $totalNew++;
                }

            } catch (\Throwable $e) {
                Log::error("❌ Remotive {$cert->name}: " . $e->getMessage());
            }

            /**
             * 📊 Métrica diaria
             */
            $today = now()->toDateString();

            if ($totalFound > 0) {
                CertificationMetric::updateOrCreate(
                    [
                        'certification_id' => $cert->id,
                        'run_date'         => $today,
                        'source'           => 'Remotive',
                    ],
                    [
                        'total'      => $totalFound,
                        'country'    => 'Multiple',
                        'updated_at' => now(),
                    ]
                );
            }

            $this->info("✅ {$cert->name}: {$totalNew} nuevas / {$totalFound} encontradas");
        }

        $this->line("\n🎯 REMOTIVE CERTIFICATIONS COMPLETADO");
    }

    // =====================================================
    // HELPERS (idénticos al original)
    // =====================================================

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

        $found = City::whereRaw('LOWER(city_ascii) = ?', [strtolower($city)])->first();

        if ($found) {
            $this->stats['mapped']++;
            return [$found->city, $found->lat, $found->lng];
        }

        try {
            $res = Http::withHeaders(['User-Agent' => 'Observatorio/1.0'])
                ->timeout(10)
                ->get('https://nominatim.openstreetmap.org/search', [
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
}
