<?php

namespace App\Console\Commands\RSS;

class ImportMcKinsey extends BaseRssCommand
{
    protected $signature = 'rss:import-mckinsey';
    protected $description = 'Importa insights desde McKinsey';

    public function handle()
    {
        $saved = $this->processFeed(
            "https://www.mckinsey.com/insights/rss",
            "McKinsey",
            "Technology",
            "Trends"
        );

        $this->info("Guardados: $saved insights desde McKinsey");
    }
}
