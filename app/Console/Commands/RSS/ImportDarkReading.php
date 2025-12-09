<?php

namespace App\Console\Commands\RSS;

class ImportDarkReading extends BaseRssCommand
{
    protected $signature = 'rss:import-darkreading';
    protected $description = 'Importa noticias de ciberseguridad desde DarkReading';

    public function handle()
    {
        $saved = $this->processFeed(
            "https://www.darkreading.com/rss.xml",
            "DarkReading",
            "Cybersecurity",
            "Security News"
        );

        $this->info("Guardados: $saved insights desde DarkReading");
    }
}
