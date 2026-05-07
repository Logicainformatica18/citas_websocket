<?php

namespace App\Console\Commands\Trends\Tavily;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\EntityTrend;

class DiscoverCertificationTrendsTavilyCommand extends Command
{
    protected $signature = 'trends:discover-certifications-tavily
                            {--limit=20 : Cantidad máxima de certificaciones}
                            {--sleep=10 : Segundos entre requests}';

    protected $description =
        'Descubre tendencias de certificaciones usando Tavily AI';

    /* =========================================================
       HANDLE
    ========================================================= */
    public function handle()
    {
        $year = now()->year;

        $quarter = now()->quarter;

        $limit = (int) $this->option('limit');

        $sleep = (int) $this->option('sleep');

        $this->info(
            "🔍 Descubriendo tendencias CERTIFICACIONES con Tavily"
        );

        $certifications = $this->getCertifications($limit);

        if (empty($certifications)) {

            $this->warn(
                '🟢 No hay certificaciones pendientes'
            );

            return Command::SUCCESS;
        }

        foreach ($certifications as $certification) {

            try {

                $query = $this->buildQuery(
                    $certification['name']
                );

                $response = $this->tavilySearch(
                    $query
                );

                if (
                    !is_array($response) ||
                    empty($response['results'])
                ) {

                    throw new \Exception(
                        'Respuesta Tavily inválida'
                    );
                }

                foreach (
                    $response['results']
                    as $result
                ) {

                    $title = trim(
                        $result['title'] ?? ''
                    );

                    $url = trim(
                        $result['url'] ?? ''
                    );

                    $content = trim(
                        $result['content'] ?? ''
                    );

                    $score = round(
                        (($result['score'] ?? 0) * 100),
                        2
                    );

                    /*
                    =========================================
                    NORMALIZAR URL
                    =========================================
                    */

                    $url = strtok($url, '?');

                    $url = rtrim($url, '/');

                    if (!$title || !$url) {
                        continue;
                    }

                    /*
                    =========================================
                    DUPLICADOS
                    =========================================
                    */

                    $exists = EntityTrend::where(
                        'market_entity_id',
                        $certification['id']
                    )

                    ->where(function ($q) use (
                        $url,
                        $title
                    ) {

                        $q->where(
                            'source_url',
                            $url
                        );

                        $q->orWhereRaw(
                            'LOWER(TRIM(source_title)) = ?',
                            [
                                strtolower(
                                    trim($title)
                                )
                            ]
                        );
                    })

                    ->exists();

                    if ($exists) {
                        continue;
                    }

                    /*
                    =========================================
                    TREND NAME
                    =========================================
                    */

                    $trendName =
                        $this->extractTrendName(
                            $certification['name'],
                            $title,
                            $content
                        );

                    /*
                    =========================================
                    SAVE
                    =========================================
                    */

                    EntityTrend::create([

                        'market_entity_id' =>
                            $certification['id'],

                        'year' =>
                            $year,

                        'quarter' =>
                            $quarter,

                        'trend_name' =>
                            $trendName,

                        'trend_score' =>
                            $score,

                        'source_title' =>
                            $title,

                        'source_url' =>
                            $url,

                        'source_type' =>
                            'article',

                        'match_type' =>
                            'explicit',

                        'confidence_score' =>
                            0.85,

                        'discovered_by' =>
                            'tavily-search',

                        'discovered_at' =>
                            now(),
                    ]);
                }

                $this->info(
                    "✅ {$certification['name']} procesada"
                );

                sleep($sleep);

            } catch (\Throwable $e) {

                $this->error(
                    "❌ Error en {$certification['name']}"
                );

                $this->line(
                    "👉 {$e->getMessage()}"
                );

                Log::error(
                    '[CERTIFICATION-TRENDS-TAVILY]',
                    [

                        'certification' =>
                            $certification,

                        'error' =>
                            $e->getMessage(),
                    ]
                );
            }
        }

        $this->info(
            '🏁 Proceso finalizado'
        );

        return Command::SUCCESS;
    }

    /* =========================================================
       CERTIFICATIONS
    ========================================================= */
    protected function getCertifications(
        int $limit
    ): array {

        return DB::table('market_entities as me')

            ->leftJoin(
                'entity_trends as et',
                function ($j) {

                    $j->on(
                        'et.market_entity_id',
                        '=',
                        'me.id'
                    );
                }
            )

            ->where(
                'me.entity_type',
                'certification'
            )

            ->groupBy(
                'me.id',
                'me.name'
            )

            ->orderByRaw(
                'MAX(et.created_at) IS NOT NULL'
            )

            ->orderByRaw(
                'MAX(et.created_at) ASC'
            )

            ->limit($limit)

            ->select(
                'me.id',
                'me.name',

                DB::raw(
                    'MAX(et.created_at) as last_trend_at'
                )
            )

            ->get()

            ->map(fn ($c) => [

                'id'   => $c->id,

                'name' => $c->name,

            ])

            ->toArray();
    }

    /* =========================================================
       QUERY
    ========================================================= */
    protected function buildQuery(
        string $certification
    ): string {

        $year = now()->year;

        return <<<QUERY
{$certification} certification trends {$year}.

Focus on:
- Job demand
- Enterprise adoption
- Cloud certifications
- AI certifications
- Cybersecurity certifications
- Hiring relevance

Sources:
Microsoft, AWS, Google Cloud, Cisco, CompTIA, LinkedIn, Gartner.
QUERY;
    }

    /* =========================================================
       TAVILY SEARCH
    ========================================================= */
    protected function tavilySearch(
        string $query
    ): array {

        $apiKey = env('TAVILY_KEY');

        if (!$apiKey) {

            throw new \Exception(
                'TAVILY_KEY no configurada'
            );
        }

        $response = Http::timeout(60)

            ->withHeaders([
                'Authorization' =>
                    'Bearer ' . $apiKey,

                'Content-Type' =>
                    'application/json',
            ])

            ->post(
                'https://api.tavily.com/search',
                [

                    'query' =>
                        $query,

                    'search_depth' =>
                        'advanced',

                    'topic' =>
                        'general',

                    'max_results' =>
                        10,

                    'include_answer' =>
                        true,

                    'include_raw_content' =>
                        false,

                    'include_images' =>
                        false,
                ]
            );

        if (!$response->successful()) {

            throw new \Exception(
                'Error Tavily: ' .
                $response->body()
            );
        }

        return $response->json();
    }

    /* =========================================================
       TREND NAME
    ========================================================= */
    protected function extractTrendName(
        string $certification,
        string $title,
        string $content
    ): string {

        $title = trim($title);

        if ($title) {
            return $title;
        }

        return "Trend related to {$certification}";
    }
}