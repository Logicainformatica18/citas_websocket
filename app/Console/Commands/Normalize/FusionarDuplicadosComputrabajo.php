<?php

namespace App\Console\Commands\Normalize;

use App\Helpers\ComputrabajoHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Fusiona las ofertas de Computrabajo duplicadas por el fragmento #lc=ListOffers-…
 * de la URL, que cambia en cada búsqueda, y deja todas las URL en su forma canónica.
 *
 * - Grupo: ofertas con la misma URL canónica (ComputrabajoHelper::canonicalUrl).
 * - Se conserva la de menor id. Sus relaciones se mantienen; las de las demás se
 *   mueven a ella salvo que ya las tenga (no se duplican), y entonces se eliminan.
 * - Luego se eliminan las ofertas duplicadas y se normaliza la URL de todas.
 * - Antes de escribir se guarda un respaldo JSON con todo lo necesario para
 *   --rollback (ofertas eliminadas, relaciones movidas o eliminadas y URL previas).
 *
 * Reemplaza a computrabajo:clean-all para los duplicados: aquel borra sin mover
 * relaciones ni guardar respaldo.
 */
class FusionarDuplicadosComputrabajo extends Command
{
    protected $signature = 'observatorio:fusionar-duplicados-computrabajo
                            {--dry-run : Muestra los grupos de duplicados y lo que se haría, sin escribir nada}
                            {--rollback : Restaura las ofertas y relaciones desde un respaldo}
                            {--backup= : Archivo de respaldo a restaurar con --rollback (por defecto, el más reciente)}
                            {--force : No pide confirmación antes de escribir}';

    protected $description = 'Fusiona las ofertas de Computrabajo duplicadas por el fragmento #lc de la URL y normaliza sus URL';

    private const PREFIJO_RESPALDO = 'duplicados-computrabajo-';

    /**
     * Tablas que referencian job_offers => columnas que, junto con job_offer_id,
     * identifican la relación (su índice único). Las dos últimas no tienen clave
     * foránea, así que el borrado en cascada no las limpiaría.
     */
    private const PIVOTES = [
        'language_job'            => ['language_id'],
        'technology_job'          => ['technology_id'],
        'methodology_job'         => ['methodology_id'],
        'certification_job'       => ['certification_id'],
        'competency_job_offer'    => ['competency_id'],
        'macro_trend_job'         => ['market_entity_id'],
        'technology_trend_job'    => ['technology_trend_id'],
        'job_offer_alignment'     => ['career_id', 'source'],
        'job_offer_career'        => ['career_id'],
        'tech_position_job_offer' => ['tech_position_id'],
    ];

    public function handle(): int
    {
        try {
            return $this->option('rollback') ? $this->revertir() : $this->aplicar();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            $this->line('No se modificó ningún registro.');

            return self::FAILURE;
        }
    }

    /* =====================================================
       APLICAR
    ===================================================== */

