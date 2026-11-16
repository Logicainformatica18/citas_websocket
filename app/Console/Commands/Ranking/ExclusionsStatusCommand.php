<?php

namespace App\Console\Commands\Ranking;

use App\Services\Ranking\ExclusionSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estado y apagado de emergencia de la exclusión de asociaciones, sin tocar el .env:
 *   php artisan entities:exclusions             → muestra el estado
 *   php artisan entities:exclusions --disable   → los rankings dejan de aplicar las exclusiones
 *   php artisan entities:exclusions --enable    → las vuelven a aplicar (por defecto)
 * El flag vive en entity_association_exclusion_settings (fila id = 1). No borra exclusiones ni
 * asociaciones. El cambio se ve después de recalcular (ranking:refresh-*).
 */
class ExclusionsStatusCommand extends Command
{
    protected $signature = 'entities:exclusions
        {--enable : aplicar las exclusiones en los rankings (por defecto)}
        {--disable : apagado de emergencia: calcular los rankings sin las exclusiones}';

    protected $description = 'Muestra o cambia (--enable / --disable) si los rankings aplican las exclusiones del matcher';

    public function handle(): int
    {
        if ($this->option('enable') && $this->option('disable')) {
            $this->error('Usa --enable o --disable, no ambos.');

            return self::INVALID;
        }
        if (! ExclusionSettings::settingsTableExists()) {
            $this->error('Falta la tabla ' . ExclusionSettings::SETTINGS_TABLE . ': aplicar antes DESPLIEGUE_RANKINGS_PRODUCCION.sql (o migrate).');

            return self::FAILURE;
        }

        if ($this->option('enable') || $this->option('disable')) {
            $new = (bool) $this->option('enable');
            $old = ExclusionSettings::enabled();
            ExclusionSettings::setEnabled($new);
            $this->info($new ? 'Exclusiones ACTIVADAS.' : 'Exclusiones DESACTIVADAS (apagado de emergencia).');
            if ($old !== $new) {
                $this->warn('Para que el cambio se vea en los rankings, ejecutar:');
                $this->line('  php artisan ranking:refresh-languages');
                $this->line('  php artisan ranking:refresh-technologies');
                $this->line('  php artisan ranking:refresh-certifications');
            } else {
                $this->line('(ya estaban así; no hace falta recalcular)');
            }
        }

        $this->status();

        return self::SUCCESS;
    }

    private function status(): void
    {
        $rows = [
            ['Estado', ExclusionSettings::enabled() ? 'ACTIVAS (los rankings las aplican)' : 'DESACTIVADAS (apagado de emergencia)'],
            ['Fecha de confianza', ExclusionSettings::trustSince() ?? 'sin definir (la guarda la primera ejecución de entities:exclude-associations)'],
        ];
        if (Schema::hasTable(ExclusionSettings::TABLE)) {
            foreach (DB::table(ExclusionSettings::TABLE)->selectRaw('pivot_table, SUM(vigente = 1) AS vigentes, SUM(vigente = 0) AS retiradas, MAX(revisado_en) AS revisado')
                ->groupBy('pivot_table')->orderBy('pivot_table')->get() as $r) {
                $rows[] = [$r->pivot_table, sprintf('%d vigentes · %d retiradas · última revisión %s', $r->vigentes, $r->retiradas, $r->revisado)];
            }
        } else {
            $rows[] = ['Tabla de exclusiones', 'no existe (el filtro se omite)'];
        }
        $this->table(['', ''], $rows);
    }
}
