<?php

namespace App\Console\Commands\RSS;

class ImportVentureBeat extends BaseRssCommand
{
    protected $signature = 'rss:import-venturebeat';
    protected $description = 'Importa insights desde VentureBeat';

    public function handle()
    {
        $saved = $this->processFeed(
            "https://venturebeat.com/feed/",
            "VentureBeat",
            "AI",
            "Data & Analytics"
        );

        $this->info("Guardados: $saved insights desde VentureBeat");
    }
}
