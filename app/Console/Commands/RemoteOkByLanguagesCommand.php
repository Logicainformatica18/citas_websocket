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
 use App\Services\SourceStatusService;
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
    $run = ScraperRunService::start(
        $this->signature,
        'RemoteOK',
        'languages'
    );

    $source = 'remoteok_languages';

    SourceStatusService::start(
        source: $source,
        runId: $run->id,
        config: [],
        apiUrl: 'https://remoteok.com/api'
    );

    $connectionOk = false;
    $startedAt = now();

    SourceStatusService::progress($source, 0, 0, 0);

    try {

        $lastLanguageId = LanguageMetric::where('source', 'RemoteOK')
            ->orderByDesc('created_at')
            ->value('language_id');

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

        if ($languages->isEmpty()) {
            $languages = $baseQuery->get();
        }

        $this->info("🚀 RemoteOK → procesando {$languages->count()} lenguajes");

        $totalFoundAll    = 0;
        $totalInsertedAll = 0;
        $totalSkippedAll  = 0;

        // 📡 RemoteOK API
        $response = Http::timeout(25)->get('https://remoteok.com/api');

        if ($response->failed()) {
            SourceStatusService::connectionFailed($source, 'API failed');
            throw new \Exception('RemoteOK API no respondió');
        }

        $connectionOk = true;

        $jobs = collect($response->json())
            ->skip(1)
            ->filter(fn ($j) => isset($j['position']));

        foreach ($languages as $language) {

            $languageId   = $language->id;
            $languageName = $language->name;

            $this->warn("\n🔎 {$languageName}");

            $totalFound = 0;
            $totalNew   = 0;

            foreach ($jobs as $job) {

                $title = $job['position'] ?? '';
                $tags  = $job['tags'] ?? [];
                $text  = strtolower($title . ' ' . implode(' ', $tags));

                if (!str_contains($text, strtolower($languageName))) {
                    continue;
                }

                $totalFound++;
                $totalFoundAll++;

                $externalId = $job['id'] ?? null;

                if ($externalId && JobOffer::where('source', 'RemoteOK')
                    ->where('external_id', $externalId)
                    ->exists()) {

                    $totalSkippedAll++;
                    continue;
                }

                $modality = 'remote';

                $locationRaw = strtolower(trim($job['location'] ?? ''));
                $country = 'Remote';

                if (preg_match('/remote\s*[-,\/]?\s*(.+)$/i', $locationRaw, $m)) {
                    $country = CountryNormalizer::normalize(trim($m[1]));
                }

                $region = RegionHelper::fromCountry($country) ?? 'REMOTE';

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

            SourceStatusService::progress(
                $source,
                $totalFoundAll,
                $totalInsertedAll,
                $totalSkippedAll
            );
        }

        ScraperRunService::success(
            $run,
            $totalFoundAll,
            $totalInsertedAll,
            $totalSkippedAll
        );

        if ($connectionOk) {
            SourceStatusService::connectionOk($source);
        }

        SourceStatusService::success(
            source: $source,
            runId: $run->id,
            found: $totalFoundAll,
            inserted: $totalInsertedAll,
            skipped: $totalSkippedAll,
            durationSeconds: now()->diffInSeconds($startedAt)
        );

        $this->info("\n🟢 RemoteOK finalizado correctamente");

    } catch (\Throwable $e) {

        ScraperRunService::failed($run, $e);

        SourceStatusService::failed(
            source: $source,
            runId: $run->id,
            e: $e,
            durationSeconds: now()->diffInSeconds($startedAt)
        );

        throw $e;
    }
}

}
