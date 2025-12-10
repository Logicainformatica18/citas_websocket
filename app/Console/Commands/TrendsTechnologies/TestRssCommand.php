<?php

namespace App\Console\Commands\TrendsTechnologies;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\GlobalTrend;

class TestRssCommand extends Command
{
    protected $signature = 'rss:test {url}';
    protected $description = 'Prueba e importa noticias desde un RSS directamente por URL.';

    public function handle()
    {
        $rssUrl = $this->argument('url');

        $this->info("📡 Leyendo RSS desde: {$rssUrl}");

        try {
            $response = Http::withOptions([
                'verify' => false,
                'timeout' => 30,
            ])->get($rssUrl);

            if (!$response->successful()) {
                $this->error("❌ Error al obtener RSS: " . $response->status());
                return Command::FAILURE;
            }

            $xml = @simplexml_load_string($response->body());

            if (!$xml) {
                $this->error("❌ No se pudo interpretar el RSS (XML inválido).");
                return Command::FAILURE;
            }

            $items = $xml->channel->item ?? [];

            $this->info("📰 Noticias encontradas: " . count($items));

            $saved = 0;

            foreach ($items as $item) {

                $title = (string) $item->title ?? null;
                $link = (string) $item->link ?? null;
                $pubDate = (string) $item->pubDate ?? null;
                $description = strip_tags((string) $item->description ?? "");

                if (!$title || !$link) {
                    continue;
                }

                // Hash para evitar duplicados
                $hash = hash('sha256', $title . $link);

                if (GlobalTrend::where('hash', $hash)->exists()) {
                    continue;
                }

                GlobalTrend::create([
                    'source'        => parse_url($rssUrl, PHP_URL_HOST),
                    'source_url'    => $link,
                    'source_type'   => 'rss',

                    'category'      => 'news',
                    'subcategory'   => 'general',

                    'item_name'     => $title,
                    'item_type'     => 'article',

                    'summary'       => $description,
                    'year'          => date('Y', strtotime($pubDate ?: 'now')),
                    'country'       => null,
                    'region'        => 'Global',

                    'metadata'      => [
                        'title' => $title,
                        'link' => $link,
                        'pubDate' => $pubDate,
                        'rss_source' => $rssUrl,
                    ],

                    'hash'          => $hash,
                ]);

                $saved++;
            }

            $this->info("✅ Noticias guardadas: {$saved}");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
