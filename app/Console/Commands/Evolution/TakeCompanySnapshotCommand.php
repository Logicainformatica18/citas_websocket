<?php

namespace App\Console\Commands\Evolution;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TakeCompanySnapshotCommand extends Command
{
    /**
     * Firma del comando modificado para empresas.
     */
    protected $signature = 'companies:take-snapshot {--filter=auto : Opciones: auto, weekly, biweekly, monthly}';

    protected $description = 'Calcula de forma automática o manual las fotos históricas necesarias para la Evolución de Empresas (Semanal, Quincenal, Mensual).';

    public function handle()
    {
        $filterOption = $this->option('filter');
        $now = Carbon::now();
        $year = $now->year;

        // 1. Determinar de forma automática qué tipos de fotos corresponden a la fecha de HOY
        $periodsToProcess = [];

        if ($filterOption === 'auto') {
            // A. ¿Es Domingo? Tomamos la foto semanal
            if ($now->isSunday()) {
                $periodsToProcess['weekly'] = "Semana " . $now->weekOfYear . " - " . $year;
            }

            // B. ¿Es día 15 o último día del mes? Tomamos la foto quincenal
            if ($now->day === 15) {
                $periodsToProcess['biweekly'] = "1ra Quincena " . $now->translatedFormat('F') . " - " . $year;
            } elseif ($now->copy()->endOfMonth()->isSameDay($now)) {
                $periodsToProcess['biweekly'] = "2da Quincena " . $now->translatedFormat('F') . " - " . $year;

                // C. Si es el último día del mes, también se captura la foto mensual de golpe
                $periodsToProcess['monthly'] = $now->translatedFormat('F') . " - " . $year;
            }

            // Si hoy no cae en ninguna de las condiciones de corte, el comando avisa y termina de forma segura
            if (empty($periodsToProcess)) {
                $this->info("Hoy (" . $now->format('Y-m-d') . ") no es un día de corte establecido. No se requiere tomar fotos históricas para empresas.");
                return 0;
            }
        } else {
            // Si decides forzarlo manualmente pasándole el parámetro en la terminal
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
            // 2. Definir el mercado a evaluar ('national' e 'international')
            $marketTypes = ['national', 'international'];

            // Iteramos sobre cada periodo pendiente por procesar hoy (pueden ser biweekly y monthly al mismo tiempo)
            foreach ($periodsToProcess as $type => $label) {

                // Calculamos dinámicamente el inicio y fin exacto del periodo actual basados en el día de hoy
                $dates = $this->calculatePeriodDates($type, $now);

                foreach ($marketTypes as $market) {
                    $this->line("⏳ Procesando [{$type}] - Mercado [{$market}] para la etiqueta: '{$label}'...");

                    // 3. Query base para extraer los datos crudos desde job_offers en ese rango de fechas
                    $query = DB::table('job_offers')
                        ->whereNotNull('company')
                        ->where(function ($q) use ($dates) {
                            $q->whereBetween('published_at', [$dates['start'], $dates['end']])
                              ->orWhere(function ($q2) use ($dates) {
                                  $q2->whereNull('published_at')
                                     ->whereBetween('created_at', [$dates['start'], $dates['end']]);
                              });
                        });

                    // Separación de mercado nacional o internacional
                    if ($market === 'national') {
                        $query->where('country', 'Peru');
                    } else {
                        $query->where('country', '!=', 'Peru');
                    }

                    $companies = $query
                        ->select(
                            DB::raw("TRIM(company) as company_original"),
                            DB::raw("UPPER(TRIM(company)) as company_normalized"),
                            DB::raw("COUNT(*) as jobs")
                        )
                        ->groupBy(DB::raw("TRIM(company)"))
                        ->orderByDesc('jobs')
                        ->get();

                    if ($companies->isEmpty()) {
                        continue;
                    }

                    // 4. Calcular el total del mercado para este periodo específico
                    $totalMarketJobs = $companies->sum('jobs');

                    // 5. Insertar o actualizar el snapshot en la tabla de caché de empresas
                    DB::transaction(function () use ($companies, $type, $market, $label, $dates, $year, $totalMarketJobs) {
                        $position = 1;

                        foreach ($companies as $comp) {
                            DB::table('company_evolution_cache')->updateOrInsert(
                                [
                                    'year'               => $year,
                                    'period_type'        => $type,
                                    'market_type'        => $market,
                                    'period_label'       => $label,
                                    'company_normalized' => $comp->company_normalized,
                                ],
                                [
                                    'start_date'        => $dates['start'],
                                    'end_date'          => $dates['end'],
                                    'company_original'  => $comp->company_original,
                                    'jobs'              => $comp->jobs,
                                    'ranking_position'  => $position,
                                    'total_market_jobs' => $totalMarketJobs,
                                    'updated_at'        => now(),
                                    'created_at'        => DB::raw('COALESCE(created_at, NOW())')
                                ]
                            );
                            $position++;
                        }
                    });

                    $this->info(" -> Foto histórica de Empresas [{$type} - {$market}] guardada con éxito.");
                }
            }

            return 0;

        } catch (\Throwable $e) {
            Log::error('[COMPANY_SNAPSHOT_COMMAND_ERROR]', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine()
            ]);

            $this->error("Ocurrió un error al procesar el snapshot de empresas: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Calcula las fechas exactas de inicio y fin del periodo en curso relativo a HOY.
     */
    private function calculatePeriodDates(string $type, Carbon $now): array
    {
        switch ($type) {
            case 'monthly':
                return [
                    'start' => $now->copy()->startOfMonth()->format('Y-m-d'),
                    'end'   => $now->copy()->endOfMonth()->format('Y-m-d'),
                ];

            case 'biweekly':
                if ($now->day <= 15) {
                    return [
                        'start' => $now->copy()->startOfMonth()->format('Y-m-d'),
                        'end'   => $now->copy()->startOfMonth()->addDays(14)->format('Y-m-d'), // Del 1 al 15
                    ];
                } else {
                    return [
                        'start' => $now->copy()->startOfMonth()->addDays(15)->format('Y-m-d'), // Día 16
                        'end'   => $now->copy()->endOfMonth()->format('Y-m-d'),
                    ];
                }

            default: // weekly
                return [
                    'start' => $now->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d'),
                    'end'   => $now->copy()->endOfWeek(Carbon::SUNDAY)->format('Y-m-d'),
                ];
        }
    }
}
