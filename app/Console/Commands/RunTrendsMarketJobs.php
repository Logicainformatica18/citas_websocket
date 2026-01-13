<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

class RunTrendsMarketJobs extends Command
{
    protected $signature = 'jobs:run-trends-market 
        {--sleep=1 : Segundos de pausa entre comandos}
        {--pages=1 : Páginas por país}
        {--year= : Año de tendencias}
        {--quarter= : Trimestre de tendencias}';

    protected $description = '📡 Ejecuta el rastreo de mercado laboral (Adzuna) usando tendencias como semilla';

    public function handle()
    {

    //php artisan jobs:run-trends-market

        $sleep   = (int) $this->option('sleep');
        $pages   = (int) $this->option('pages');
        $year    = $this->option('year');
        $quarter = $this->option('quarter');

        $baseCmd = "adzuna:trends --pages={$pages}";
        if ($year) {
            $baseCmd .= " --year={$year}";
        }
        if ($quarter) {
            $baseCmd .= " --quarter={$quarter}";
        }

        $commands = [
            "{$baseCmd} --country=us",
            "{$baseCmd} --country=ca",
            "{$baseCmd} --country=mx",
            "{$baseCmd} --country=br",

            "{$baseCmd} --country=es",
            "{$baseCmd} --country=fr",
            "{$baseCmd} --country=de",
            "{$baseCmd} --country=it",
            "{$baseCmd} --country=gb",
            "{$baseCmd} --country=nl",
            "{$baseCmd} --country=ch",
            "{$baseCmd} --country=pl",

            "{$baseCmd} --country=in",
            "{$baseCmd} --country=sg",

            "{$baseCmd} --country=za",

            "{$baseCmd} --country=au",
            "{$baseCmd} --country=nz",
        ];

        $total = count($commands);

        $this->info("📡 Ejecutando {$total} scans de mercado por tendencias\n");

        foreach ($commands as $index => $cmd) {
            $pos = $index + 1;

            $this->line(str_repeat('═', 70));
            $this->comment(
                "🕒 [" . Carbon::now()->format('H:i:s') . "] Ejecutando {$pos}/{$total}: {$cmd}"
            );
            $this->newLine();

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
                $this->line("⏸ Pausando {$sleep}s antes del siguiente...");
                sleep($sleep);
            }

            $this->newLine();
        }

        $this->line(str_repeat('═', 70));
        $this->info("🏁 [" . Carbon::now()->format('H:i:s') . "] Scan de mercado por tendencias completado.");

        return 0;
    }
}
