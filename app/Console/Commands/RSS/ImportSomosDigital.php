<?php

namespace App\Console\Commands\RSS;

class ImportSomosDigital extends BaseRssCommand
{
    protected $signature = 'rss:import-somos-digital';
    protected $description = 'Importa insights desde Somos Digital';

    public function handle()
    {
        $saved = $this->processFeed(
            "https://somos-digital.org/feed/",
            "Somos Digital",
            "Digital Inclusion",
            "European Policy"
        );

        $this->info("Guardados: $saved insights desde Somos Digital");
    }
}
