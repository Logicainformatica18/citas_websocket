<?php

namespace App\Console\Commands\NormalizeTrends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CleanDuplicateMarketEntitiesCommand extends Command
{
    protected $signature = 'market:clean-duplicates {--dry-run}';

    protected $description = 'Limpia duplicados de market_entities sin romper relaciones';

    protected function normalize(string $name): string
    {
        return Str::of($name)
            ->lower()
            ->replaceMatches('/\(.*?\)/', '')
            ->replace(['certification', 'certificate'], '')
            ->replace(['  '], ' ')
            ->trim()
            ->toString();
    }

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('🔍 Buscando duplicados...');

        $entities = DB::table('market_entities')
            ->where('entity_type', 'certification')
            ->get();

        $grouped = [];

        foreach ($entities as $e) {
            $key = $this->normalize($e->name);
            $grouped[$key][] = $e;
        }

        foreach ($grouped as $key => $group) {

            if (count($group) <= 1) continue;

            $this->warn("⚠️ Duplicados encontrados: {$key}");

            // 🎯 elegir el "bueno"
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
                       1. Mover certifications
                    ========================================= */
                    DB::table('certifications')
                        ->where('market_entity_id', $dup->id)
                        ->update([
                            'market_entity_id' => $keep->id
                        ]);

                    /* =========================================
                       2. Mover pivot careers
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

                    /* =========================================
                       3. Eliminar pivot viejo
                    ========================================= */
                    DB::table('market_entity_career')
                        ->where('market_entity_id', $dup->id)
                        ->delete();

                    /* =========================================
                       4. (Opcional) mover trends
                    ========================================= */
                    DB::table('entity_trends')
                        ->where('market_entity_id', $dup->id)
                        ->update([
                            'market_entity_id' => $keep->id
                        ]);

                    /* =========================================
                       5. Eliminar duplicado
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
