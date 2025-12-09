<?php

namespace App\Console\Commands\RSS;

class ImportTheVerge extends BaseRssCommand
{
    protected $signature = 'rss:import-theverge';
    protected $description = 'Importa noticias desde The Verge (RSS)';

    public function handle()
    {
        $saved = $this->processFeed(
            "https://www.theverge.com/rss/index.xml",
            "The Verge",
            "Technology",
            "Innovation"
        );

        $this->info("Guardados: $saved insights desde The Verge");
    }
}
