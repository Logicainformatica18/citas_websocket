<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\WorldbankIndicator;

class WorldBankImportCommand extends Command
{
/*
php artisan worldbank:import --countries=all --indicators=SL.UEM.TOTL.ZS,SL.IND.EMPL.ZS,SL.SRV.EMPL.ZS,IT.NET.USER.ZS,TX.VAL.ICTG.ZS.UN,GB.XPD.RSDV.GD.ZS --from=2020 --to=2025

*/

    /**
     * Firma del comando
     */
    protected $signature = 'worldbank:import
        {--indicators= : Códigos de indicadores separados por coma (ej: SL.UEM.TOTL.ZS,SL.SRV.EMPL.ZS,IT.NET.USER.ZS)}
        {--countries=all : Códigos de países separados por coma o "all"}
        {--from=2015 : Año inicial}
        {--to=2024 : Año final}';

    /**
     * Descripción del comando
     */
    protected $description = 'Importa indicadores laborales o tecnológicos desde la API del World Bank (v2)';

    /**
     * URL base de la API
     */
    protected $baseUrl = 'https://api.worldbank.org/v2/';

    public function handle()
    {
        $indicators = explode(',', $this->option('indicators'));
        $countries = $this->option('countries');
        $from = $this->option('from');
        $to = $this->option('to');

        if (empty($indicators)) {
            $this->error('⚠️ Debes especificar al menos un indicador con --indicators=');
            return;
        }

        foreach ($indicators as $indicator) {
            $indicator = trim($indicator);
            $this->info("🌍 Importando indicador: {$indicator}");

            $page = 1;
            $totalPages = 1;

            do {
                $url = "{$this->baseUrl}country/{$countries}/indicator/{$indicator}?format=json&per_page=1000&page={$page}&date={$from}:{$to}";
                $response = Http::get($url);

                if ($response->failed()) {
                    $this->error("❌ Error al consultar la API: {$indicator} (HTTP {$response->status()})");
                    break;
                }

                $json = $response->json();

                if (!is_array($json) || count($json) < 2) {
                    $this->warn("⚠️ Sin datos para {$indicator}");
                    break;
                }

                $meta = $json[0];
                $data = $json[1];
                $totalPages = (int) ($meta['pages'] ?? 1);

                foreach ($data as $item) {
                    if (empty($item['value']) || empty($item['date'])) {
                        continue;
                    }

                    WorldbankIndicator::updateOrCreate(
                        [
                            'country_code' => $item['country']['id'] ?? 'N/A',
                            'indicator_code' => $indicator,
                            'year' => $item['date'],
                        ],
                        [
                            'country_name' => $item['country']['value'] ?? 'Unknown',
                            'indicator_name' => $item['indicator']['value'] ?? '',
                            'value' => $item['value'],
                            'source' => 'World Bank API',
                        ]
                    );
                }

                $this->info("✅ Página {$page}/{$totalPages} completada para {$indicator}");
                $page++;
                sleep(1); // para evitar rate limit

            } while ($page <= $totalPages);

            $this->info("🎯 Indicador {$indicator} importado correctamente.\n");
        }

        $this->info("🎉 Importación de indicadores completada.");
    }
}
