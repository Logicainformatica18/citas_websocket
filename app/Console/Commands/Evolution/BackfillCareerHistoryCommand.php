<?php

namespace App\Console\Commands\Evolution;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BackfillCareerHistoryCommand extends Command
{
    // Por defecto toma el año actual (2026) o el que le pases por parámetro
    protected $signature = 'careers:backfill-all {year=2026}';
    protected $description = 'Reconstruye el historial de Evolución por Carreras (Semanal, Quincenal y Mensual) calculando la distribución exacta y el total del mercado.';

    public function handle()
    {
        $year = (int) $this->argument('year');
        $this->info("=== INICIANDO RECONSTRUCCIÓN INTEGRAL DE EVOLUCIÓN POR CARRERAS PARA EL AÑO {$year} ===");

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
            7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        $currentMonth = 1;

        // Si es el año actual, frena en el mes en curso. Si es un año pasado, procesa los 12 meses.
        $endMonth = ($year === (int)date('Y')) ? Carbon::now()->month : 12;

        while ($currentMonth <= $endMonth) {
            $monthName = $meses[$currentMonth];
            $this->comment("=============================================");
            $this->comment("Procesando cortes acumulados para Carreras: {$monthName}");
            $this->comment("=============================================");

            $startOfMonth = Carbon::create($year, $currentMonth, 1)->startOfDay();
            $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();

            // 📅 Definición exacta de tus tramos idéntica al backfill de empresas
            $tramos = [
                [
                    'type' => 'weekly',
                    'label' => "Semana 1 - {$monthName} {$year}",
                    'start_date' => Carbon::create($year, $currentMonth, 1)->startOfDay(),
                    'end_date' => Carbon::create($year, $currentMonth, 7)->endOfDay(),
                ],
                [
                    'type' => 'weekly',
                    'label' => "Semana 2 - {$monthName} {$year}",
                    'start_date' => Carbon::create($year, $currentMonth, 8)->startOfDay(),
                    'end_date' => Carbon::create($year, $currentMonth, 14)->endOfDay(),
                ],
                [
                    'type' => 'weekly',
                    'label' => "Semana 3 - {$monthName} {$year}",
                    'start_date' => Carbon::create($year, $currentMonth, 15)->startOfDay(),
                    'end_date' => Carbon::create($year, $currentMonth, 21)->endOfDay(),
                ],
                [
                    'type' => 'weekly',
                    'label' => "Semana 4 - {$monthName} {$year}",
                    'start_date' => Carbon::create($year, $currentMonth, 22)->startOfDay(),
                    'end_date' => $endOfMonth->copy(),
                ],
                [
                    'type' => 'biweekly',
                    'label' => "1ra Quincena {$monthName} - {$year}",
                    'start_date' => Carbon::create($year, $currentMonth, 1)->startOfDay(),
                    'end_date' => Carbon::create($year, $currentMonth, 15)->endOfDay(),
                ],
                [
                    'type' => 'biweekly',
                    'label' => "2da Quincena {$monthName} - {$year}",
                    'start_date' => Carbon::create($year, $currentMonth, 16)->startOfDay(),
                    'end_date' => $endOfMonth->copy(),
                ],
                [
                    'type' => 'monthly',
                    'label' => "{$monthName} - {$year}",
                    'start_date' => $startOfMonth->copy(),
                    'end_date' => $endOfMonth->copy(),
                ]
            ];

            foreach ($tramos as $tramo) {
                $startRange = $tramo['start_date'];
                $endRange = $tramo['end_date'];

                // Evitar procesar tramos que aún no han ocurrido en el tiempo real
                if ($endRange->greaterThan(Carbon::now())) {
                    continue;
                }

                $startString = $startRange->format('Y-m-d');
                $endString = $endRange->format('Y-m-d');

                // Manejo de Semestres para coordinar rangos acumulados si se requiere
                $semester = $currentMonth <= 6 ? 1 : 2;
                $semStart = $semester === 1 ? "$year-01-01" : "$year-07-01";

                /*
                ==================================================
                🟢 1️⃣ CALCULAR EL TOTAL REAL ÚNICO DEL MERCADO
                ==================================================
                */
                // Contamos cuántas ofertas globales (sin importar la carrera) existen en este tramo temporal acumulativo
                $totalMarketJobs = DB::table('job_offers')
                    ->whereBetween('published_at', [$semStart, $endString])
                    ->orWhere(function($q) use ($semStart, $endString) {
                        $q->whereNull('published_at')
                          ->whereBetween('created_at', [$semStart, $endString]);
                    })
                    ->count();

                if ($totalMarketJobs === 0) {
                    continue;
                }

                /*
                ==================================================
                🔵 2️⃣ QUERY DE DISTRIBUCIÓN DE CARRERAS
                ==================================================
                */
                // Buscamos y agrupamos las ofertas cruzando las relaciones de tu ecosistema de tablas
                $careersData = DB::table('job_offers as jo')
                    ->join('technology_job as tj', 'tj.job_offer_id', '=', 'jo.id')
                    ->join('course_technology as ct', 'ct.technology_id', '=', 'tj.technology_id')
                    ->join('career_course as cc', 'cc.course_id', '=', 'ct.course_id')
                    ->join('careers as c', 'c.id', '=', 'cc.career_id')
                    ->where(function ($q) use ($semStart, $endString) {
                        $q->whereBetween('jo.published_at', [$semStart, $endString])
                          ->orWhere(function ($q2) use ($semStart, $endString) {
                              $q2->whereNull('jo.published_at')
                                 ->whereBetween('jo.created_at', [$semStart, $endString]);
                          });
                    })
                    ->groupBy('c.id', 'c.name', 'c.slug')
                    ->select([
                        'c.id as career_id',
                        'c.name as career_name',
                        'c.slug as career_slug',
                        DB::raw("COUNT(DISTINCT jo.id) as total_jobs")
                    ])
                    ->get();

                /*
                ==================================================
                💾 3️⃣ TRANSACCIÓN E INSERCIÓN EN CACHÉ
                ==================================================
                */
                DB::transaction(function () use ($careersData, $tramo, $year, $startString, $endString, $totalMarketJobs) {

                    // Limpiamos el tramo exacto antes de reescribir para evitar duplicados
                    DB::table('career_evolution_cache')
                        ->where('year', $year)
                        ->where('period_type', $tramo['type'])
                        ->where('period_label', $tramo['label'])
                        ->delete();

                    foreach ($careersData as $car) {
                        // Calculamos el porcentaje que representa esta carrera frente al mercado global real
                        $percentage = $totalMarketJobs > 0
                            ? round(($car->total_jobs / $totalMarketJobs) * 100, 2)
                            : 0;

                        DB::table('career_evolution_cache')->insert([
                            'year'              => $year,
                            'period_type'       => $tramo['type'],
                            'period_label'      => $tramo['label'],
                            'start_date'        => $startString,
                            'end_date'          => $endString,
                            'career_id'         => $car->career_id,
                            'career_name'       => $car->career_name,
                            'career_slug'       => $car->career_slug,
                            'jobs'              => $car->total_jobs,
                            'percentage'        => $percentage,
                            'total_market_jobs' => $totalMarketJobs,
                            'created_at'        => now(),
                            'updated_at'        => now(),
                        ]);
                    }
                });

                $this->info(" -> Guardada evolución de Carreras [{$tramo['type']}]. Mercado Total: {$totalMarketJobs} ofertas distribuidas.");
            }

            $currentMonth++;
        }

        $this->info("=== ¡PROCESO DE CARRERAS COMPLETADO AL 100%! ===");
        return 0;
    }
}
