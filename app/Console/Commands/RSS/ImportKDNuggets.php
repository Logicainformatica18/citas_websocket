<?php

namespace App\Console\Commands\RSS;

class ImportKDNuggets extends BaseRssCommand
{
    protected $signature = 'rss:import-kdnuggets';
    protected $description = 'Importa noticias desde KDNuggets (IA / Data Science)';

    public function handle()
    {
        $saved = $this->processFeed(
            "https://www.kdnuggets.com/feed",
            "KDNuggets",
            "AI",
            "Data Science"
        );

        $this->info("Guardados: $saved insights desde KDNuggets");
    }
}
