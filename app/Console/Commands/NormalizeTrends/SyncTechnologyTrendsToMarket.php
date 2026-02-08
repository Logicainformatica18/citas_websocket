<?php

namespace App\Console\Commands\NormalizeTrends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncTechnologyTrendsToMarket extends Command
{
    protected $signature = 'market:sync-technology-trends
                            {--dry-run : Solo muestra qué se haría, no escribe nada}';

    protected $description = 'Sincroniza tecnologías ISIL y tecnologías detectadas en technology_trends hacia market_entities (idempotente)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('🔄 Sincronizando TECNOLOGÍAS (ISIL + Trends) → market_entities');

        /* ============================================================
           1️⃣ ASEGURAR TECNOLOGÍAS ISIL EN MARKET
        ============================================================ */
        $technologies = DB::table('technologies')->get();

        foreach ($technologies as $tech) {

            $slug = Str::slug($tech->name);

            $market = DB::table('market_entities')
                ->where('slug', $slug)
                ->where('entity_type', 'technology')
                ->first();

            if (!$market) {
                if ($dryRun) {
                    $this->line("➕ [DRY] Crear market (ISIL): {$tech->name}");
                } else {
                    DB::table('market_entities')->insert([
                        'name'       => $tech->name,
                        'slug'       => $slug,
                        'entity_type'=> 'technology',
                        'origin'     => 'isil',
                        'has_isil'   => 1,
                        'has_trend'  => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } else {
                // ISIL manda
                if (!$dryRun) {
                    DB::table('market_entities')
                        ->where('id', $market->id)
                        ->update([
                            'origin'     => 'isil',
                            'has_isil'   => 1,
                            'updated_at' => now(),
                        ]);
                }
            }
        }

        /* ============================================================
           2️⃣ DETECTAR TECNOLOGÍAS EN TECHNOLOGY_TRENDS
        ============================================================ */
        $this->info('🔍 Analizando technology_trends…');

        $rows = DB::select("
            SELECT DISTINCT
              tt.id AS trend_id,
              t.name AS technology_name
            FROM technology_trends tt
            JOIN technologies t
              ON (
                CHAR_LENGTH(t.name) >= 3
                AND LOWER(tt.topic_name) REGEXP CONCAT(
                  '(^|[^a-zA-Z])',
                  LOWER(t.name),
                  '([^a-zA-Z]|$)'
                )
              )
            WHERE tt.topic_category NOT LIKE '%Lenguaj%'
        ");

        $linked = 0;

        foreach ($rows as $row) {

            $slug = Str::slug($row->technology_name);

            $market = DB::table('market_entities')
                ->where('slug', $slug)
                ->where('entity_type', 'technology')
                ->first();

            if (!$market) {
                // Trend detectó algo que ISIL no tiene
                if ($dryRun) {
                    $this->line("➕ [DRY] Crear market (trend-only): {$row->technology_name}");
                } else {
                    $marketId = DB::table('market_entities')->insertGetId([
                        'name'       => $row->technology_name,
                        'slug'       => $slug,
                        'entity_type'=> 'technology',
                        'origin'     => 'trend',
                        'has_isil'   => 0,
                        'has_trend'  => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $market = (object)['id' => $marketId];
                }
            } else {
                // Marcar que tiene trend
                if (!$dryRun && !$market->has_trend) {
                    DB::table('market_entities')
                        ->where('id', $market->id)
                        ->update([
                            'has_trend'  => 1,
                            'updated_at' => now(),
                        ]);
                }
            }

            // Linkear technology_trends → market (solo si está vacío)
            if (!$dryRun) {
                $updated = DB::table('technology_trends')
                    ->where('id', $row->trend_id)
                    ->whereNull('market_entity_id')
                    ->update([
                        'market_entity_id' => $market->id
                    ]);

                if ($updated) {
                    $linked++;
                }
            }
        }

        $this->info("🔗 Trends enlazados: {$linked}");
        $this->info('✅ Sincronización de tecnologías completada (idempotente)');

        return Command::SUCCESS;
    }
}
