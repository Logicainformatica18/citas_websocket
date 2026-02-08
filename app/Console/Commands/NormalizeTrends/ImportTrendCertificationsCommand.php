<?php

namespace App\Console\Commands\NormalizeTrends;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\MarketEntity;
use Illuminate\Support\Str;

class ImportTrendCertificationsCommand extends Command
{
    protected $signature = 'trends:import-certifications {--dry-run}';

    protected $description = 'Importa certificaciones detectadas desde technology_trends hacia market_entities (solo nuevas, origin=trend)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('🔍 Escaneando technology_trends para certificaciones…');

        /* =========================================================
           1. Detectar posibles certificaciones desde trends
        ========================================================= */
        $candidates = DB::table('technology_trends')
            ->where(function ($q) {
                $q->where('topic_name', 'REGEXP', '(Certified|Certification|Professional)')
                  ->orWhere('topic_category', 'LIKE', '%Certificacion%');
            })
            ->select('topic_name')
            ->distinct()
            ->pluck('topic_name');

        if ($candidates->isEmpty()) {
            $this->info('🟢 No se encontraron certificaciones candidatas');
            return Command::SUCCESS;
        }

        $this->info("🧩 Candidatos detectados: {$candidates->count()}");

        /* =========================================================
           2. Procesar uno por uno
        ========================================================= */
        foreach ($candidates as $rawName) {

            $name = trim($rawName);
            $normalized = mb_strtolower($name);

            // ¿Ya existe en market_entities?
           $slug = Str::slug($name);

$exists = MarketEntity::where('entity_type', 'certification')
    ->where('slug', $slug)
    ->exists();


            if ($exists) {
                // 🔒 No tocar nada existente (ISIL o trend previo)
                $this->line("↪ Ya existe: {$name}");
                continue;
            }

            if ($dryRun) {
                $this->line("🆕 [DRY] {$name} → origin=trend");
                continue;
            }

            // 🆕 Crear SOLO nuevas desde trends
            MarketEntity::create([
                'entity_type' => 'certification',
                'name'        => $name,
                'slug'        => Str::slug($name),
                'origin'      => 'trend',
                'has_trend'   => 1,
            ]);

            $this->info("🆕 Creado: {$name} → origin=trend");
        }

        $this->info('🏁 Importación de certificaciones trend finalizada');
        return Command::SUCCESS;
    }
}
