<?php

namespace App\Console\Commands\NormalizeTrends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CleanDuplicateTechnologiesCommand extends Command
{
    protected $signature = 'market:clean-technologies {--dry-run}';

    protected $description = 'Limpia duplicados y huérfanos de tecnologías';

    protected function normalize(string $name): string
    {
        return Str::of($name)
            ->lower()
            ->replaceMatches('/\(.*?\)/', '')
            ->replace(['  '], ' ')
            ->trim()
            ->toString();
    }

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('🔍 Buscando duplicados de tecnologías...');

        /* ===================================================
           1️⃣ LIMPIAR HUÉRFANOS PRIMERO
        =================================================== */

        $orphans = DB::table('market_entity_career as mec')
            ->leftJoin('market_entities as m', 'm.id', '=', 'mec.market_entity_id')
            ->whereNull('m.id')
            ->select('mec.market_entity_id')
            ->distinct()
            ->get();

        foreach ($orphans as $o) {
            $this->warn("🧹 Huérfano detectado: market_entity_id {$o->market_entity_id}");
        }

        if (!$dryRun) {
            DB::table('market_entity_career as mec')
                ->leftJoin('market_entities as m', 'm.id', '=', 'mec.market_entity_id')
                ->whereNull('m.id')
                ->delete();
        }

        /* ===================================================
           2️⃣ LIMPIAR DUPLICADOS
        =================================================== */

        $entities = DB::table('market_entities')
            ->where('entity_type', 'technology')
            ->get();

        $grouped = [];

        foreach ($entities as $e) {
            $key = $this->normalize($e->name);
            $grouped[$key][] = $e;
        }

        foreach ($grouped as $key => $group) {

            if (count($group) <= 1) continue;

            $this->warn("⚠️ Duplicados encontrados: {$key}");

            // 🎯 prioriza ISIL
            usort($group, function ($a, $b) {
                return ($b->origin === 'isil') <=> ($a->origin === 'isil');
            });

            $keep = $group[0];
            $duplicates = array_slice($group, 1);

            $this->line("👉 Mantener: {$keep->id} - {$keep->name}");

            foreach ($duplicates as $dup) {

                $this->line("🗑 Eliminar: {$dup->id} - {$dup->name}");

                if ($dryRun) continue;

                DB::beginTransaction();

                try {

                    /* =========================================
                       1. Mover careers
                    ========================================= */
                    $relations = DB::table('market_entity_career')
                        ->where('market_entity_id', $dup->id)
                        ->get();

                    foreach ($relations as $rel) {

                        DB::table('market_entity_career')->updateOrInsert(
                            [
                                'market_entity_id' => $keep->id,
                                'career_id'        => $rel->career_id,
                            ],
                            [
                                'relevance_score'  => $rel->relevance_score,
                                'source'           => $rel->source,
                                'updated_at'       => now(),
                                'created_at'       => $rel->created_at ?? now(),
                            ]
                        );
                    }

                    DB::table('market_entity_career')
                        ->where('market_entity_id', $dup->id)
                        ->delete();

                    /* =========================================
                       2. Mover trends
                    ========================================= */
                   $trends = DB::table('entity_trends')
    ->where('market_entity_id', $dup->id)
    ->get();

foreach ($trends as $trend) {

    $exists = DB::table('entity_trends')
        ->where('market_entity_id', $keep->id)
        ->where('source_url', $trend->source_url)
        ->exists();

    if ($exists) {
        $this->warn("⚠️ Trend duplicado omitido: {$trend->source_url}");
        continue;
    }

    DB::table('entity_trends')
        ->where('id', $trend->id)
        ->update([
            'market_entity_id' => $keep->id
        ]);
}
                    /* =========================================
                       3. Eliminar duplicado
                    ========================================= */
                    DB::table('market_entities')
                        ->where('id', $dup->id)
                        ->delete();

                    DB::commit();

                } catch (\Throwable $e) {

                    DB::rollBack();

                    $this->error("❌ Error con ID {$dup->id}: " . $e->getMessage());
                }
            }
        }

        $this->info('🏁 Limpieza completada');

        return Command::SUCCESS;
    }
}
