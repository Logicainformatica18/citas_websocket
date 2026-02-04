<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Language;
use App\Models\JobOffer;
use App\Models\LanguageMetric;
use Carbon\Carbon;
use App\Helpers\RegionHelper;
use App\Helpers\CountryNormalizer;
use App\Services\ScraperRunService;

class RemoteOkByLanguagesCommand extends Command
{
    protected $signature = 'remoteok:languages';
    protected $description = '🌍 Importa empleos 100% remotos desde RemoteOK, clasificando país y región cuando sea posible.';

    protected $stats = [
        'found'  => 0,
        'new'    => 0,
        'skipped'=> 0,
    ];

    public function handle()
{
    // ▶️ INICIAR RUN
    $run = \App\Services\ScraperRunService::start(
        $this->signature,
        'RemoteOK',
        'languages'
    );

    try {

        // 🔹 Último lenguaje procesado (cursor)
        $lastLanguageId = LanguageMetric::where('source', 'RemoteOK')
            ->orderByDesc('created_at')
            ->value('language_id');

        // 🔹 Query base (solo lenguajes ISIL)
        $baseQuery = Language::whereIn('languages.id', function ($q) {
                $q->select('course_language.language_id')
                  ->from('course_language')
                  ->join('career_course', 'career_course.course_id', '=', 'course_language.course_id');
            })
            ->orderBy('languages.id');

        $languagesQuery = clone $baseQuery;

        if ($lastLanguageId) {
            $languagesQuery->where('languages.id', '>', $lastLanguageId);
        }

        $languages = $languagesQuery->get();

        // 🔁 Reinicio automático
        if ($languages->isEmpty()) {
            $languages = $baseQuery->get();
        }

        $this->info("🚀 RemoteOK → procesando {$languages->count()} lenguajes");

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

        foreach ($languages as $language) {

            $languageId   = $language->id;
            $languageName = $language->name;

            $this->warn("\n🔎 {$languageName}");

            $totalFound = 0;
            $totalNew   = 0;

            foreach ($jobs as $job) {

                $title       = $job['position'] ?? '';
                $tags        = $job['tags'] ?? [];
                $text        = strtolower($title . ' ' . implode(' ', $tags));

                // 🧠 Match por lenguaje
                if (!str_contains($text, strtolower($languageName))) {
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

                // 🌍 COUNTRY / REGION (limpieza simple)
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
                    'search_query' => $languageName,
                    'external_id'  => $externalId,
                    'url'          => $job['url'] ?? null,
                    'published_at' => isset($job['date'])
                        ? Carbon::parse($job['date'])
                        : now(),
                ]);

                $totalNew++;
                $totalInsertedAll++;
            }

            // 📊 MÉTRICA DIARIA (UNA POR LENGUAJE)
            LanguageMetric::updateOrCreate(
                [
                    'language_id' => $languageId,
                    'run_date'    => now()->toDateString(),
                    'source'      => 'RemoteOK',
                ],
                [
                    'language_name'    => $languageName,
                    'jobs_found_count' => $totalFound,
                    'jobs_new_count'   => $totalNew,
                    'updated_at'       => now(),
                ]
            );

            $this->info("✔ {$languageName}: {$totalNew} nuevas / {$totalFound}");
        }

        // ✅ RUN OK
        \App\Services\ScraperRunService::success(
            $run,
            $totalFoundAll,
            $totalInsertedAll,
            $totalSkippedAll
        );

        $this->info("\n🟢 RemoteOK finalizado correctamente");

    } catch (\Throwable $e) {

        // ❌ RUN FAILED
        \App\Services\ScraperRunService::failed($run, $e);
        throw $e;
    }
}

}
