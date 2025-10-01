<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define el horario de ejecución de los comandos.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Aquí agregas tus tareas programadas
        $schedule->command('joboffers:import')->everyThirtyMinutes();
            $schedule->command('getonboard:import')->everySixHours();
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
