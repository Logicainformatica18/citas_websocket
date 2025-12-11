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
//     'adzuna:technologies --country=ca --pages=1',
//     'adzuna:technologies --country=mx --pages=1',
//     'adzuna:technologies --country=br --pages=1',

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
// 'adzuna:languages --country=it --pages=1',
// 'adzuna:languages --country=gb --pages=1',
// 'adzuna:languages --country=nl --pages=1',
// 'adzuna:languages --country=ch --pages=1',
// 'adzuna:languages --country=pl --pages=1',

// 'adzuna:languages --country=in --pages=1',
// 'adzuna:languages --country=sg --pages=1',

// 'adzuna:languages --country=za --pages=1',

// 'adzuna:languages --country=au --pages=1',
// 'adzuna:languages --country=nz --pages=1',


// 'jooble:languages --country="United States" --pages=1',
// 'jooble:languages --country="India" --pages=1',
// 'jooble:languages --country="United Kingdom" --pages=1',
// 'jooble:languages --country="Germany" --pages=1',
// 'jooble:languages --country="Spain" --pages=1',
// 'jooble:languages --country="Canada" --pages=1',
// 'jooble:languages --country="Italy" --pages=1',
// 'jooble:languages --country="Mexico" --pages=1',

// 'jooble:methodologies --country="United States" --pages=1',
// 'jooble:methodologies --country="India" --pages=1',
// 'jooble:methodologies --country="United Kingdom" --pages=1',
// 'jooble:methodologies --country="Germany" --pages=1',
// 'jooble:methodologies --country="Spain" --pages=1',
// 'jooble:methodologies --country="Canada" --pages=1',
// 'jooble:methodologies --country="Italy" --pages=1',
// 'jooble:methodologies --country="Mexico" --pages=1',

// 'jooble:technologies --country="United States" --pages=1',
// 'jooble:technologies --country="India" --pages=1',
// 'jooble:technologies --country="United Kingdom" --pages=1',
// 'jooble:technologies --country="Germany" --pages=1',
// 'jooble:technologies --country="Spain" --pages=1',
// 'jooble:technologies --country="Canada" --pages=1',
// 'jooble:technologies --country="Italy" --pages=1',
// 'jooble:technologies --country="Mexico" --pages=1',

// 'greenhouse:languages --company=cloudflare',
// 'greenhouse:languages --company=stripe',
// 'greenhouse:languages --company=discord',
// 'greenhouse:languages --company=gitlab',
// 'greenhouse:languages --company=dropbox',
// 'greenhouse:languages --company=airbnb',
// 'greenhouse:languages --company=datadog',
// 'greenhouse:languages --company=notion',
// 'greenhouse:languages --company=shopify',
// 'greenhouse:languages --company=brex',
// 'greenhouse:languages --company=zoom',
// 'greenhouse:languages --company=hubspot',
// 'greenhouse:languages --company=duolingo',
// 'greenhouse:languages --company=figma',
// 'greenhouse:languages --company=reddit',

// // 🌱 Startups tech
// 'greenhouse:languages --company=rippling',
// 'greenhouse:languages --company=deel',
// 'greenhouse:languages --company=ramp',
// 'greenhouse:languages --company=mercury',
// 'greenhouse:languages --company=wise',
// 'greenhouse:languages --company=bolt',
// 'greenhouse:languages --company=sentry',
// 'greenhouse:languages --company=zapier',
// 'greenhouse:languages --company=benchling',
// 'greenhouse:languages --company=anduril',

// // 🌱 Extra (si quieres más variedad)
// 'greenhouse:languages --company=asana',
// 'greenhouse:languages --company=grammarly',
// 'greenhouse:languages --company=openai',

// 'reed:languages',
// 'reed:methodologies',
// 'reed:technologies',
// 'usajobs:languages',
// 'usajobs:methodologies',
// 'usajobs:technologies',


//  'adzuna:competencies --country=us --pages=1',
//     'adzuna:competencies --country=ca --pages=1',
//     'adzuna:competencies --country=mx --pages=1',
//     'adzuna:competencies --country=br --pages=1',

//     'adzuna:competencies --country=es --pages=1',
//     'adzuna:competencies --country=fr --pages=1',
//     'adzuna:competencies --country=de --pages=1',
//     'adzuna:competencies --country=it --pages=1',
//     'adzuna:competencies --country=gb --pages=1',
//     'adzuna:competencies --country=nl --pages=1',
//     'adzuna:competencies --country=ch --pages=1',
//     'adzuna:competencies --country=pl --pages=1',

// 'adzuna:competencies --country=in --pages=1',
// 'adzuna:competencies --country=sg --pages=1',

// 'adzuna:competencies --country=za --pages=1',

// 'adzuna:competencies --country=au --pages=1',
// 'adzuna:competencies --country=nz --pages=1',

 //'computrabajo:competencies  --pages=5',
 'computrabajo:technologies --country=pe --pages=1',
    'computrabajo:technologies --country=bo --pages=1',
'computrabajo:technologies --country=ar --pages=1',
'computrabajo:technologies --country=uy --pages=1',
'computrabajo:technologies --country=mx --pages=1',
'computrabajo:technologies --country=co --pages=1',
'computrabajo:technologies --country=ec --pages=1',
'computrabajo:technologies --country=ve --pages=1',
'computrabajo:technologies --country=cl --pages=1',
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
