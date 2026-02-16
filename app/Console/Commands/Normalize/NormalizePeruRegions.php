<?php

namespace App\Console\Commands\Normalize;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizePeruRegions extends Command
{
    protected $signature = 'normalize:peru-regions {--dry-run}';
    protected $description = 'Normaliza city para Perú usando latitude/longitude (todas las regiones)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        /*
        |--------------------------------------------------------------------------
        | Definición de regiones (Bounding Boxes)
        |--------------------------------------------------------------------------
        */

        $regions = [

            'Callao' => [
                'lat_min' => -12.25,
                'lat_max' => -11.85,
                'lng_min' => -77.30,
                'lng_max' => -76.90,
            ],

            'Lima' => [
                'lat_min' => -13.95,
                'lat_max' => -11.50,
                'lng_min' => -77.90,
                'lng_max' => -75.40,
            ],

            'Áncash' => [
                'lat_min' => -10.90,
                'lat_max' => -8.00,
                'lng_min' => -78.90,
                'lng_max' => -76.70,
            ],

            'Arequipa' => [
                'lat_min' => -17.90,
                'lat_max' => -15.50,
                'lng_min' => -73.90,
                'lng_max' => -70.20,
            ],

            'Cusco' => [
                'lat_min' => -15.90,
                'lat_max' => -12.80,
                'lng_min' => -72.90,
                'lng_max' => -70.00,
            ],

            'Piura' => [
                'lat_min' => -6.90,
                'lat_max' => -4.00,
                'lng_min' => -81.60,
                'lng_max' => -79.00,
            ],

            'Loreto' => [
                'lat_min' => -6.50,
                'lat_max' => 0.50,
                'lng_min' => -77.80,
                'lng_max' => -69.50,
            ],

            'Junín' => [
                'lat_min' => -12.90,
                'lat_max' => -10.50,
                'lng_min' => -76.60,
                'lng_max' => -73.50,
            ],

            'Ica' => [
                'lat_min' => -15.60,
                'lat_max' => -13.00,
                'lng_min' => -76.60,
                'lng_max' => -74.00,
            ],

            'La Libertad' => [
                'lat_min' => -9.60,
                'lat_max' => -6.80,
                'lng_min' => -80.10,
                'lng_max' => -77.70,
            ],

            'Lambayeque' => [
                'lat_min' => -7.40,
                'lat_max' => -5.50,
                'lng_min' => -80.10,
                'lng_max' => -78.80,
            ],

            'Puno' => [
                'lat_min' => -17.60,
                'lat_max' => -14.50,
                'lng_min' => -71.60,
                'lng_max' => -68.80,
            ],

            'Tacna' => [
                'lat_min' => -18.50,
                'lat_max' => -16.90,
                'lng_min' => -71.20,
                'lng_max' => -69.50,
            ],

            'Tumbes' => [
                'lat_min' => -4.20,
                'lat_max' => -3.40,
                'lng_min' => -80.70,
                'lng_max' => -80.00,
            ],

            'Ucayali' => [
                'lat_min' => -10.20,
                'lat_max' => -7.00,
                'lng_min' => -75.80,
                'lng_max' => -72.00,
            ],

            'Amazonas' => [
                'lat_min' => -6.50,
                'lat_max' => -3.00,
                'lng_min' => -78.50,
                'lng_max' => -76.00,
            ],
        ];

        $totalAffected = 0;

        foreach ($regions as $region => $bounds) {

            $query = DB::table('job_offers')
                ->whereRaw("LOWER(TRIM(country)) IN ('peru','perú','pe')")
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereBetween('latitude', [$bounds['lat_min'], $bounds['lat_max']])
                ->whereBetween('longitude', [$bounds['lng_min'], $bounds['lng_max']])
                ->where('city', '!=', $region);

            $count = $query->count();

            if ($dryRun) {
                $this->line("🟡 {$region}: {$count} registros se actualizarían.");
            } else {
                $affected = $query->update(['city' => $region]);
                $totalAffected += $affected;
                $this->info("✅ {$region}: {$affected} actualizados.");
            }
        }

        if ($dryRun) {
            $this->warn("🔎 DRY RUN completado.");
        } else {
            $this->info("🎉 Total registros actualizados: {$totalAffected}");
        }

        return Command::SUCCESS;
    }
}
