<?php

namespace App\Console\Commands\RSS;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\GlobalInsight;

abstract class BaseRssCommand extends Command
{
    protected function processFeed($rssUrl, $sourceName, $category = null, $subcategory = null)
    {
        $response = Http::withOptions(['verify' => false, 'timeout' => 20])->get($rssUrl);

        if (!$response->successful()) {
            $this->error("❌ Error RSS: " . $response->status());
            return 0;
        }

        $xml = @simplexml_load_string($response->body());
        if (!$xml) {
            $this->error("❌ RSS inválido.");
            return 0;
        }

        $items = $xml->channel->item ?? [];
        $saved = 0;

        foreach ($items as $item) {

            $title = trim((string) $item->title);
            $link = trim((string) $item->link);
            $date = (string) $item->pubDate;
            $desc = strip_tags((string) $item->description);

            if (!$title || !$link) continue;

            $hash = hash('sha256', $title.$link);

            if (GlobalInsight::where('hash', $hash)->exists()) continue;

            GlobalInsight::create([
                'source' => $sourceName,
                'source_url' => $link,
                'source_type' => 'rss',

                'category' => $category,
                'subcategory' => $subcategory,

                'title' => $title,
                'summary' => $desc,

                'published_at' => $date ? date('Y-m-d H:i:s', strtotime($date)) : null,
                'region' => null,
                'country' => null,

                'impact_score' => null,
                'keywords' => null,
                'entities' => null,

                'hash' => $hash
            ]);

            $saved++;
        }

        return $saved;
    }
}
