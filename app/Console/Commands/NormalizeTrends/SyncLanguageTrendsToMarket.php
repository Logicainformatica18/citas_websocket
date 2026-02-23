<?php

namespace App\Console\Commands\NormalizeTrends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncLanguageTrendsToMarket extends Command
{
    protected $signature = 'market:sync-language-trends
                            {--dry-run : Solo muestra qué se haría, no escribe nada}';

    protected $description = 'Sincroniza lenguajes ISIL y lenguajes detectados en technology_trends hacia market_entities (idempotente y autocorrectivo)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('🔄 Sincronizando LENGUAJES (ISIL + Trends) → market_entities');

        /* ============================================================
           1️⃣ ASEGURAR QUE TODOS LOS LENGUAJES ISIL EXISTAN EN MARKET
              + SINCRONIZAR market_entity_id EN languages
        ============================================================ */
        $languages = DB::table('languages')->get();

        foreach ($languages as $lang) {

            $slug = Str::slug($lang->name);

            $market = DB::table('market_entities')
                ->where('slug', $slug)
                ->where('entity_type', 'language')
                ->first();

            if (!$market) {

                if ($dryRun) {
                    $this->line("➕ [DRY] Crear market (ISIL): {$lang->name}");
                } else {
                    $marketId = DB::table('market_entities')->insertGetId([
                        'name'        => $lang->name,
                        'slug'        => $slug,
                        'entity_type' => 'language',
                        'origin'      => 'isil',
                        'has_isil'    => 1,
                        'has_trend'   => 0,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);

                    $market = (object)['id' => $marketId];
                }

            } else {

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

            /* 🔥 SINCRONIZACIÓN CRÍTICA */
            if (!$dryRun && isset($market->id)) {
                DB::table('languages')
                    ->where('id', $lang->id)
                    ->update([
                        'market_entity_id' => $market->id
                    ]);
            }
        }

        /* ============================================================
           2️⃣ DETECTAR LENGUAJES EN TECHNOLOGY_TRENDS
        ============================================================ */
        $this->info('🔍 Analizando technology_trends…');

        $rows = DB::select("
            SELECT DISTINCT
              tt.id AS trend_id,
              l.name AS language_name
            FROM technology_trends tt
            JOIN languages l
              ON (
                (
                  CHAR_LENGTH(l.name) >= 3
                  AND l.name NOT IN ('Go')
                  AND LOWER(tt.topic_name) REGEXP CONCAT(
                    '(^|[^a-zA-Z])',
                    LOWER(l.name),
                    '([^a-zA-Z]|$)'
                  )
                )
                OR (
                  l.name = 'Go'
                  AND LOWER(tt.topic_name) REGEXP '(go language|golang)'
                )
                OR (
                  l.name = 'R'
                  AND LOWER(tt.topic_name) REGEXP '(^|[^a-zA-Z])r (language|programming)([^a-zA-Z]|$)'
                )
                OR (
                  l.name = 'C++'
                  AND LOWER(tt.topic_name) REGEXP 'c\\\\+\\\\+'
                )
                OR (
                  l.name = 'C#'
                  AND LOWER(tt.topic_name) REGEXP 'c#'
                )
              )
            WHERE tt.topic_category LIKE '%Lenguaj%'
        ");

        $linked = 0;

        foreach ($rows as $row) {

            $slug = Str::slug($row->language_name);

            $market = DB::table('market_entities')
                ->where('slug', $slug)
                ->where('entity_type', 'language')
                ->first();

            if (!$market) {

                if ($dryRun) {
                    $this->line("➕ [DRY] Crear market (trend-only): {$row->language_name}");
                } else {
                    $marketId = DB::table('market_entities')->insertGetId([
                        'name'        => $row->language_name,
                        'slug'        => $slug,
                        'entity_type' => 'language',
                        'origin'      => 'trend',
                        'has_isil'    => 0,
                        'has_trend'   => 1,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);

                    $market = (object)['id' => $marketId];
                }

            } else {

                if (!$dryRun && !$market->has_trend) {
                    DB::table('market_entities')
                        ->where('id', $market->id)
                        ->update([
                            'has_trend'  => 1,
                            'updated_at' => now(),
                        ]);
                }
            }

            // 🔗 Linkear trend → market (solo si está vacío)
            if (!$dryRun && isset($market->id)) {
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
        $this->info('✅ Sincronización completada (segura, idempotente y autocorrectiva)');

        return Command::SUCCESS;
    }
}
