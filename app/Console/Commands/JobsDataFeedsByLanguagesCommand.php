<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Language;
use App\Models\JobOffer;
use App\Models\LanguageMetric;
use Carbon\Carbon;

class JobsDataFeedsByLanguagesCommand extends Command
{
    protected $signature = 'jobsdatafeeds:languages {--country=US} {--pages=1}';
    protected $description = '🌐 Importa empleos tecnológicos desde JobsDataFeeds (RapidAPI) y genera métricas por lenguaje';

    protected string $endpoint =
        'https://daily-international-job-postings.p.rapidapi.com/api/v2/jobs/search';

    /**
     * Queries base (ROLES, no lenguajes)
     */
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

    /**
     * Palabras clave NO tecnológicas (filtro fuerte)
     */
    protected array $blacklist = [
        'clerk', 'bake off', 'cashier', 'accounts payable',
        'accounting', 'finance', 'warehouse', 'operator',
        'assistant', 'sales', 'cook', 'driver',
    ];

    public function handle()
    {
        $country = strtoupper($this->option('country'));
        $pages   = (int) $this->option('pages');

        $languages = Language::whereIn('languages.id', function ($q) {
            $q->select('course_language.language_id')
              ->from('course_language')
              ->join('career_course', 'career_course.course_id', '=', 'course_language.course_id');
        })->pluck('name', 'id');

        $this->info("🚀 JobsDataFeeds iniciado | País {$country}");

        foreach ($languages as $languageId => $languageName) {
            $this->warn("\n💡 Lenguaje: {$languageName}");

            $found = $new = 0;
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
                            $this->error("❌ Error API {$role} p{$page}");
                            continue;
                        }

                        /**
                         * 🔑 CLAVE: ROOT = ARRAY
                         */
                        $jobs = $response->json();
                        if (!is_array($jobs)) {
                            continue;
                        }

                        foreach ($jobs as $job) {
                            $text = strtolower(
                                ($job['title'] ?? '') . ' ' .
                                ($job['description'] ?? '') . ' ' .
                                implode(' ', $job['skills'] ?? [])
                            );

                            // 🚫 Filtro NO TECH
                            if ($this->isBlacklisted($text)) {
                                continue;
                            }

                            // 🔎 El lenguaje debe aparecer en el texto
                            if (!str_contains($text, strtolower($languageName))) {
                                continue;
                            }

                            $found++;

                            $externalId = md5(($job['url'] ?? '') . ($job['title'] ?? ''));

                            $existing = JobOffer::where('external_id', $externalId)->first();
                            if ($existing) {
                                $existing->languages()->syncWithoutDetaching([$languageId]);
                                continue;
                            }

                            $company = $job['hiringOrganization']['name'] ?? null;
                            $countryName =
                                $job['jobLocation'][0]['address']['addressCountry']
                                ?? $country;

                            $city =
                                $job['jobLocation'][0]['address']['addressLocality']
                                ?? null;

                            $region =
                                $job['jobLocation'][0]['address']['addressRegion']
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
                                'requirements' => strip_tags($job['description'] ?? null),
                                'source'       => 'JobsDataFeeds',
                                'external_id'  => $externalId,
                                'url'          => $job['url'] ?? null,
                                'search_query' => $role,
                                'published_at' => isset($job['datePosted'])
                                    ? Carbon::parse($job['datePosted'])
                                    : now(),
                            ]);

                            $offer->languages()->syncWithoutDetaching([$languageId]);

                            $new++;
                            $rolesBreakdown[$role] = ($rolesBreakdown[$role] ?? 0) + 1;
                        }

                        sleep(1);
                    } catch (\Throwable $e) {
                        Log::error("JobsDataFeeds {$languageName}: {$e->getMessage()}");
                    }
                }
            }

            // 📊 MÉTRICAS DIARIAS
            $today = now()->toDateString();
            if (!LanguageMetric::whereDate('run_date', $today)
                ->where('language_id', $languageId)
                ->where('source', 'JobsDataFeeds')
                ->exists()) {

                LanguageMetric::create([
                    'language_id'      => $languageId,
                    'language_name'    => $languageName,
                    'jobs_found_count' => $found,
                    'jobs_new_count'   => $new,
                    'roles_breakdown'  => $rolesBreakdown,
                    'run_date'         => Carbon::today(),
                    'source'           => 'JobsDataFeeds',
                ]);
            }

            $this->info("✅ {$languageName}: {$new} nuevas | {$found} encontradas");
        }

        $this->info("🎯 JobsDataFeeds COMPLETADO");
    }

    /* ================= HELPERS ================= */

    protected function detectModality(string $text): string
    {
        return match (true) {
            str_contains($text, 'remote'),
            str_contains($text, 'work from home'),
            str_contains($text, 'anywhere') => 'remote',

            str_contains($text, 'hybrid') => 'hybrid',

            default => 'no_remote',
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
