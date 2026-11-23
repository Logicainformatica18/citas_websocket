<?php

namespace App\Console\Commands\JobOffers;

use App\Models\JobOfferProcess;
use App\Services\JobOffers\TitleExclusionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Revierte un "Eliminar definitivamente" (proceso delete) desde job_offer_deletion_backups: vuelve a
 * insertar las ofertas y luego sus asociaciones (INSERT IGNORE: se puede repetir). Sin argumento lista
 * las eliminaciones con respaldo pendiente de restaurar. Luego: ranking:refresh-*.
 */
class RestoreDeletedOffersCommand extends Command
{
    protected $signature = 'job-offers:restore-deleted {process? : id del proceso de eliminación}';

    protected $description = 'Restaura las ofertas (y sus asociaciones) borradas por "Eliminar definitivamente"';

    public function handle(TitleExclusionService $service): int
    {
        $id = $this->argument('process');
        if (! $id) {
            $rows = DB::table('job_offer_deletion_backups')->whereNull('restored_at')
                ->selectRaw("process_id, SUM(table_name = 'job_offers') AS ofertas, COUNT(*) AS filas, MIN(created_at) AS fecha")
                ->groupBy('process_id')->get();
            $this->table(['Proceso', 'Ofertas', 'Filas respaldadas', 'Fecha'], $rows->map(fn ($r) => (array) $r)->all());

            return self::SUCCESS;
        }

        $delete = JobOfferProcess::where('type', 'delete')->find($id);
        if (! $delete) {
            $this->error("No existe el proceso de eliminación #{$id}.");

            return self::FAILURE;
        }

        $process = JobOfferProcess::start('restore', ['delete_process_id' => (int) $id, 'origin' => 'consola']);
        $process->update(['status' => 'running', 'started_at' => now()]);
        do {
            $done = $service->restoreStep($process, microtime(true) + 30);
            $process->save();
            $this->line("  {$process->processed}/{$process->total} filas");
        } while (! $done);
        $process->update(['status' => 'done', 'finished_at' => now()]);

        $this->info("Restauradas {$process->result['offers_restored']} ofertas (proceso #{$process->id}). Recálculo de rankings encolado.");
        $this->table(['Tabla', 'Filas'], collect($process->result['restored'])->map(fn ($n, $t) => [$t, $n])->values()->all());

        return self::SUCCESS;
    }
}
