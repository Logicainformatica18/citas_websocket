<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Methodology;
use App\Models\JobOffer;
use App\Models\MethodologyMetric;
use Carbon\Carbon;
use App\Helpers\RegionHelper;
use App\Helpers\CountryNormalizer;
use App\Services\ScraperRunService;

class RemoteOkByMethodologiesCommand extends Command
{
    protected $signature = 'remoteok:methodologies';
    protected $description = '🧭 Importa empleos 100% remotos desde RemoteOK por metodología';

    protected $stats = [
        'found'   => 0,
        'new'     => 0,
        'skipped' => 0,
    ];

    public function handle()
    {
        // ▶️ INICIAR RUN
        $run = ScraperRunService::start(
            $this->signature,
            'RemoteOK',
            'methodologies'
        );

        try {

            // 🔹 Última metodología procesada (cursor)
            $lastMethodologyId = MethodologyMetric::where('source', 'RemoteOK')
                ->orderByDesc('created_at')
                ->value('methodology_id');

            // 🔹 Query base (solo metodologías ISIL)
            $baseQuery = Methodology::whereIn('methodologies.id', function ($q) {
                    $q->select('course_methodology.methodology_id')
                      ->from('course_methodology')
                      ->join('career_course', 'career_course.course_id', '=', 'course_methodology.course_id');
                })
                ->orderBy('methodologies.id');

            $methodologiesQuery = clone $baseQuery;

            if ($lastMethodologyId) {
                $methodologiesQuery->where('methodologies.id', '>', $lastMethodologyId);
            }

            $methodologies = $methodologiesQuery->get();

            // 🔁 Reinicio automático
            if ($methodologies->isEmpty()) {
                $methodologies = $baseQuery->get();
            }

            $this->info("🚀 RemoteOK → procesando {$methodologies->count()} metodologías");

            // 🔢 CONTADORES GLOBALES
            $totalFoundAll    = 0;
            $totalInsertedAll = 0;
            $totalSkippedAll  = 0;

            // 📡 RemoteOK API (una sola llamada)
            $response = Http::timeout(25)->get('https://remoteok.com/api');

            if ($response->failed()) {
                throw new \Exception('RemoteOK API no respondió');
            }

            $jobs = collect($response->json())
                ->skip(1) // aviso legal
                ->filter(fn ($j) => isset($j['position']));

            foreach ($methodologies as $methodology) {

                $methodologyId   = $methodology->id;
                $methodologyName = $methodology->name;

                $this->warn("\n🔎 {$methodologyName}");

                $totalFound = 0;
                $totalNew   = 0;

                foreach ($jobs as $job) {

                    $title = $job['position'] ?? '';
                    $tags  = $job['tags'] ?? [];

                    $text = strtolower($title . ' ' . implode(' ', $tags));

                    // 🧠 Match por metodología
                    if (!str_contains($text, strtolower($methodologyName))) {
                        continue;
                    }

                    $totalFound++;
                    $totalFoundAll++;

                    $externalId = $job['id'] ?? null;

                    // 🔁 DEDUPE
                    if ($externalId && JobOffer::where('source', 'RemoteOK')
                        ->where('external_id', $externalId)
                        ->exists()) {

                        $totalSkippedAll++;
                        continue;
                    }

                    // 🧭 MODALIDAD (RemoteOK = remote)
                    $modality = 'remote';

                    // 🌍 COUNTRY / REGION
                    $locationRaw = strtolower(trim($job['location'] ?? ''));
                    $country = 'Remote';

                    if (preg_match('/remote\s*[-,\/]?\s*(.+)$/i', $locationRaw, $m)) {
                        $country = CountryNormalizer::normalize(trim($m[1]));
                    }

                    $region = RegionHelper::fromCountry($country) ?? 'REMOTE';

                    // 💾 CREAR OFERTA
                    JobOffer::create([
                        'title'        => $title ?: 'N/A',
                        'company'      => $job['company'] ?? null,
                        'country'      => $country,
                        'region'       => $region,
                        'city'         => null,
                        'latitude'     => null,
                        'longitude'    => null,
                        'modality'     => $modality,
                        'source'       => 'RemoteOK',
                        'search_query' => $methodologyName,
                        'external_id'  => $externalId,
                        'url'          => $job['url'] ?? null,
                        'published_at' => isset($job['date'])
                            ? Carbon::parse($job['date'])
                            : now(),
                    ]);

                    $totalNew++;
                    $totalInsertedAll++;
                }

                // 📊 MÉTRICA DIARIA (UNA POR METODOLOGÍA)
                MethodologyMetric::updateOrCreate(
                    [
                        'methodology_id' => $methodologyId,
                        'run_date'       => now()->toDateString(),
                        'source'         => 'RemoteOK',
                    ],
                    [
                        'methodology_name' => $methodologyName,
                        'jobs_found_count' => $totalFound,
                        'jobs_new_count'   => $totalNew,
                        'updated_at'       => now(),
                    ]
                );

                $this->info("✔ {$methodologyName}: {$totalNew} nuevas / {$totalFound}");
            }

            // ✅ RUN OK
            ScraperRunService::success(
                $run,
                $totalFoundAll,
                $totalInsertedAll,
                $totalSkippedAll
            );

            $this->info("\n🟢 RemoteOK (methodologies) finalizado correctamente");

        } catch (\Throwable $e) {

            // ❌ RUN FAILED
            ScraperRunService::failed($run, $e);
            throw $e;
        }
    }
}
