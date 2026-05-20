<?php

namespace App\Console\Commands\Evolution;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Prueba;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TakeTechnologySnapshotCommand extends Command
{
    /**
     * El nombre y la firma del comando en la terminal.
     * Ejemplo: php artisan technologies:take-snapshot --filter=weekly
     */
    protected $signature = 'technologies:take-snapshot {--filter=weekly : Tipo de periodo (weekly, biweekly, monthly)}';

    /**
     * Descripción del comando.
     */
    protected $description = 'Calcula el ranking actual de tecnologías y guarda una foto histórica exacta en la caché de evolución.';

    /**
     * Ejecuta la lógica del comando.
     */
    public function handle()
    {
        $filter = $this->option('filter');
        
        if (!in_array($filter, ['weekly', 'biweekly', 'monthly'])) {
            $this->error("Filtro no válido. Usa: weekly, biweekly o monthly.");
            return 1;
        }

        $this->info("Iniciando la captura de la foto histórica ({$filter})...");

        try {
            // 1. Establecer tiempos basados en "HOY"
            $now = Carbon::now();
            $year = $now->year;
            
            // Determinamos el semestre actual en base a la fecha de ejecución
            $period = $now->month <= 6 ? 's1' : 's2';
            $semester = $period === 's1' ? 1 : 2;

            // Rango del semestre para calcular los máximos del periodo (Igual que en el Index)
            $range = $this->getPeriodRange($period, $year);

            // 2. Obtener etiquetas de tiempo para guardar el registro histórico
            $startDate = $now->format('Y-m-d');
            $endDate   = $now->copy()->endOfWeek()->format('Y-m-d'); // O el cierre que prefieras
            
            if ($filter === 'weekly') {
                $periodLabel = "Semana " . $now->weekOfYear . " - " . $year;
            } elseif ($filter === 'monthly') {
                $periodLabel = $now->translatedFormat('F') . " - " . $year;
                $endDate = $now->copy()->endOfMonth()->format('Y-m-d');
            } else {
                $periodLabel = "Quincena " . ($now->day <= 15 ? '1' : '2') . " " . $now->translatedFormat('F') . " - " . $year;
            }

            // 3. Cargar Ponderaciones Activas
            try {
                $weights = Prueba::getActive('technologies');
            } catch (\Throwable $e) {
                $weights = null;
            }

            $laborWeight = (float) ($weights?->labor_weight ?? 0.7);
            $trendWeight = (float) ($weights?->trend_weight ?? 0.3);

            $this->info("Ponderaciones aplicadas: Laboral={$laborWeight}, Tendencias={$trendWeight}");

            // 4. Calcular Sub-queries de volumenes (Exactamente igual que en tu Index)
            // Demanda Laboral acumulada en el semestre hasta el día de hoy
            $laborSub = DB::table('technology_job as tj')
                ->join('job_offers as j', 'j.id', '=', 'tj.job_offer_id')
                ->whereBetween('j.published_at', [$range['start'], $range['end']])
                ->groupBy('tj.market_entity_id')
                ->select('tj.market_entity_id', DB::raw('COUNT(DISTINCT tj.job_offer_id) as offers'));

            $maxLabor = max(DB::query()->fromSub($laborSub, 'x')->max('offers'), 1);

            // Tendencias acumuladas en el semestre hasta el día de hoy
            $trendSub = DB::table('entity_trends as et')
                ->where('et.year', $year)
                ->whereIn('et.quarter', $semester === 1 ? [1, 2] : [3, 4])
                ->groupBy('et.market_entity_id')
                ->select(
                    'et.market_entity_id',
                    DB::raw('COUNT(et.id) as trend_reports')
                );

            $maxTrendReports = max(DB::query()->fromSub($trendSub, 't')->max('trend_reports'), 1);

            // 5. Procesar y calcular scores para cada tecnología existente
            $technologies = DB::table('market_entities as me')
                ->leftJoinSub($laborSub, 'labor', 'labor.market_entity_id', '=', 'me.id')
                ->leftJoinSub($trendSub, 'trends', 'trends.market_entity_id', '=', 'me.id')
                ->where('me.entity_type', 'technology')
                ->select(
                    'me.id as market_entity_id',
                    DB::raw('COALESCE(labor.offers, 0) as total_jobs'),
                    DB::raw('COALESCE(trends.trend_reports, 0) as total_trends'),
                    // Fórmulas Logarítmicas idénticas al Index
                    DB::raw("ROUND(((LOG(COALESCE(labor.offers,0)+1) / LOG({$maxLabor}+1)) * 100), 1) as labor_score"),
                    DB::raw("ROUND(((LOG(COALESCE(trends.trend_reports,0)+1) / LOG({$maxTrendReports}+1)) * 100), 1) as trend_score"),
                    DB::raw("ROUND((((LOG(COALESCE(labor.offers,0)+1) / LOG({$maxLabor}+1)) * 100 * {$laborWeight}) + ((LOG(COALESCE(trends.trend_reports,0)+1) / LOG({$maxTrendReports}+1)) * 100 * {$trendWeight})), 1) as final_score")
                )
                ->orderByDesc('final_score')
                ->get();

            if ($technologies->isEmpty()) {
                $this->warn("No se encontraron tecnologías para procesar.");
                return 0;
            }

            // 6. Guardar en la Base de Datos (Evolución histórica)
          // 6. Guardar en la Base de Datos (Evolución histórica)
DB::transaction(function () use ($technologies, $year, $filter, $startDate, $endDate, $periodLabel) {
    
    $position = 1;

    foreach ($technologies as $tech) {
        
        // 🔥 CORREGIDO: Se cambió 'updateOrCreate' por 'updateOrInsert'
        DB::table('technology_evolution_cache')->updateOrInsert(
            [
                'market_entity_id' => $tech->market_entity_id,
                'start_date'       => $startDate,
                'period_type'      => $filter,
            ],
            [
                'year'             => $year,
                'end_date'         => $endDate,
                'period_label'     => $periodLabel,
                'jobs'             => $tech->total_jobs,
                'trend_reports'    => $tech->total_trends,
                'labor_score'      => $tech->labor_score,
                'trend_score'      => $tech->trend_score,
                'final_score'      => $tech->final_score,
                'ranking_position' => $position,
                'updated_at'       => now(),
                'created_at'       => now(), // Se aplicará solo si es una inserción nueva
            ]
        );

        $position++;
    }
});

            $this->info("¡Foto histórica capturada con éxito! Registradas " . $technologies->count() . " tecnologías bajo la etiqueta: '{$periodLabel}'.");
            return 0;

        } catch (\Throwable $e) {
            Log::error('[SNAPSHOT_COMMAND_ERROR]', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine()
            ]);

            $this->error("Ocurrió un error al procesar el snapshot: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Mismo formateador de rangos del controlador principal
     */
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