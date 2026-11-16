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
    // 'arbeitnow:languages', // Desactivado hasta Fase B (matcher)
    'arbeitnow:methodologies',
    // 'arbeitnow:technologies', // Desactivado hasta Fase B (matcher)

    'getonboard:languages',
    'getonboard:methodologies',
    'getonboard:technologies',


 'computrabajo:technologies --country=pe --pages=1',
    'computrabajo:technologies --country=bo --pages=1',
'computrabajo:technologies --country=ar --pages=1',
'computrabajo:technologies --country=uy --pages=1',
'computrabajo:technologies --country=mx --pages=1',
'computrabajo:technologies --country=co --pages=1',
'computrabajo:technologies --country=ec --pages=1',
'computrabajo:technologies --country=ve --pages=1',
'computrabajo:technologies --country=cl --pages=1',

 'computrabajo:languages --pages=1',
'computrabajo:methodologies --pages=1',


 'adzuna:technologies --country=us --pages=1',
    'adzuna:technologies --country=ca --pages=1',
    'adzuna:technologies --country=mx --pages=1',
    'adzuna:technologies --country=br --pages=1',

    'adzuna:technologies --country=es --pages=1',
    'adzuna:technologies --country=fr --pages=1',
    'adzuna:technologies --country=de --pages=1',
    'adzuna:technologies --country=it --pages=1',
    'adzuna:technologies --country=gb --pages=1',
    'adzuna:technologies --country=nl --pages=1',
    'adzuna:technologies --country=ch --pages=1',
    'adzuna:technologies --country=pl --pages=1',

'adzuna:technologies --country=in --pages=1',
'adzuna:technologies --country=sg --pages=1',

'adzuna:technologies --country=za --pages=1',

'adzuna:technologies --country=au --pages=1',
'adzuna:technologies --country=nz --pages=1',


 'adzuna:languages --country=us --pages=1',
'adzuna:languages --country=ca --pages=1',
'adzuna:languages --country=mx --pages=1',
'adzuna:languages --country=br --pages=1',

'adzuna:languages --country=es --pages=1',
'adzuna:languages --country=fr --pages=1',
'adzuna:languages --country=de --pages=1',
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


'jooble:languages --country="United States" --pages=1',
'jooble:languages --country="India" --pages=1',
'jooble:languages --country="United Kingdom" --pages=1',
'jooble:languages --country="Germany" --pages=1',
'jooble:languages --country="Spain" --pages=1',
'jooble:languages --country="Canada" --pages=1',
'jooble:languages --country="Italy" --pages=1',
'jooble:languages --country="Mexico" --pages=1',

'jooble:methodologies --country="United States" --pages=1',
'jooble:methodologies --country="India" --pages=1',
'jooble:methodologies --country="United Kingdom" --pages=1',
'jooble:methodologies --country="Germany" --pages=1',
'jooble:methodologies --country="Spain" --pages=1',
'jooble:methodologies --country="Canada" --pages=1',
'jooble:methodologies --country="Italy" --pages=1',
'jooble:methodologies --country="Mexico" --pages=1',

'jooble:technologies --country="United States" --pages=1',
'jooble:technologies --country="India" --pages=1',
'jooble:technologies --country="United Kingdom" --pages=1',
'jooble:technologies --country="Germany" --pages=1',
'jooble:technologies --country="Spain" --pages=1',
'jooble:technologies --country="Canada" --pages=1',
'jooble:technologies --country="Italy" --pages=1',
'jooble:technologies --country="Mexico" --pages=1',

// 'greenhouse:languages --company=cloudflare', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=stripe', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=discord', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=gitlab', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=dropbox', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=airbnb', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=datadog', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=notion', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=shopify', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=brex', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=zoom', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=hubspot', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=duolingo', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=figma', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=reddit', // Desactivado hasta Fase B (matcher)

// 🌱 Startups tech
// 'greenhouse:languages --company=rippling', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=deel', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=ramp', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=mercury', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=wise', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=bolt', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=sentry', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=zapier', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=benchling', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=anduril', // Desactivado hasta Fase B (matcher)

