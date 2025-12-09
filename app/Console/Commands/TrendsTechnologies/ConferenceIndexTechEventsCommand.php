<?php

namespace App\Console\Commands\TrendsTechnologies;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\GlobalTrend;
use Carbon\Carbon;
use Symfony\Component\DomCrawler\Crawler;

class ConferenceIndexTechEventsCommand extends Command
{
    protected $signature = 'conferenceindex:tech-events {--limit=50}';
    protected $description = 'Scrapea ConferenceIndex para eventos de IA/tecnología y los guarda en global_trends.';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $year = Carbon::now()->year;
        $quarter = ceil(Carbon::now()->month / 3);

        $url = 'https://conferenceindex.org/conferences/artificial-intelligence';

        try {
            $this->info("🔵 Consultando ConferenceIndex: {$url}");

            $response = Http::timeout(20)->get($url);

            if (!$response->ok()) {
                $this->error("❌ Error consultando ConferenceIndex");
                return Command::FAILURE;
            }

            $html = $response->body();
            $crawler = new Crawler($html);

            // Selección de bloques de eventos (ajusta el selector si cambia el HTML)
            $nodes = $crawler->filter('.list-group-item');

            if ($nodes->count() === 0) {
                $this->error("❌ No se encontraron eventos en ConferenceIndex.");
                return Command::FAILURE;
            }

            $count = 0;

            foreach ($nodes as $node) {

                if ($count >= $limit) break;

                $crawlerNode = new Crawler($node);

                // Extraer título
                $title = trim(
                    $crawlerNode->filter('h3')->first()->text() ?? ''
                );

                // Extraer URL del evento
                $link = $crawlerNode->filter('a')->attr('href') ?? null;
                if ($link && strpos($link, 'http') !== 0) {
                    $link = 'https://conferenceindex.org' . $link;
                }

                // Extraer fecha/ubicación — según html (puede necesitar ajuste)
                $meta = trim(
                    $crawlerNode->filter('.media-body .mb-2')->text() ?? ''
                );

                // Hash único
                $hash = hash('sha256', 'conferenceindex_'.$title.$year);

                GlobalTrend::updateOrCreate(
                    ['hash' => $hash],
                    [
                        'source'      => 'conferenceindex',
                        'source_url'  => $link,
                        'source_type' => 'html',

                        'category'   => 'tech-event',
                        'subcategory'=> 'conferenceindex',

                        'item_name'   => $title,
                        'item_type'   => 'trend',

                        'year'        => $year,
                        'quarter'     => $quarter,

                        'value'       => null,
                        'rank'        => null,

                        'metadata'    => json_encode([
                            'link' => $link,
                            'meta' => $meta,
                        ]),
                    ]
                );

                $this->info("✔ Guardado evento: {$title}");
                $count++;
            }

            $this->info("🎉 ConferenceIndex tech-events procesado correctamente. Total: {$count}");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            Log::error("❌ Error ConferenceIndexTechEventsCommand", [
                'error' => $e->getMessage()
            ]);
            $this->error("❌ Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
