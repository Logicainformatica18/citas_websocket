<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Language;
use App\Models\JobOffer;
use App\Models\LanguageMetric;
use Carbon\Carbon;

class JobsDataFeedsByLanguagesCommand extends Command
{
    protected $signature = 'jobsdatafeeds:languages {--country=US} {--pages=2}';
    protected $description = '🌐 JobsDataFeeds PRO + Resume + Smart Filter';

    protected string $endpoint =
        'https://daily-international-job-postings.p.rapidapi.com/api/v2/jobs/search';

    protected array $baseQueries = [
        'software developer',
        'software engineer',
        'backend developer',
        'frontend developer',
        'full stack developer',
        'data engineer',
        'data scientist',
        'devops engineer',
        'cloud engineer',
    ];

    protected array $blacklist = [
        'clerk','cashier','warehouse','cook','driver','sales assistant'
    ];

    public function handle()
    {
        $country = strtoupper($this->option('country'));
        $pages   = (int) $this->option('pages');

        // 🔥 RESUME (cache)
        $lastLanguageId = Cache::get('jobsdatafeeds_last_language');

        $languagesQuery = Language::orderBy('id');

        if ($lastLanguageId) {
            $languagesQuery->where('id', '>', $lastLanguageId);
        }

        $languages = $languagesQuery->pluck('name', 'id');

        if ($languages->isEmpty()) {
            $languages = Language::orderBy('id')->pluck('name', 'id');
        }

        $this->info("🚀 JobsDataFeeds PRO | País {$country}");

        foreach ($languages as $languageId => $languageName) {

            try {

                $this->warn("\n💡 Lenguaje: {$languageName}");

                $found = 0;
                $new = 0;
                $rolesBreakdown = [];

                foreach ($this->baseQueries as $role) {

                    for ($page = 1; $page <= $pages; $page++) {

                        try {

                            $response = Http::withHeaders([
                                'x-rapidapi-host' => config('services.jobsdatafeeds.host'),
                                'x-rapidapi-key'  => config('services.jobsdatafeeds.key'),
                            ])->timeout(30)->get($this->endpoint, [
                                'format'      => 'json',
                                'countryCode' => $country,
                                'page'        => $page,
                                'q'           => $role,
                            ]);

                            if ($response->failed()) {
                                continue;
                            }

                            // ✅ FIX CRÍTICO (soporta ambos formatos)
                            $data = $response->json();

                            $jobs = is_array($data)
                                ? ($data['data'] ?? $data)
                                : [];

                            if (empty($jobs)) {
                                continue;
                            }

                            foreach ($jobs as $job) {

                                $text = strtolower(
                                    ($job['title'] ?? '') . ' ' .
                                    ($job['description'] ?? '') . ' ' .
                                    implode(' ', $job['skills'] ?? [])
                                );

                                if ($this->isBlacklisted($text)) {
                                    continue;
                                }

                                // 🧠 SCORE FLEXIBLE
                                $score = $this->calculateLanguageScore($text, $languageName);

                                if ($score === 0) {
                                    continue;
                                }

                                $found++;

                                $externalId = md5(($job['url'] ?? '') . ($job['title'] ?? ''));

                                if (JobOffer::where('external_id', $externalId)->exists()) {
                                    continue;
                                }

                                $company = $job['hiringOrganization']['name'] ?? null;

                                $countryName =
                                    $job['jobLocation'][0]['address']['addressCountry']
                                    ?? $country;

                                $city =
                                    $job['jobLocation'][0]['address']['addressLocality']
                                    ?? null;

                                $lat = $job['jobLocation'][0]['latitude'] ?? null;
                                $lng = $job['jobLocation'][0]['longitude'] ?? null;

                                $offer = JobOffer::create([
                                    'title'        => $job['title'] ?? 'N/A',
                                    'company'      => $company,
                                    'country'      => $countryName,
                                    'city'         => $city,
                                    'latitude'     => $lat,
                                    'longitude'    => $lng,
                                    'modality'     => $this->detectModality($text),
                                    'skills'       => implode(', ', $job['skills'] ?? []),
                                    'requirements' => strip_tags($job['description'] ?? ''),
                                    'source'       => 'JobsDataFeeds',
                                    'external_id'  => $externalId,
                                    'url'          => $job['url'] ?? null,
                                    'search_query' => $role,
                                    'published_at' => isset($job['datePosted'])
                                        ? Carbon::parse($job['datePosted'])
                                        : now(),
                                ]);

                             $detectedLanguages = $this->detectLanguagesFromText($text);

foreach ($detectedLanguages as $langId) {
    $offer->languages()->syncWithoutDetaching([$langId]);
}

                                $new++;
                                $rolesBreakdown[$role] = ($rolesBreakdown[$role] ?? 0) + 1;
                            }

                            usleep(500000);

                        } catch (\Throwable $e) {
                            Log::error("Página error {$languageName}: {$e->getMessage()}");
                        }
                    }
                }

                // 📊 MÉTRICAS
                LanguageMetric::updateOrCreate(
                    [
                        'language_id' => $languageId,
                        'run_date' => Carbon::today(),
                        'source' => 'JobsDataFeeds',
                    ],
                    [
                        'language_name'    => $languageName,
                        'jobs_found_count' => $found,
                        'jobs_new_count'   => $new,
                        'roles_breakdown'  => $rolesBreakdown,
                    ]
                );

                // 🔥 GUARDAR PROGRESO
                Cache::put('jobsdatafeeds_last_language', $languageId);

                $this->info("✅ {$languageName}: {$new} nuevas | {$found} encontradas");

            } catch (\Throwable $e) {
                Log::error("Lenguaje falló {$languageName}: {$e->getMessage()}");
                continue;
            }
        }

        $this->info("🎯 JobsDataFeeds PRO COMPLETADO");
    }
protected function detectLanguagesFromText(string $text): array
{
    $map = [
        'javascript' => ['javascript','js','react','node'],
        'python' => ['python','django','flask'],
        'java' => ['java','spring'],
        'php' => ['php','laravel'],
        'sql' => ['sql','postgres','mysql'],
    ];

    $languages = Language::pluck('id', 'name')->mapWithKeys(fn($id, $name) => [strtolower($name) => $id]);

    $detected = [];

    foreach ($map as $lang => $keywords) {
        foreach ($keywords as $word) {
            if (str_contains($text, $word) && isset($languages[$lang])) {
                $detected[] = $languages[$lang];
                break;
            }
        }
    }

    return $detected;
}
    /* ================= HELPERS ================= */

    protected function calculateLanguageScore(string $text, string $language): int
    {
        $language = strtolower($language);

        $aliases = [
            'javascript' => ['javascript','js','node','react'],
            'python' => ['python','py','django','flask'],
            'java' => ['java','spring'],
            'php' => ['php','laravel'],
            'c#' => ['c#','dotnet','.net'],
        ];

        $keywords = $aliases[$language] ?? [$language];

        $score = 0;

        foreach ($keywords as $word) {
            if (str_contains($text, $word)) {
                $score++;
            }
        }

        return $score;
    }

    protected function detectModality(string $text): string
    {
        return match (true) {
            str_contains($text, 'remote'),
            str_contains($text, 'work from home') => 'remote',

            str_contains($text, 'hybrid') => 'hybrid',

            default => 'no_precisa',
        };
    }

    protected function isBlacklisted(string $text): bool
    {
        foreach ($this->blacklist as $word) {
            if (str_contains($text, $word)) {
                return true;
            }
        }
        return false;
    }
}