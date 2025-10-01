<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\JobOfferController;
use Illuminate\Http\Request;

class ImportJobOffers extends Command
{
    protected $signature = 'joboffers:import';
    protected $description = 'Importar ofertas de trabajo desde la API';

    public function handle()
    {
        // URL fija de la API, o podrías pasarla por .env
        $apiUrl = config('services.getonboard.api_url', 'https://www.getonbrd.com/api/v0/jobs');

        // Crear Request falso para simular el controller
        $request = new Request(['api_url' => $apiUrl]);

        $controller = new JobOfferController();
        $response = $controller->import($request);

        $this->info('Import ejecutado: ' . json_encode($response->getData()));
    }
}
