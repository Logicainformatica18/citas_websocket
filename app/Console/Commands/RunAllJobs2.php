<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class RunAllJobs2 extends Command
{
    protected $signature = 'jobs:run-all2 {--sleep=1 : Segundos de pausa entre comandos}';
    protected $description = '🚀 Ejecuta todos los comandos de scraping y carga de datos, mostrando progreso en tiempo real.';

    public function handle()
    {
      $commands = [

// 'certifications:discover-gaps',
// 'languages:discover-gaps',
// 'technologies:discover-gaps',
'trends:discover-languages --limit=50 --sleep=5',
'trends:discover-methodologies --limit=50 --sleep=5',
'trends:discover-technologies --limit=1500 --sleep=5',



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
