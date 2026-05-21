<?php

namespace App\Console\Commands\Evolution;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TakeCareerSnapshotCommand extends Command
{
    /**
     * Si corre en el CRON diario se ejecuta en modo 'auto'.
     * También te permite forzar un corte desde la terminal si lo necesitas de emergencia.
     */
    protected $signature = 'careers:take-snapshot {--filter=auto : Opciones: auto, weekly, biweekly, monthly}';
    protected $description = 'Calcula y captura de forma automatizada las fotos históricas de la evolución de carreras (Semanal, Quincenal, Mensual).';

    public function handle()
    {
        $filterOption = $this->option('filter');
        $now = Carbon::now();
        $year = $now->year;

        // 1️⃣ DETERMINAR AUTOMÁTICAMENTE SI HOY ES UN DÍA DE CORTE
        $periodsToProcess = [];

        if ($filterOption === 'auto') {
            // A. ¿Es Domingo? Capturamos el cierre de la semana actual
            if ($now->isSunday()) {
                $periodsToProcess['weekly'] = "Semana " . $now->weekOfYear . " - " . $year;
            }

            // B. ¿Es día 15 o el último día del mes? Capturamos la quincena o el mes completo
            if ($now->day === 15) {
                $periodsToProcess['biweekly'] = "1ra Quincena " . $now->translatedFormat('F') . " - " . $year;
            } elseif ($now->copy()->endOfMonth()->isSameDay($now)) {
                $periodsToProcess['biweekly'] = "2da Quincena " . $now->translatedFormat('F') . " - " . $year;

                // C. Si es fin de mes, también procesamos la foto mensual acumulada
                $periodsToProcess['monthly'] = $now->translatedFormat('F') . " - " . $year;
            }

            // Guardián seguro: Si hoy no es día de corte, cerramos la ejecución sin consumir recursos
            if (empty($periodsToProcess)) {
                $this->info("Hoy (" . $now->format('Y-m-d') . ") no es un día de corte establecido. No se requiere tomar fotos históricas.");
                return 0;
            }
        } else {
            // Manejo de ejecuciones manuales por consola
            if (!in_array($filterOption, ['weekly', 'biweekly', 'monthly'])) {
                $this->error("Filtro manual no válido. Usa: auto, weekly, biweekly o monthly.");
                return 1;
            }

            if ($filterOption === 'weekly') {
                $periodsToProcess['weekly'] = "Semana " . $now->weekOfYear . " - " . $year;
            } elseif ($filterOption === 'monthly') {
                $periodsToProcess['monthly'] = $now->translatedFormat('F') . " - " . $year;
            } else {
                $periodsToProcess['biweekly'] = "Quincena " . ($now->day <= 15 ? '1' : '2') . " " . $now->translatedFormat('F') . " - " . $year;
            }
        }

        try {
            // 2️⃣ DEFINIR LOS LÍMITES TEMPORALES DEL SEMESTRE
            $period = $now->month <= 6 ? 's1' : 's2';
            $range = $this->getPeriodRange($period, $year);

            // La foto se sella con la fecha exacta del día de hoy
            $startDate = $now->format('Y-m-d');
            $endDate   = $now->format('Y-m-d');

            /*
            ==================================================
            🟢 3️⃣ CALCULAR EL TOTAL REAL DEL MERCADO HASTA HOY
            ==================================================
            */
            $totalMarketJobs = DB::table('job_offers')
                ->whereBetween('published_at', [$range['start'], $startDate])
                ->orWhere(function($q) use ($range, $startDate) {
                    $q->whereNull('published_at')
                      ->whereBetween('created_at', [$range['start'], $startDate]);
                })
                ->count();

            if ($totalMarketJobs === 0) {
                $this->warn("No se detectaron ofertas de trabajo en el mercado para el periodo actual.");
                return 0;
            }

            /*
            ==================================================
            🔵 4️⃣ DISTRIBUCIÓN DE VACANTES POR CARRERA
            ==================================================
            */
            $careersData = DB::table('job_offers as jo')
                ->join('technology_job as tj', 'tj.job_offer_id', '=', 'jo.id')
                ->join('course_technology as ct', 'ct.technology_id', '=', 'tj.technology_id')
                ->join('career_course as cc', 'cc.course_id', '=', 'ct.course_id')
                ->join('careers as c', 'c.id', '=', 'cc.career_id')
                ->where(function ($q) use ($range, $startDate) {
                    $q->whereBetween('jo.published_at', [$range['start'], $startDate])
                      ->orWhere(function ($q2) use ($range, $startDate) {
                          $q2->whereNull('jo.published_at')
                             ->whereBetween('jo.created_at', [$range['start'], $startDate]);
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

            if ($careersData->isEmpty()) {
                $this->warn("No se encontraron ofertas asociadas a ninguna carrera hoy.");
                return 0;
            }

            /*
            ==================================================
            💾 5️⃣ OPERACIÓN TRANSACCIONAL DE GUARDADO (SNAPSHOT)
            ==================================================
            */
            DB::transaction(function () use ($careersData, $periodsToProcess, $year, $startDate, $endDate, $totalMarketJobs) {
                foreach ($periodsToProcess as $type => $label) {

                    foreach ($careersData as $car) {
                        $percentage = $totalMarketJobs > 0
                            ? round(($car->total_jobs / $totalMarketJobs) * 100, 2)
                            : 0;

                        // Usamos updateOrInsert para reescribir si el cron corre dos veces el mismo día de corte
                        DB::table('career_evolution_cache')->updateOrInsert(
                            [
                                'career_id'   => $car->career_id,
                                'start_date'  => $startDate,
                                'period_type' => $type,
                            ],
                            [
                                'year'              => $year,
                                'end_date'          => $endDate,
                                'period_label'      => $label,
                                'career_name'       => $car->career_name,
                                'career_slug'       => $car->career_slug,
                                'jobs'              => $car->total_jobs,
                                'percentage'        => $percentage,
                                'total_market_jobs' => $totalMarketJobs,
                                'updated_at'        => now(),
                                'created_at'        => now(),
                            ]
                        );
                    }
                    $this->info(" -> Foto de Carreras [{$type}] guardada exitosamente bajo el registro: '{$label}'.");
                }
            });

            return 0;

        } catch (\Throwable $e) {
            Log::error('[CAREER_SNAPSHOT_ERROR]', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine()
            ]);

            $this->error("Error crítico al calcular el snapshot de carreras: " . $e->getMessage());
            return 1;
        }
    }

    private function getPeriodRange(string $period, int $year): array
    {
        if ($period === 's1') {
            return [
                'start' => "$year-01-01",
                'end'   => "$year-06-30",
            ];
        }

        return [
            'start' => "$year-07-01",
            'end'   => "$year-12-31",
        ];
    }
}
