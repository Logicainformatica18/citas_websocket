<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Technology;
use App\Models\JobOffer;
use App\Models\City;
use App\Helpers\CountryNormalizer;
use Carbon\Carbon;
use App\Services\ScraperRunService;

class USAJOBSByTechnologiesCommand extends Command
{
    protected $signature = 'usajobs:technologies {--pages=1}';

    protected $description = '🇺🇸 Importa ofertas laborales desde USAJOBS por tecnologías del PE ISIL.';

    protected $stats = [
        'api_hits' => 0,
        'mapped'   => 0,
        'skipped'  => 0,
    ];

    public function handle()
    {
        /* ===========================
           ▶️ INICIAR RUN
        ============================ */
        $run = ScraperRunService::start(
            $this->signature,
            'USAJOBS',
            'technologies'
        );

        try {

            /* ===========================
               1️⃣ TECNOLOGÍAS DEL PE ISIL
            ============================ */
            $technologies = Technology::whereIn('technologies.id', function ($q) {
                    $q->select('ct.technology_id')
                      ->from('course_technology as ct')
                      ->join('career_course as cc', 'cc.course_id', '=', 'ct.course_id');
                })
                ->orderBy('technologies.id')
                ->get();

            $this->info("🇺🇸 USAJOBS → {$technologies->count()} tecnologías PE ISIL");

            /* ===========================
               CONTADORES GLOBALES
            ============================ */
            $totalFoundAll    = 0;
            $totalInsertedAll = 0;
            $totalSkippedAll  = 0;

            foreach ($technologies as $technology) {

                $techId   = $technology->id;
                $techName = $technology->name;

                $this->warn("\n🔎 {$techName}");

                $totalFound = 0;
                $totalNew   = 0;

                for ($page = 1; $page <= (int) $this->option('pages'); $page++) {

                    /* ===========================
                       🔵 REQUEST USAJOBS
                    ============================ */
                    $response = Http::timeout(40)
                        ->withHeaders([
                            'Host'              => 'data.usajobs.gov',
                            'User-Agent'        => 'Isil Scraper (contacto@isil.pe)',
                            'Authorization-Key' => config('services.usajobs.key'),
                        ])
                        ->get('https://data.usajobs.gov/api/Search', [
                            'Keyword'        => $techName,
                            'ResultsPerPage' => 100,
                            'Page'           => $page,
                        ]);

                    $this->stats['api_hits']++;

                    if ($response->failed()) {
                        Log::error('USAJOBS API ERROR', [
                            'technology' => $techName,
                            'status'     => $response->status(),
                            'body'       => $response->body(),
                        ]);
                        break;
                    }

                    $items = $response->json(
                        'SearchResult.SearchResultItems',
                        []
                    );

                    if (empty($items)) {
                        break;
                    }

                    foreach ($items as $item) {

                        $job = $item['MatchedObjectDescriptor'] ?? [];
                        $totalFound++;
                        $totalFoundAll++;

                        $externalId = 'usajobs-' . ($job['PositionID'] ?? uniqid());

                        /* ===========================
                           DEDUPE + RELACIÓN
                        ============================ */
                        $existing = JobOffer::where('source', 'usajobs')
                            ->where('external_id', $externalId)
                            ->first();

                        if ($existing) {
                            $existing->technologies()
                                ->syncWithoutDetaching([$techId]);
                            $totalSkippedAll++;
                            continue;
                        }

                        /* ===========================
                           UBICACIÓN
                        ============================ */
                        $locationRaw = $job['PositionLocation'][0]['LocationName'] ?? null;

                        $cityMatch = $locationRaw
                            ? City::whereRaw('LOWER(city_ascii) = ?', [strtolower($locationRaw)])
                                ->orWhereRaw('LOWER(city) = ?', [strtolower($locationRaw)])
                                ->first()
                            : null;

                        if ($cityMatch) {
                            $city    = $cityMatch->city;
                            $lat     = $cityMatch->lat;
                            $lng     = $cityMatch->lng;
                            $country = CountryNormalizer::normalize($cityMatch->country);
                        } else {
                            $city    = 'Washington D.C.';
                            $lat     = 38.8951;
                            $lng     = -77.0364;
                            $country = 'Estados Unidos';
                        }

                        /* ===========================
                           FECHA + SALARIO
                        ============================ */
                        $publishedAt = isset($job['PublicationStartDate'])
                            ? Carbon::parse($job['PublicationStartDate'])
                            : now();

                        $salaryMin = $this->cleanSalary(
                            $job['PositionRemuneration'][0]['MinimumRange'] ?? null
                        );

                        $salaryMax = $this->cleanSalary(
                            $job['PositionRemuneration'][0]['MaximumRange'] ?? null
                        );

                        $compType = $this->normalizeCompType(
                            $job['PositionRemuneration'][0]['RateIntervalCode'] ?? null
                        );

                        /* ===========================
                           CREAR OFERTA
                        ============================ */
                        $offer = JobOffer::create([
                            'title'             => $job['PositionTitle'] ?? '',
                            'company'           => $job['OrganizationName'] ?? '',
                            'country'           => $country,
                            'city'              => $city,
                            'latitude'          => $lat,
                            'longitude'         => $lng,
                            'modality'          => 'presencial',
                            'salary_min'        => $salaryMin,
                            'salary_max'        => $salaryMax,
                            'currency'          => 'USD',
                            'compensation_type' => $compType,
                            'source'            => 'usajobs',
                            'external_id'       => $externalId,
                            'url'               => $job['PositionURI'] ?? null,
                            'search_query'      => $techName,
                            'published_at'      => $publishedAt,
                        ]);

                        $offer->technologies()
                            ->syncWithoutDetaching([$techId]);

                        $totalNew++;
                        $totalInsertedAll++;
                        $this->stats['mapped']++;
                    }

                    sleep(1); // ⏱️ rate limit USAJOBS
                }

                $this->info("✔ {$techName}: {$totalNew} nuevas / {$totalFound}");
            }

            ScraperRunService::success(
                $run,
                $totalFoundAll,
                $totalInsertedAll,
                $totalSkippedAll
            );

            $this->info("\n🟢 USAJOBS tecnologías OK");

        } catch (\Throwable $e) {
            ScraperRunService::failed($run, $e);
            throw $e;
        }
    }

    /* ===========================
       HELPERS
    ============================ */

    protected function cleanSalary(?string $value): ?float
    {
        if (!$value) return null;
        return (float) str_replace([',', ' '], '', $value);
    }

    protected function normalizeCompType(?string $code): ?string
    {
        return match ($code) {
            'PA' => 'yearly',
            'PH' => 'hourly',
            'PM' => 'monthly',
            'PD' => 'daily',
            default => null,
        };
    }
}
