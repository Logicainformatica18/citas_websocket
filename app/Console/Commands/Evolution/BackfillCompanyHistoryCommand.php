<?php

namespace App\Console\Commands\Evolution;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BackfillCompanyHistoryCommand extends Command
{
    protected $signature = 'companies:backfill-all {year=2026}';
    protected $description = 'Reconstruye el historial de Empresas (Semanal, Quincenal y Mensual) guardando únicamente las TOP 10 y el total real del mercado.';

    public function handle()
    {
        $year = (int) $this->argument('year');
        $this->info("=== INICIANDO RECONSTRUCCIÓN INTEGRAL (TOP 10 + TOTAL MERCADO) PARA EL AÑO {$year} ===");

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
            7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        $currentMonth = 1;
        $endMonth = Carbon::now()->month;
        $markets = ['national', 'international'];

        while ($currentMonth <= $endMonth) {
            $monthName = $meses[$currentMonth];
            $this->comment("=============================================");
            $this->comment("Procesando cortes acumulados para Empresas: {$monthName}");
            $this->comment("=============================================");

            $startOfMonth = Carbon::create($year, $currentMonth, 1)->startOfDay();
            $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();

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

                if ($endRange->greaterThan(Carbon::now())) {
                    continue;
                }

                $startString = $startRange->format('Y-m-d');
                $endString = $endRange->format('Y-m-d');

                $semester = $currentMonth <= 6 ? 1 : 2;
                $semStart = $semester === 1 ? "$year-01-01" : "$year-07-01";

                foreach ($markets as $market) {
                    
                    // 1️⃣ Base Query para el tramo actual
                    $baseQuery = DB::table('job_offers')
                        ->whereNotNull('company')
                        ->whereRaw("TRIM(company) != ''")
                        ->whereBetween('published_at', [$semStart, $endString]);

                    if ($market === 'national') {
                        $baseQuery->where('country', 'Peru');
                    } else {
                        $baseQuery->where('country', '!=', 'Peru');
                    }

                    // 2️⃣ Calcular el TOTAL REAL GLOBAL de ofertas en este tramo específico
                    $totalMarketJobs = $baseQuery->count();

                    // Si no hay ofertas en este mercado y tramo, saltamos para ahorrar recursos
                    if ($totalMarketJobs === 0) {
                        continue;
                    }

                    // 3️⃣ Query de Empresas Agrupadas trayendo únicamente el TOP 10 desde MySQL
                    $companies = (clone $baseQuery)
                        ->select(
                            DB::raw("UPPER(TRIM(company)) as company_normalized"),
                            DB::raw("MIN(company) as company_original"), 
                            DB::raw("COUNT(*) as total_jobs")
                        )
                        ->groupBy(DB::raw("UPPER(TRIM(company))"))
                        ->orderByDesc('total_jobs')
                        ->take(10) // 🌟 LIMITA A NIVEL DE BASE DE DATOS (MAX 10 FILAS)
                        ->get();

                    // 4️⃣ Transacción limpia e inserción veloz
                    DB::transaction(function () use ($companies, $tramo, $year, $market, $startString, $endString, $totalMarketJobs) {
                        
                        // Limpieza del tramo objetivo antes de reescribir
                        DB::table('company_evolution_cache')
                            ->where('year', $year)
                            ->where('period_type', $tramo['type'])
                            ->where('period_label', $tramo['label'])
                            ->where('market_type', $market)
                            ->delete();

                        $position = 1;
                        foreach ($companies as $co) {
                            DB::table('company_evolution_cache')->insert([
                                'year'               => $year,
                                'period_type'        => $tramo['type'],
                                'market_type'        => $market,
                                'period_label'       => $tramo['label'],
                                'start_date'         => $startString,
                                'end_date'           => $endString,
                                'company_normalized' => $co->company_normalized,
                                'company_original'   => $co->company_original,
                                'jobs'               => $co->total_jobs,
                                'ranking_position'   => $position,
                                
                                // 🌟 GUARDAMOS EL TOTAL DEL MERCADO DIRECTO EN LA FILA CACHÉ
                                // Nota: Si no tienes esta columna en tu tabla, puedes agregarla con una migración sencilla 
                                // (integer, nullable). Es vital para que tus porcentajes sean del total global.
                                'total_market_jobs'  => $totalMarketJobs, 
                                
                                'created_at'         => now(),
                                'updated_at'         => now(),
                            ]);
                            $position++;
                        }
                    });

                    $this->info(" -> Guardado TOP 10 de [{$tramo['type']} - {$market}]. Mercado Total: {$totalMarketJobs} ofertas.");
                }
            }

            $currentMonth++;
        }

        $this->info("=== ¡PROCESO COMPLETADO! HISTORIAL OPTIMIZADO AL 100% ===");
        return 0;
    }
}