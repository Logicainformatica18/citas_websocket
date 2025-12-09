<?php

namespace App\Console\Commands\RSS;

class ImportTheRegister extends BaseRssCommand
{
    protected $signature = 'rss:import-theregister';
    protected $description = 'Importa noticias desde The Register';

    public function handle()
    {
        $saved = $this->processFeed(
            "https://www.theregister.com/headlines.atom",
            "The Register",
            "Technology",
            "IT News"
        );

        $this->info("Guardados: $saved insights desde The Register");
    }
}