// // 🌱 Extra (si quieres más variedad)
// 'greenhouse:languages --company=asana', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=grammarly', // Desactivado hasta Fase B (matcher)
// 'greenhouse:languages --company=openai', // Desactivado hasta Fase B (matcher)

'reed:languages',
'reed:methodologies',
'reed:technologies',
'usajobs:languages',
'usajobs:methodologies',
'usajobs:technologies',

'selecta:languages',

'computrabajo:technologies --country=pe --pages=1',
'computrabajo:technologies --country=bo --pages=1',
'computrabajo:technologies --country=ar --pages=1',
'computrabajo:technologies --country=uy --pages=1',
'computrabajo:technologies --country=mx --pages=1',
'computrabajo:technologies --country=co --pages=1',
'computrabajo:technologies --country=ec --pages=1',
'computrabajo:technologies --country=ve --pages=1',
'computrabajo:technologies --country=cl --pages=1',

 'adzuna:certifications --country=us --pages=1',
 'adzuna:certifications --country=ca --pages=1',
 'adzuna:certifications --country=mx --pages=1',
 'adzuna:certifications --country=br --pages=1',

 'adzuna:certifications --country=es --pages=1',
 'adzuna:certifications --country=fr --pages=1',
 'adzuna:certifications --country=de --pages=1',
 'adzuna:certifications --country=it --pages=1',
 'adzuna:certifications --country=gb --pages=1',
 'adzuna:certifications --country=nl --pages=1',
 'adzuna:certifications --country=ch --pages=1',
 'adzuna:certifications --country=pl --pages=1',

 'adzuna:certifications --country=in --pages=1',
 'adzuna:certifications --country=sg --pages=1',

 'adzuna:certifications --country=za --pages=1',

 'adzuna:certifications --country=au --pages=1',
 'adzuna:certifications --country=nz --pages=1',
   'arbeitnow:certification',
 'computrabajo:certifications',
 'getonboard:certifications',

 'greenhouse:certification --company=cloudflare',
'greenhouse:certification --company=stripe',
'greenhouse:certification --company=discord',
'greenhouse:certification --company=gitlab',
'greenhouse:certification --company=dropbox',
'greenhouse:certification --company=airbnb',
'greenhouse:certification --company=datadog',
'greenhouse:certification --company=notion',
'greenhouse:certification --company=shopify',
'greenhouse:certification --company=brex',
'greenhouse:certification --company=zoom',
'greenhouse:certification --company=hubspot',
'greenhouse:certification --company=duolingo',
'greenhouse:certification --company=figma',
'greenhouse:certification --company=reddit',
'jobicy:certifications',
'reed:certifications',
'remoteok:certifications',
'remotive:certifications',



 'adzuna:trends-languages --country=us',
 'adzuna:trends-languages --country=ca',
 'adzuna:trends-languages --country=mx',
 'adzuna:trends-languages --country=br',
 'adzuna:trends-languages --country=es',
 'adzuna:trends-languages --country=fr',
 'adzuna:trends-languages --country=de',
 'adzuna:trends-languages --country=gb',


'trends:discover-languages --limit=50 --sleep=5',
'trends:discover-methodologies --limit=50 --sleep=5',
'trends:discover-technologies --limit=500 --sleep=5',

'normalize:asia-countries',
'normalize:europe-countries',
'joboffers:normalize-regions',
'joboffers:normalize-north-america',
'joboffers:normalize-oceania',
'normalize:geo-regions',
'normalize:peru-regions',
// //asigna el valor en market_entity_id sincronizando languaje, metodology, technology JOBS
'market:fix-null-entities',
'computrabajo:clean-all',
// Recalcula el resumen del Ranking de Lenguajes con los datos ya importados y normalizados
'ranking:refresh-languages',
// Ídem para el Ranking de Tecnologías
'ranking:refresh-technologies',
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
