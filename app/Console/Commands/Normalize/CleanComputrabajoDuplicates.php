<?php

namespace App\Console\Commands\Normalize;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanComputrabajoDuplicates extends Command
{
    protected $signature = 'computrabajo:clean-all {--dry-run}';
    protected $description = 'Limpia duplicados y elimina ofertas no-tech de Computrabajo (sin confirmación)';

    protected array $negativeKeywords = [
        'asesor',
        'cliente',
        'plataforma',
        'cajero',
        'ventas',
        'call center',
        'almacén',
        'logistica',
        'operario',
        'empaquetador',
        'produccion',
        'cobranzas',
        'cartera',
        'pricing',
        'relaciones comunitarias',
        'customer experience',
        'televisores',
        'industrial',
    ];

    protected array $techKeywords = [
        'developer',
        'desarrollador',
        'programador',
        'software',
        'frontend',
        'backend',
        'fullstack',
        'react',
        'angular',
        'vue',
        'node',
        'php',
        'laravel',
        'java',
        'python',
        'devops',
        'cloud',
        'aws',
        'azure',
        'docker',
        'kubernetes',
        'data',
        'machine learning',
        'inteligencia artificial',
        'qa',
        'testing',
        'scrum',
        'ingeniero de sistemas',
    ];

    public function handle()
    {
        $this->info('🚀 Limpieza Computrabajo iniciada...');

        // ============================
        // 🧹 DUPLICADOS
        // ============================

        $subquery = "
            SELECT MIN(id)
            FROM job_offers
            WHERE source = 'Computrabajo'
            GROUP BY SUBSTRING_INDEX(SUBSTRING_INDEX(url, '#', 1), '-', -1)
        ";

        $duplicatesCount = DB::selectOne("
            SELECT COUNT(*) as total
            FROM job_offers
            WHERE id NOT IN (
                SELECT * FROM ($subquery) as keep_ids
            )
            AND source = 'Computrabajo'
        ")->total;

        $this->warn("⚠️ Duplicados: {$duplicatesCount}");

        // ============================
        // 🚫 NO TECH
        // ============================

        $allJobs = DB::table('job_offers')
            ->where('source', 'Computrabajo')
            ->select('id', 'title')
            ->get();

        $nonTechIds = [];

        foreach ($allJobs as $job) {
            if (!$this->isTechRelated($job->title)) {
                $nonTechIds[] = $job->id;
            }
        }

        $this->warn("⚠️ No-tech: " . count($nonTechIds));

        // ============================
        // 🧪 DRY RUN
        // ============================

        if ($this->option('dry-run')) {
            $this->info('🧪 Dry run → no se eliminó nada.');
            return;
        }

        // ============================
        // 💣 DELETE DUPLICADOS
        // ============================

        if ($duplicatesCount > 0) {
            DB::statement("
                DELETE FROM job_offers
                WHERE id NOT IN (
                    SELECT * FROM ($subquery) as keep_ids
                )
                AND source = 'Computrabajo'
            ");
        }

        // ============================
        // 💣 DELETE NO TECH
        // ============================

        if (!empty($nonTechIds)) {
            DB::table('job_offers')
                ->whereIn('id', $nonTechIds)
                ->delete();
        }

        $this->info('✅ Limpieza completada.');
    }

    protected function isTechRelated(string $title): bool
    {
        $text = strtolower($title);

        foreach ($this->techKeywords as $good) {
            if (str_contains($text, $good)) {
                return true;
            }
        }

        return false;
    }
}
