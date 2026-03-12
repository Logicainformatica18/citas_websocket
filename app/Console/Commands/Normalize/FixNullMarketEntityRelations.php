<?php

namespace App\Console\Commands\Normalize;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixNullMarketEntityRelations extends Command
{
    protected $signature = 'market:fix-null-entities';
    protected $description = 'Corrige market_entity_id NULL en entidades y pivots';

    public function handle()
    {
        $this->info("🔧 Iniciando reparación de market_entity_id...");

        DB::beginTransaction();

        try {

            /* =========================================================
               1️⃣ TECHNOLOGIES → llenar market_entity_id
            ========================================================= */
            $fixTechnologies = DB::update("
                UPDATE technologies t
                JOIN market_entities me
                    ON LOWER(me.name) = LOWER(t.name)
                SET t.market_entity_id = me.id
                WHERE t.market_entity_id IS NULL
                AND me.entity_type = 'technology'
            ");

            $this->info("✅ technologies corregidas: {$fixTechnologies}");

            /* =========================================================
               2️⃣ LANGUAGES PIVOT
            ========================================================= */
            $fixLanguages = DB::update("
                UPDATE language_job lj
                JOIN languages l ON l.id = lj.language_id
                SET lj.market_entity_id = l.market_entity_id
                WHERE lj.market_entity_id IS NULL
                AND l.market_entity_id IS NOT NULL
            ");

            $this->info("✅ language_job corregidos: {$fixLanguages}");

            /* =========================================================
               3️⃣ TECHNOLOGY PIVOT
            ========================================================= */
            $fixTechnologiesPivot = DB::update("
                UPDATE technology_job tj
                JOIN technologies t ON t.id = tj.technology_id
                SET tj.market_entity_id = t.market_entity_id
                WHERE tj.market_entity_id IS NULL
                AND t.market_entity_id IS NOT NULL
            ");

            $this->info("✅ technology_job corregidos: {$fixTechnologiesPivot}");

            /* =========================================================
               4️⃣ METHODOLOGY PIVOT
            ========================================================= */
            $fixMethodologies = DB::update("
                UPDATE methodology_job mj
                JOIN methodologies m ON m.id = mj.methodology_id
                SET mj.market_entity_id = m.market_entity_id
                WHERE mj.market_entity_id IS NULL
                AND m.market_entity_id IS NOT NULL
            ");

            $this->info("✅ methodology_job corregidos: {$fixMethodologies}");

            DB::commit();

            $this->info("🎯 Reparación completada correctamente.");

        } catch (\Throwable $e) {

            DB::rollBack();
            $this->error("❌ Error: " . $e->getMessage());

        }

        return Command::SUCCESS;
    }
}
