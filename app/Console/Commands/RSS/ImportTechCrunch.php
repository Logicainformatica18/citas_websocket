<?php

namespace App\Console\Commands\RSS;

class ImportTechCrunch extends BaseRssCommand
{
    protected $signature = 'rss:import-techcrunch';
    protected $description = 'Importa insights desde TechCrunch';

    public function handle()
    {
        $saved = $this->processFeed(
            "https://techcrunch.com/feed/",
            "TechCrunch",
            "Technology",
            "Startups"
        );

        $this->info("Guardados: $saved insights desde TechCrunch");
    }
}
