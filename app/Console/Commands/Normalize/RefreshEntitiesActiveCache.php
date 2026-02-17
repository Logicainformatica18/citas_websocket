<?php

namespace App\Console\Commands\Normalize;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshEntitiesActiveCache extends Command
{
//ejecutar diario
    protected $signature = 'cache:refresh-entities-active';
    protected $description = 'Actualiza la cache de entidades activas últimos 12 meses';

    public function handle()
    {
        $this->info('🔄 Actualizando entities_active_cache...');

        DB::beginTransaction();

        try {

            DB::statement("TRUNCATE TABLE entities_active_cache");

            DB::statement("
                INSERT INTO entities_active_cache (market_entity_id, updated_at)

                SELECT DISTINCT market_entity_id, NOW()
                FROM entity_trends
                WHERE created_at >= NOW() - INTERVAL 12 MONTH
                  AND market_entity_id IS NOT NULL

                UNION

                SELECT DISTINCT tj.market_entity_id, NOW()
                FROM job_offers jo
                JOIN technology_job tj ON tj.job_offer_id = jo.id
                WHERE jo.created_at >= NOW() - INTERVAL 12 MONTH
                  AND tj.market_entity_id IS NOT NULL

                UNION

                SELECT DISTINCT lj.market_entity_id, NOW()
                FROM job_offers jo
                JOIN language_job lj ON lj.job_offer_id = jo.id
                WHERE jo.created_at >= NOW() - INTERVAL 12 MONTH
                  AND lj.market_entity_id IS NOT NULL

                UNION

                SELECT DISTINCT mj.market_entity_id, NOW()
                FROM job_offers jo
                JOIN methodology_job mj ON mj.job_offer_id = jo.id
                WHERE jo.created_at >= NOW() - INTERVAL 12 MONTH
                  AND mj.market_entity_id IS NOT NULL
            ");

            DB::commit();

            $count = DB::table('entities_active_cache')->count();

            $this->info("✅ Cache actualizada. Total entidades activas: {$count}");

        } catch (\Throwable $e) {

            DB::rollBack();

            $this->error('❌ Error actualizando cache');
            $this->error($e->getMessage());
        }

        return 0;
    }
}
