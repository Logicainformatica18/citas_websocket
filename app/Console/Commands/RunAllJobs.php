<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class RunAllJobs extends Command
{
    protected $signature = 'jobs:run-all {--sleep=1 : Segundos de pausa entre comandos}';
    protected $description = '🚀 Ejecuta todos los comandos de scraping y carga de datos, mostrando progreso en tiempo real.';

    public function handle()
    {
      $commands = [
//     'arbeitnow:languages',
//     'arbeitnow:methodologies',
//  'arbeitnow:technologies',

//     'getonboard:languages',
//     'getonboard:methodologies',
//     'getonboard:technologies',


//  'computrabajo:technologies --country=pe --pages=1',
//     'computrabajo:technologies --country=bo --pages=1',
// 'computrabajo:technologies --country=ar --pages=1',
// 'computrabajo:technologies --country=uy --pages=1',
// 'computrabajo:technologies --country=mx --pages=1',
// 'computrabajo:technologies --country=co --pages=1',
// 'computrabajo:technologies --country=ec --pages=1',
// 'computrabajo:technologies --country=ve --pages=1',
// 'computrabajo:technologies --country=cl --pages=1',

// 'computrabajo:languages --pages=1',
// 'computrabajo:methodologies --pages=1',


//  'adzuna:technologies --country=us --pages=1',
    // 'adzuna:technologies --country=ca --pages=1',
    // 'adzuna:technologies --country=mx --pages=1',
    // 'adzuna:technologies --country=br --pages=1',

//     'adzuna:technologies --country=es --pages=1',
//     'adzuna:technologies --country=fr --pages=1',
//     'adzuna:technologies --country=de --pages=1',
//     'adzuna:technologies --country=it --pages=1',
//     'adzuna:technologies --country=gb --pages=1',
//     'adzuna:technologies --country=nl --pages=1',
//     'adzuna:technologies --country=ch --pages=1',
//     'adzuna:technologies --country=pl --pages=1',

// 'adzuna:technologies --country=in --pages=1',
// 'adzuna:technologies --country=sg --pages=1',

// 'adzuna:technologies --country=za --pages=1',

// 'adzuna:technologies --country=au --pages=1',
// 'adzuna:technologies --country=nz --pages=1',


//  'adzuna:languages --country=us --pages=1',
// 'adzuna:languages --country=ca --pages=1',
// 'adzuna:languages --country=mx --pages=1',
// 'adzuna:languages --country=br --pages=1',

// 'adzuna:languages --country=es --pages=1',
// 'adzuna:languages --country=fr --pages=1',
// 'adzuna:languages --country=de --pages=1',
'adzuna:languages --country=it --pages=1',
'adzuna:languages --country=gb --pages=1',
'adzuna:languages --country=nl --pages=1',
'adzuna:languages --country=ch --pages=1',
'adzuna:languages --country=pl --pages=1',

'adzuna:languages --country=in --pages=1',
'adzuna:languages --country=sg --pages=1',

'adzuna:languages --country=za --pages=1',

'adzuna:languages --country=au --pages=1',
'adzuna:languages --country=nz --pages=1',
];

        $total = count($commands);
        $this->info("🌍 Iniciando ejecución de {$total} comandos...\n");

        foreach ($commands as $index => $cmd) {
            $pos = $index + 1;
            $this->line(str_repeat('═', 60));
            $this->newLine();
            $this->comment("🕒 [" . Carbon::now()->format('H:i:s') . "] Ejecutando {$pos}/{$total}: {$cmd}");
            $this->newLine();

            // Mostrar salida en tiempo real
            $process = popen("php artisan {$cmd} 2>&1", 'r');
            while (!feof($process)) {
                $line = fgets($process);
                if ($line) {
                    $this->line("  " . trim($line));
                }
            }
            $exitCode = pclose($process);

            $this->newLine();
            if ($exitCode === 0) {
                $this->info("✅ {$cmd} finalizado correctamente.");
            } else {
                $this->error("❌ Error ejecutando {$cmd} (código {$exitCode}).");
            }

            if ($index < $total - 1) {
                $sleep = (int) $this->option('sleep');
                $this->line("⏸ Pausando {$sleep}s antes del siguiente...");
                sleep($sleep);
            }

            $this->newLine();
        }

        $this->line(str_repeat('═', 60));
        $this->info("🏁 [" . Carbon::now()->format('H:i:s') . "] Todos los procesos completados.");
        return 0;
    }
}
