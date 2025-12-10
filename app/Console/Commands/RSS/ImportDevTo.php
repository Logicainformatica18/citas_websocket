<?php

namespace App\Console\Commands\RSS;

class ImportDevTo extends BaseRssCommand
{
    protected $signature = 'rss:import-devto';
    protected $description = 'Importa artículos desde Dev.to (RRSS)';

    public function handle()
    {
        $saved = $this->processFeed(
            "https://dev.to/feed",
            "Dev.to",
            "Software Development",
            "Engineering"
        );

        $this->info("Guardados: $saved insights desde Dev.to");
    }
}
