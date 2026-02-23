<?php

namespace App\Console\Commands\Normalize;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixNullMarketEntityRelations extends Command
{
    protected $signature = 'market:fix-null-entities';
    protected $description = 'Corrige market_entity_id NULL en language_job, technology_job y methodology_job';

    public function handle()
    {
        $this->info("🔧 Corrigiendo NULL market_entity_id...");

        DB::beginTransaction();

        try {

            /* =========================================================
               1️⃣ LANGUAGES
            ========================================================= */
            $updatedLanguages = DB::update("
                UPDATE language_job lj
                JOIN languages l ON l.id = lj.language_id
                JOIN market_entities me ON me.id = l.market_entity_id
                SET lj.market_entity_id = l.market_entity_id
                WHERE lj.market_entity_id IS NULL
            ");

            $this->info("✅ language_job corregidos: {$updatedLanguages}");

            /* =========================================================
               2️⃣ TECHNOLOGIES
            ========================================================= */
            $updatedTechnologies = DB::update("
                UPDATE technology_job tj
                JOIN technologies t ON t.id = tj.technology_id
                JOIN market_entities me ON me.id = t.market_entity_id
                SET tj.market_entity_id = t.market_entity_id
                WHERE tj.market_entity_id IS NULL
            ");

            $this->info("✅ technology_job corregidos: {$updatedTechnologies}");

            /* =========================================================
               3️⃣ METHODOLOGIES
            ========================================================= */
            $updatedMethodologies = DB::update("
                UPDATE methodology_job mj
                JOIN methodologies m ON m.id = mj.methodology_id
                JOIN market_entities me ON me.id = m.market_entity_id
                SET mj.market_entity_id = m.market_entity_id
                WHERE mj.market_entity_id IS NULL
            ");

            $this->info("✅ methodology_job corregidos: {$updatedMethodologies}");

            DB::commit();

            $this->info("🎯 Limpieza completada correctamente.");

        } catch (\Throwable $e) {

            DB::rollBack();
            $this->error("❌ Error: " . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