    private function aplicar(): int
    {
        $plan = $this->planificar();

        $this->mostrarPlan($plan);

        if ($plan['grupos'] === [] && $plan['urls'] === []) {
            $this->info('No hay duplicados ni URL por normalizar. No hay nada que hacer.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('Simulación (--dry-run): no se escribió nada.');

            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm('¿Fusionar los duplicados y normalizar las URL?', false)) {
            $this->line('Operación cancelada. No se modificó ningún registro.');

            return self::SUCCESS;
        }

        $archivo = $this->escribirRespaldo($plan);
        $this->info("Respaldo guardado en {$archivo}");

        DB::transaction(function () use ($plan) {
            foreach ($plan['grupos'] as $g) {
                foreach ($g['movidas'] as $m) {
                    $this->filaPivote($m['tabla'], $m['fila'])->update(['job_offer_id' => $g['conservar']]);
                }
                foreach ($g['eliminadas'] as $e) {
                    $this->filaPivote($e['tabla'], $e['fila'])->delete();
                }

                $borradas = DB::table('job_offers')->whereIn('id', array_column($g['ofertas_eliminadas'], 'id'))->delete();
                if ($borradas !== count($g['ofertas_eliminadas'])) {
                    throw new RuntimeException("El grupo de la oferta {$g['conservar']} cambió durante la operación. Se revirtió todo.");
                }
            }

            // Después de borrar los duplicados: la URL canónica puede ser la de uno de ellos.
            foreach ($plan['urls'] as $u) {
                $n = DB::table('job_offers')->where('id', $u['id'])->where('url', $u['antes'])->update(['url' => $u['despues']]);
                if ($n !== 1) {
                    throw new RuntimeException("La URL de la oferta {$u['id']} cambió durante la operación. Se revirtió todo.");
                }
            }
        });

        $this->info(count($plan['grupos']) . ' grupo(s) fusionado(s) y ' . count($plan['urls']) . ' URL normalizada(s).');
        $this->line('Para revertir: php artisan observatorio:fusionar-duplicados-computrabajo --rollback --backup=' . basename($archivo));

        return self::SUCCESS;
    }

    /**
     * Agrupa por URL canónica y decide, para cada relación de los duplicados, si
     * se mueve a la oferta conservada o se elimina porque esta ya la tiene.
     */
    private function planificar(): array
    {
        $ofertas = DB::table('job_offers')
            ->where('source', 'Computrabajo')
            ->where('url', 'like', '%computrabajo.com/%')
            ->orderBy('id')
            ->get(['id', 'url']);

        $grupos = [];
        $urls   = [];

        foreach ($ofertas->groupBy(fn ($o) => ComputrabajoHelper::canonicalUrl($o->url)) as $canonica => $miembros) {
            $conservar = $miembros->first();

            if ($conservar->url !== $canonica) {
                $urls[] = ['id' => (int) $conservar->id, 'antes' => $conservar->url, 'despues' => $canonica];
            }

            if ($miembros->count() > 1) {
                $grupos[] = $this->planGrupo((int) $conservar->id, $miembros->slice(1)->pluck('id')->map(fn ($id) => (int) $id)->all(), $canonica);
            }
        }

        return ['grupos' => $grupos, 'urls' => $urls];
    }

    private function planGrupo(int $conservar, array $duplicados, string $canonica): array
    {
        $movidas    = [];
        $eliminadas = [];

        foreach (self::PIVOTES as $tabla => $claves) {
            $filas = DB::table($tabla)->whereIn('job_offer_id', array_merge([$conservar], $duplicados))->get();

            // Relaciones que la oferta conservada ya tiene (o recibirá).
            $presentes = $filas->where('job_offer_id', $conservar)->map(fn ($f) => $this->claveRelacion($f, $claves))->flip();

            foreach ($filas->where('job_offer_id', '!=', $conservar) as $f) {
                $clave = $this->claveRelacion($f, $claves);
                $fila  = (array) $f;

                if ($presentes->has($clave)) {
                    $eliminadas[] = ['tabla' => $tabla, 'fila' => $fila];
                } else {
                    $movidas[] = ['tabla' => $tabla, 'fila' => $fila];
                    $presentes->put($clave, true);
                }
            }
        }

        return [
            'url_canonica'       => $canonica,
            'conservar'          => $conservar,
            'ofertas_eliminadas' => DB::table('job_offers')->whereIn('id', $duplicados)->orderBy('id')->get()->map(fn ($o) => (array) $o)->all(),
            'movidas'            => $movidas,
            'eliminadas'         => $eliminadas,
        ];
    }

    /* =====================================================
       REVERTIR
    ===================================================== */

    private function revertir(): int
    {
        $archivo  = $this->archivoRespaldo();
        $respaldo = json_decode(File::get($archivo), true);

        if (!is_array($respaldo) || !isset($respaldo['grupos'], $respaldo['urls'])) {
            throw new RuntimeException("El respaldo {$archivo} no tiene el formato esperado.");
        }

        $this->info('Respaldo: ' . basename($archivo) . " (creado el {$respaldo['creado_en']})");

        $bd = DB::connection()->getDatabaseName();
        if (($respaldo['base_de_datos'] ?? null) !== $bd) {
            throw new RuntimeException("El respaldo es de la base «{$respaldo['base_de_datos']}» y la conexión actual es «{$bd}».");
        }

        // Se restaura solo si todo sigue como lo dejó la fusión.
        foreach ($respaldo['urls'] as $u) {
            $actual = DB::table('job_offers')->where('id', $u['id'])->value('url');
            if ($actual !== $u['despues']) {
                throw new RuntimeException("La URL de la oferta {$u['id']} cambió después de la fusión. Revísela antes de revertir.");
            }
        }

        $idsEliminados = collect($respaldo['grupos'])->flatMap(fn ($g) => array_column($g['ofertas_eliminadas'], 'id'));
        if (DB::table('job_offers')->whereIn('id', $idsEliminados)->exists()) {
            throw new RuntimeException('Alguna oferta eliminada por la fusión ya existe de nuevo. Revíselo antes de revertir.');
        }

        $this->mostrarPlan($respaldo);

        if ($this->option('dry-run')) {
            $this->warn('Simulación (--dry-run): se restauraría el respaldo completo. No se escribió nada.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($respaldo) {
            // Primero las URL, para liberar las que usarán las ofertas que se reinsertan.
            foreach ($respaldo['urls'] as $u) {
                DB::table('job_offers')->where('id', $u['id'])->update(['url' => $u['antes']]);
            }

            foreach ($respaldo['grupos'] as $g) {
                DB::table('job_offers')->insert($g['ofertas_eliminadas']);

                foreach ($g['eliminadas'] as $e) {
                    DB::table($e['tabla'])->insert($e['fila']);
                }

                foreach ($g['movidas'] as $m) {
                    $fila = $m['fila'];
                    $this->filaPivote($m['tabla'], ['job_offer_id' => $g['conservar']] + $fila)
                        ->update(['job_offer_id' => $fila['job_offer_id']]);
                }
            }
        });

        $this->info(count($respaldo['grupos']) . ' grupo(s) y ' . count($respaldo['urls']) . ' URL restaurado(s) desde el respaldo.');

        return self::SUCCESS;
    }

    /* =====================================================
       APOYO
    ===================================================== */

    /** Ubica una fila de pivote por id o, si la tabla no tiene id, por su clave compuesta. */
    private function filaPivote(string $tabla, array $fila)
    {
        if (array_key_exists('id', $fila)) {
            return DB::table($tabla)->where('id', $fila['id']);
        }

        $q = DB::table($tabla)->where('job_offer_id', $fila['job_offer_id']);
        foreach (self::PIVOTES[$tabla] as $c) {
            $q->where($c, $fila[$c]);
        }

        return $q;
    }

    private function claveRelacion(object $fila, array $claves): string
    {
        return implode('|', array_map(fn ($c) => (string) $fila->{$c}, $claves));
    }

    private function escribirRespaldo(array $plan): string
    {
        $carpeta = storage_path('app' . DIRECTORY_SEPARATOR . 'backups');
        File::ensureDirectoryExists($carpeta);

        $archivo = $carpeta . DIRECTORY_SEPARATOR . self::PREFIJO_RESPALDO . now()->format('Ymd_His') . '.json';

        File::put($archivo, json_encode([
            'creado_en'     => now()->toDateTimeString(),
            'entorno'       => app()->environment(),
            'base_de_datos' => DB::connection()->getDatabaseName(),
        ] + $plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $archivo;
    }

    private function archivoRespaldo(): string
    {
        $carpeta = storage_path('app' . DIRECTORY_SEPARATOR . 'backups');

        if ($nombre = $this->option('backup')) {
            $ruta = $carpeta . DIRECTORY_SEPARATOR . basename($nombre);

            if (!File::exists($ruta)) {
                throw new RuntimeException("No existe el respaldo {$ruta}.");
            }

            return $ruta;
        }

        $archivos = collect(File::glob($carpeta . DIRECTORY_SEPARATOR . self::PREFIJO_RESPALDO . '*.json'))->sort()->values();

        if ($archivos->isEmpty()) {
            throw new RuntimeException("No hay respaldos en {$carpeta}.");
        }

        return $archivos->last();
    }

    private function mostrarPlan(array $plan): void
    {
        $grupos = collect($plan['grupos']);

        $this->table(['Concepto', 'Cantidad'], [
            ['Grupos de duplicados', $grupos->count()],
            ['Ofertas que se eliminarían', $grupos->sum(fn ($g) => count($g['ofertas_eliminadas']))],
            ['Relaciones movidas a la oferta conservada', $grupos->sum(fn ($g) => count($g['movidas']))],
            ['Relaciones eliminadas (la conservada ya las tiene)', $grupos->sum(fn ($g) => count($g['eliminadas']))],
            ['URL que se normalizarían (sin #lc)', count($plan['urls'])],
        ]);

        $porTabla = $grupos->flatMap(fn ($g) => array_merge(
            array_map(fn ($m) => [$m['tabla'], 'mover'], $g['movidas']),
            array_map(fn ($e) => [$e['tabla'], 'eliminar'], $g['eliminadas'])
        ))->groupBy(0);

        if ($porTabla->isNotEmpty()) {
            $this->table(['Tabla', 'Mover', 'Eliminar'], $porTabla->map(fn (Collection $f, $t) => [
                $t, $f->where(1, 'mover')->count(), $f->where(1, 'eliminar')->count(),
            ])->values()->all());
        }

        $this->table(['Ofertas por grupo', 'Grupos'], $grupos
            ->countBy(fn ($g) => count($g['ofertas_eliminadas']) + 1)
            ->sortKeys()
            ->map(fn ($n, $k) => [$k, $n])->values()->all());
    }
}
