<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ranking de Lenguajes: respaldo diario. jobs:run-all también lo ejecuta al final.
Schedule::command('ranking:refresh-languages')->dailyAt('07:00')->timezone('America/Lima')->withoutOverlapping();

// Ranking de Tecnologías: respaldo diario. jobs:run-all también lo ejecuta al final.
Schedule::command('ranking:refresh-technologies')->dailyAt('07:00')->timezone('America/Lima')->withoutOverlapping();

// Ranking de Certificaciones: respaldo diario. jobs:run-all también lo ejecuta al final.
Schedule::command('ranking:refresh-certifications')->dailyAt('07:00')->timezone('America/Lima')->withoutOverlapping();

// Precálculos de los demás indicadores (todo menos los tres rankings): respaldo diario.
// jobs:run-all también lo ejecuta al final.
Schedule::command('indicators:refresh-all')->dailyAt('07:00')->timezone('America/Lima')->withoutOverlapping();
