<?php

namespace App\Console\Commands\Trends\Tavily;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\EntityTrend;

class DiscoverLanguageTrendsTavilyCommand extends Command
{
    protected $signature = 'trends:discover-languages-tavily
                            {--limit=20 : Cantidad máxima de lenguajes}
                            {--sleep=10 : Segundos entre requests}';

    protected $description =
        'Descubre tendencias de lenguajes usando Tavily AI';

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
            "🔍 Descubriendo tendencias LENGUAJES con Tavily"
        );

        $languages = $this->getLanguages($limit);

        if (empty($languages)) {

            $this->warn(
                '🟢 No hay lenguajes pendientes'
            );

            return Command::SUCCESS;
        }

        foreach ($languages as $language) {

            try {

                $query = $this->buildQuery(
                    $language['name']
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
                        $language['id']
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
                            $language['name'],
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
                            $language['id'],

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
                    "✅ {$language['name']} procesado"
                );

                sleep($sleep);

            } catch (\Throwable $e) {

                $this->error(
                    "❌ Error en {$language['name']}"
                );

                $this->line(
                    "👉 {$e->getMessage()}"
                );

                Log::error(
                    '[LANGUAGE-TRENDS-TAVILY]',
                    [

                        'language' =>
                            $language,

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
       LANGUAGES
    ========================================================= */
    protected function getLanguages(
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
                'language'
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

            ->map(fn ($l) => [

                'id'   => $l->id,

                'name' => $l->name,

            ])

            ->toArray();
    }

    /* =========================================================
       QUERY
    ========================================================= */
    protected function buildQuery(
        string $language
    ): string {

        $year = now()->year;

        return <<<QUERY
Enterprise programming language trends for {$language} in {$year}.

Focus on:
- enterprise adoption
- backend development
- frontend ecosystem
- cloud-native development
- AI integration
- developer demand
- hiring growth
- GitHub popularity
- StackOverflow trends
- modern software architecture

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

                        'github.com',
                        'stackoverflow.com',
                        'stackoverflow.blog',
                        'stackexchange.com',
                        'jetbrains.com',
                        'redmonk.com',
                        'tiobe.com',
                        'linkedin.com',
                        'gartner.com',
                        'mckinsey.com',
                        'deloitte.com',
                        'pwc.com',

                        /*
                        =====================================
                        NIVEL 2
                        =====================================
                        */

                        'infoq.com',
                        'thoughtworks.com',
                        'martinfowler.com',
                        'dev.to',
                        'hashnode.com',
                        'medium.com',
                        'towardsdatascience.com',
                        'kdnuggets.com',

                        /*
                        =====================================
                        NIVEL 3
                        =====================================
                        */

                        'aws.amazon.com',
                        'cloud.google.com',
                        'azure.microsoft.com',
                        'vercel.com',
                        'netlify.com',

                        /*
                        =====================================
                        NIVEL 4
                        =====================================
                        */

                        'techcrunch.com',
                        'theverge.com',
                        'venturebeat.com',
                        'zdnet.com',
                        'computerworld.com',
                        'techtarget.com',

                        /*
                        =====================================
                        NIVEL 5
                        =====================================
                        */

                        'openai.com',
                        'anthropic.com',
                        'huggingface.co',
                        'langchain.com',
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
        string $language,
        string $title,
        string $content
    ): string {

        $title = trim($title);

        if ($title) {
            return $title;
        }

        return "Trend related to {$language}";
    }
}