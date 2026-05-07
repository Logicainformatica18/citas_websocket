<?php

namespace App\Console\Commands\Trends\Tavily;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\EntityTrend;

class DiscoverTechnologyTrendsTavilyCommand extends Command
{
    protected $signature = 'trends:discover-technologies-tavily
                            {--limit=20 : Cantidad máxima de tecnologías}
                            {--sleep=10 : Segundos entre requests}';

    protected $description =
        'Descubre tendencias de tecnologías usando Tavily AI';

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
            "🔍 Descubriendo tendencias TECNOLOGÍAS con Tavily"
        );

        $technologies = $this->getTechnologies($limit);

        if (empty($technologies)) {

            $this->warn(
                '🟢 No hay tecnologías pendientes'
            );

            return Command::SUCCESS;
        }

        foreach ($technologies as $technology) {

            try {

                $query = $this->buildQuery(
                    $technology['name']
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
                        $technology['id']
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
                            $technology['name'],
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
                            $technology['id'],

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
                    "✅ {$technology['name']} procesado"
                );

                sleep($sleep);

            } catch (\Throwable $e) {

                $this->error(
                    "❌ Error en {$technology['name']}"
                );

                $this->line(
                    "👉 {$e->getMessage()}"
                );

                Log::error(
                    '[TECHNOLOGY-TRENDS-TAVILY]',
                    [

                        'technology' =>
                            $technology,

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
       TECNOLOGÍAS
    ========================================================= */
    protected function getTechnologies(
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
                'technology'
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

            ->map(fn ($t) => [

                'id'   => $t->id,

                'name' => $t->name,

            ])

            ->toArray();
    }

    /* =========================================================
       QUERY
    ========================================================= */
    protected function buildQuery(
        string $technology
    ): string {

        $year = now()->year;

        return <<<QUERY
Enterprise technology trends for {$technology} in {$year}.

Focus on:
- enterprise adoption
- job demand
- AI integration
- cloud infrastructure
- industry usage
- hiring growth
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

                    /*
                    =========================================
                    DOMINIOS PRIORITARIOS
                    =========================================
                    */

                    'include_domains' => [

                        /*
                        =====================================
                        NIVEL 1
                        =====================================
                        */

                        'weforum.org',
                        'oecd.org',
                        'unesco.org',
                        'gartner.com',
                        'mckinsey.com',
                        'pwc.com',
                        'deloitte.com',
                        'holoniq.com',
                        'linkedin.com',
                        'coursera.org',
                        'lightcast.io',
                        'burning-glass.com',

                        /*
                        =====================================
                        NIVEL 2
                        =====================================
                        */

                        'techcrunch.com',
                        'theverge.com',
                        'zdnet.com',
                        'venturebeat.com',
                        'computerworld.com',
                        'informationweek.com',
                        'techrepublic.com',
                        'networkworld.com',
                        'theregister.com',
                        'techtarget.com',

                        /*
                        =====================================
                        NIVEL 3
                        =====================================
                        */

                        'ieee.org',
                        'kdnuggets.com',
                        'towardsdatascience.com',

                        /*
                        =====================================
                        NIVEL 4
                        =====================================
                        */

                        'aws.amazon.com',
                        'microsoft.com',
                        'oracle.com',
                        'salesforce.com',
                        'cisco.com',
                        'ces.tech',

                        /*
                        =====================================
                        NIVEL 5
                        =====================================
                        */

                        'educause.edu',
                        'edtechmagazine.com',
                    ],
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
        string $technology,
        string $title,
        string $content
    ): string {

        $title = trim($title);

        if ($title) {
            return $title;
        }

        return "Trend related to {$technology}";
    }
}