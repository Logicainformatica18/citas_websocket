<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [

    \App\Console\Commands\Certifications\AdzunaByCertificationsCommand::class,
    \App\Console\Commands\GetOnBoardByLanguagesCommand::class,

     \App\Console\Commands\ArbeitnowByLanguagesCommand::class,
         \App\Console\Commands\ArbeitnowByMethodologiesCommand::class,
         \App\Console\Commands\scraping\AdzunaByCompetenciesCommand::class,
    \App\Console\Commands\scraping\ArbeitnowByCompetenciesCommand::class,
    \App\Console\Commands\scraping\ComputrabajoByCompetenciesCommand::class,
    \App\Console\Commands\scraping\GetOnBoardByCompetenciesCommand::class,
    \App\Console\Commands\scraping\GreenhouseByCompetenciesCommand::class,
    \App\Console\Commands\scraping\JobicyByCompetenciesCommand::class,
    \App\Console\Commands\scraping\JoobleByCompetenciesCommand::class,
    \App\Console\Commands\scraping\ReedByCompetenciesCommand::class,
    \App\Console\Commands\scraping\RemoteOkByCompetenciesCommand::class,
    \App\Console\Commands\scraping\RemotiveByCompetenciesCommand::class,
    \App\Console\Commands\scraping\USAJOBSByCompetenciesCommand::class,
    \App\Console\Commands\scraping\WantedlyByCompetenciesCommand::class,
 \App\Console\Commands\JobsDataFeedsByLanguagesCommand::class,
  \App\Console\Commands\RunAllJobs::class,

  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
      \App\Console\Commands\RSS\ImportSomosDigital::class,

    \App\Console\Commands\RSS\ImportMcKinsey::class,
    \App\Console\Commands\RSS\ImportTechCrunch::class,
    \App\Console\Commands\RSS\ImportTheVerge::class,
    \App\Console\Commands\RSS\ImportVentureBeat::class,

 \App\Console\Commands\RunAllRss::class,


];

    /**
     * Define el horario de ejecución de los comandos.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Aquí agregas tus tareas programadas
        $schedule->command('joboffers:import')->everyThirtyMinutes();
            $schedule->command('getonboard:import')->everySixHours();
$schedule->command('worldbank:import
    --countries=all
    --indicators=SL.UEM.TOTL.ZS,SL.IND.EMPL.ZS,SL.SRV.EMPL.ZS,IT.NET.USER.ZS,TX.VAL.ICTG.ZS.UN,GB.XPD.RSDV.GD.ZS
    --from=2020
    --to=2025')->weeklyOn(1, '03:00'); // cada lunes a las 3am
    $schedule->command('scrape:computrabajo --pages=5')->dailyAt('03:00');

    }

    /**
     * Registra los comandos de consola para la aplicación.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
