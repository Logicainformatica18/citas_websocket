<?php

namespace App\Console\Commands\TrendsTechnologies;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\GlobalTrend;

class GithubTrendsCommand extends Command
{
    protected $signature = 'github:trends
        {--topic=ai}
        {--per_page=50}
        {--year=}
        {--mode=trends : trends|languages|topics|all-topics}';

    protected $description = 'Extrae tendencias tecnológicas desde GitHub (repos, lenguajes, topics) y las guarda en global_trends.';

    public function handle()
    {
        $mode = $this->option('mode') ?? 'trends';

        return match ($mode) {
            'languages'   => $this->extractLanguages(),
            'topics'      => $this->extractTopics(),
            'all-topics'  => $this->extractAllTopics(),
            default       => $this->extractRepoTrends(),
        };
    }

    // =========================================================================
    // 1️⃣ EXTRAER TENDENCIAS POR REPOS (TOP STARS)
    // =========================================================================
    private function extractRepoTrends()
    {
        $topic    = $this->option('topic');
        $perPage  = (int)$this->option('per_page');
        $year     = $this->option('year') ?? now()->year;

        $url = "https://api.github.com/search/repositories?q=topic:{$topic}&sort=stars&order=desc&per_page={$perPage}";
        $this->info("🔎 Consultando repos trending para topic={$topic}");

        $response = Http::retry(3, 2000)
            ->withHeaders([
                'Accept'        => 'application/vnd.github+json',
                'User-Agent'    => 'Observatorio-ISIL',
                'Authorization' => 'Bearer ' . config('services.github.token'),
            ])
            ->timeout(60)
            ->get($url);

        if ($response->failed()) {
            $this->error("❌ Error GitHub API: " . $response->body());
            return Command::FAILURE;
        }

        $items = $response->json()['items'] ?? [];
        $rank = 1;

        foreach ($items as $repo) {

            $repoNodeId = $repo['node_id'];              // ⭐ Identificador real de GitHub
            $itemName   = $repo['full_name'];            // nombre visible
            $value      = (int)($repo['stargazers_count'] ?? 0);

            // Buscar si ya existe este repo en este topic y año
            $existing = GlobalTrend::where('repo_node_id', $repoNodeId)
                ->where('subcategory', $topic)
                ->where('year', $year)
                ->first();

            if ($existing) {
                // 🔄 UPDATE
                $existing->update([
                    'value'        => $value,
                    'rank'         => $rank,
                    'summary'      => $repo['description'],
                    'metadata'     => json_encode($repo),
                    'updated_at'   => now(),
                ]);
            } else {
                // ➕ INSERT
                GlobalTrend::create([
                    'repo_node_id' => $repoNodeId,
                    'source'       => 'github',
                    'source_url'   => $repo['html_url'] ?? null,
                    'source_type'  => 'api',

                    'category'     => 'github_repos',
                    'subcategory'  => $topic,

                    'item_name'    => $itemName,
                    'item_type'    => 'technology',
                    'summary'      => $repo['description'],

                    'year'         => $year,
                    'quarter'      => now()->quarter,
                    'value'        => $value,
                    'rank'         => $rank,

                    'metadata'     => json_encode($repo),
                ]);
            }

            $rank++;
        }

        $this->info("✅ Repos trending guardados / actualizados para topic={$topic}");
        return Command::SUCCESS;
    }

    // =========================================================================
    // 2️⃣ EXTRAER LENGUAJES (simple ejemplo)
    // =========================================================================
    private function extractLanguages()
    {
        $this->info("🌐 Extrayendo lenguajes más usados de GitHub…");

        $url = "https://api.github.com/repos/github/gitignore/languages";

        $response = Http::retry(3, 2000)
            ->withHeaders([
                'Accept'        => 'application/vnd.github+json',
                'User-Agent'    => 'Observatorio-ISIL',
                'Authorization' => 'Bearer ' . config('services.github.token'),
            ])
            ->timeout(60)
            ->get($url);

        if ($response->failed()) {
            $this->error("❌ Error GitHub languages: " . $response->body());
            return Command::FAILURE;
        }

        $languages = $response->json();
        $rank      = 1;
        $year      = now()->year;

        foreach ($languages as $language => $bytes) {

            $existing = GlobalTrend::where('item_name', $language)
                ->where('category', 'github_languages')
                ->where('year', $year)
                ->first();

            if ($existing) {
                $existing->update([
                    'value' => $bytes,
                    'rank'  => $rank,
                ]);
            } else {
                GlobalTrend::create([
                    'source'        => 'github',
                    'source_url'    => 'https://github.com/github/gitignore',
                    'source_type'   => 'api',

                    'category'      => 'github_languages',
                    'subcategory'   => 'global',

                    'item_name'     => $language,
                    'item_type'     => 'technology',

                    'summary'       => "Lenguaje detectado automáticamente.",
                    'year'          => $year,
                    'quarter'       => now()->quarter,
                    'value'         => $bytes,
                    'rank'          => $rank,

                    'metadata'      => json_encode(['bytes' => $bytes]),
                ]);
            }

            $rank++;
        }

        $this->info("✅ Lenguajes guardados / actualizados.");
        return Command::SUCCESS;
    }

    // =========================================================================
    // 3️⃣ PROCESAR TODOS LOS TOPICS (batch)
    // =========================================================================
    private function extractAllTopics()
    {
        $topics = [
            'ai', 'machine-learning', 'deep-learning', 'data-science',
            'javascript', 'typescript', 'python', 'php', 'java', 'csharp',
            'golang', 'rust', 'ruby', 'swift', 'kotlin',
            'devops', 'cloud', 'docker', 'kubernetes',
            'cybersecurity', 'blockchain', 'web-development',
            'backend', 'frontend', 'fullstack', 'big-data'
        ];

        $this->info("🚀 Ejecutando GitHub Trends para múltiples topics…");

        foreach ($topics as $topic) {
            $this->info("──────────────────────────────────");
            $this->info("🔎 Procesando topic: {$topic}");
            $this->info("──────────────────────────────────");

            $this->option('topic', $topic);
            $this->extractRepoTrends();

            sleep(2); // Evitar rate limit
        }

        $this->info("🎉 Finalizado el procesamiento global de topics.");
        return Command::SUCCESS;
    }
}
