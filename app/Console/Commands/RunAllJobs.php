<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RunAllJobs extends Command
{
    protected $signature = 'jobs:run-all';
    protected $description = 'Ejecuta todos los comandos de scraping y carga de datos (Arbeitnow + GetOnBoard)';

    public function handle()
    {
        $commands = [
        //     'arbeitnow:languages',
        //     'arbeitnow:methodologies',
        //  'arbeitnow:technologies',

        //     'getonboard:languages',
        //     'getonboard:methodologies',
        //     'getonboard:technologies',


 'computrabajo:technologies --country=pe --pages=1',
    'computrabajo:technologies --country=bo --pages=1',
    'computrabajo:technologies --country=ar --pages=1',
    'computrabajo:technologies --country=uy --pages=1',
    'computrabajo:technologies --country=mx --pages=1',
    'computrabajo:technologies --country=co --pages=1',
    'computrabajo:technologies --country=ec --pages=1',
    'computrabajo:technologies --country=ve --pages=1',
    'computrabajo:technologies --country=cl --pages=1',

    // 'computrabajo:languages --pages=1',
    // 'computrabajo:methodologies --pages=1',

        ];

        foreach ($commands as $cmd) {
            $this->info("▶ Ejecutando: {$cmd}");
            $exitCode = Artisan::call($cmd);

            if ($exitCode !== 0) {
                $this->error("❌ Falló el comando: {$cmd}");
                $this->line(Artisan::output());
                return $exitCode;
            }

            $this->line(Artisan::output());
            $this->info("✅ Comando {$cmd} finalizado correctamente.\n");
        }

        $this->info('🎯 Todos los procesos se ejecutaron correctamente.');
        return 0;
    }
}
