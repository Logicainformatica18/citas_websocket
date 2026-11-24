<?php

namespace App\Console\Commands\Evolution;

use App\Services\Indicators\IndicatorScope;
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
        $semesters = [];   // agregación por semestre (semesterCounts), calculada una vez

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
                🟢 1️⃣ TOTAL DEL MERCADO Y 🔵 2️⃣ DISTRIBUCIÓN POR CARRERA
                ==================================================
                */
                // Ofertas acumuladas desde el inicio del semestre hasta el corte, con el criterio de los
                // indicadores (IndicatorScope). Antes eran dos consultas pesadas por tramo (77 tramos,
                // 515 s); ahora una agregación por semestre (semesterCounts) y cada tramo se suma en PHP
                // con exactamente la misma condición de fechas.
                $agg = $semesters[$semester] ??= $this->semesterCounts($year, $semester);

                $totalMarketJobs = $this->countUntil($agg['market'], $semStart, $endString);

                if ($totalMarketJobs === 0) {
                    continue;
                }

                $careersData = collect($agg['careers'])
                    ->map(fn ($c) => (object) [
                        'career_id' => $c['id'],
                        'career_name' => $c['name'],
                        'career_slug' => $c['slug'],
                        'total_jobs' => $this->countUntil($c['days'], $semStart, $endString),
                    ])
                    ->filter(fn ($c) => $c->total_jobs > 0)
                    ->values();

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

    /**
     * Ofertas del semestre por día de la fecha efectiva (published_at, o created_at si no hay), con
     * el criterio de los indicadores: total del mercado y, por carrera, las que tienen al menos una
     * asociación válida (IndicatorScope) a una tecnología de los cursos de la carrera.
     * Cada día guarda [todas, a las 00:00:00]: los tramos comparan la fecha y hora con 'Y-m-d'
     * (medianoche), así que el último día solo cuenta lo publicado exactamente a las 00:00:00.
     *
     * @return array{market: array<string, array{0:int,1:int}>, careers: array<int, array{id:int, name:string, slug:?string, days: array}>}
     */
    private function semesterCounts(int $year, int $semester): array
    {
        $from = $semester === 1 ? "{$year}-01-01" : "{$year}-07-01";
        $to = $semester === 1 ? "{$year}-06-30" : "{$year}-12-31";   // último corte posible ('Y-m-d')
        $d = 'COALESCE(jo.published_at, jo.created_at)';
        $inSemester = function ($q) use ($from, $to) {
            $q->whereBetween('jo.published_at', [$from, $to])
              ->orWhere(function ($q2) use ($from, $to) {
                  $q2->whereNull('jo.published_at')->whereBetween('jo.created_at', [$from, $to]);
              });
        };
        $cols = [DB::raw("DATE({$d}) as d"), DB::raw("TIME({$d}) = '00:00:00' as mid"), DB::raw('COUNT(*) as n')];

        $market = [];
        foreach (IndicatorScope::offers(DB::table('job_offers as jo'), 'jo')->where($inSemester)
            ->groupBy('d', 'mid')->select($cols)->get() as $r) {
            $market[$r->d][0] = ($market[$r->d][0] ?? 0) + $r->n;
            $market[$r->d][1] = ($market[$r->d][1] ?? 0) + ($r->mid ? $r->n : 0);
        }

        $careers = [];
        $rows = IndicatorScope::offers(DB::table('careers as c')->crossJoin('job_offers as jo'), 'jo')
            ->where($inSemester)
            ->whereExists(function ($q) {
                $q->from('technology_job as tj')
                    ->join('course_technology as ct', 'ct.technology_id', '=', 'tj.technology_id')
                    ->join('career_course as cc', function ($j) {
                        $j->on('cc.course_id', '=', 'ct.course_id')->on('cc.career_id', '=', 'c.id');
                    })
                    ->whereColumn('tj.job_offer_id', 'jo.id');
                IndicatorScope::associations($q, 'technology_job', 'tj');
            })
            ->groupBy('c.id', 'c.name', 'c.slug', 'd', 'mid')
            ->select(array_merge(['c.id', 'c.name', 'c.slug'], $cols))
            ->get();
        foreach ($rows as $r) {
            $c = &$careers[$r->id];
            $c ??= ['id' => (int) $r->id, 'name' => $r->name, 'slug' => $r->slug, 'days' => []];
            $c['days'][$r->d][0] = ($c['days'][$r->d][0] ?? 0) + $r->n;
            $c['days'][$r->d][1] = ($c['days'][$r->d][1] ?? 0) + ($r->mid ? $r->n : 0);
            unset($c);
        }
        $this->line("   Semestre {$semester}: " . array_sum(array_column($market, 0)) . ' ofertas, ' . count($careers) . ' carreras con demanda');

        return ['market' => $market, 'careers' => $careers];
    }

    /** Ofertas con fecha efectiva entre $from 00:00 y $to 00:00 (como whereBetween con 'Y-m-d'). */
    private function countUntil(array $days, string $from, string $to): int
    {
        $n = 0;
        foreach ($days as $day => [$all, $midnight]) {
            if ($day >= $from && $day < $to) {
                $n += $all;
            } elseif ($day === $to) {
                $n += $midnight;
            }
        }

        return $n;
    }
}
