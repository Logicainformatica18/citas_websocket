<?php

namespace App\Console\Commands\RSS;

class ImportZDNet extends BaseRssCommand
{
    protected $signature = 'rss:import-zdnet';
    protected $description = 'Importa noticias desde ZDNet';

    public function handle()
    {
        $saved = $this->processFeed(
            "https://www.zdnet.com/news/rss.xml",
            "ZDNet",
            "Technology",
            "IT News"
        );

        $this->info("Guardados: $saved insights desde ZDNet");
    }
}
