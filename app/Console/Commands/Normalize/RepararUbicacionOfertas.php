<?php

namespace App\Console\Commands\Normalize;

use App\Helpers\ComputrabajoHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Corrige el país, la región y las coordenadas de las ofertas de Computrabajo
 * cuyo país guardado no coincide con el del dominio ({cc}.computrabajo.com).
 *
 * El error venía de getCoords(), que buscaba la ciudad en todo el catálogo
 * cities y sobrescribía el país del dominio con el de la primera ciudad
 * homónima (p. ej., Monterrey → Colombia).
 *
 * - Solo se tocan URL cuyo subdominio figura en DOMINIOS; el resto se ignora.
 * - La ciudad se busca solo dentro del país del dominio. Si no se encuentra,
 *   las coordenadas quedan vacías en lugar de tomar las de otro país.
 * - Con --geocodificar, las que siguen sin coordenadas se consultan en Nominatim,
 *   también restringido al país (countrycodes) y a una petición por segundo.
 * - Antes de escribir se guarda un respaldo JSON; si un solo registro cambió
 *   entre la lectura y la escritura, no se modifica nada.
 */
class RepararUbicacionOfertas extends Command
{
    protected $signature = 'observatorio:reparar-ubicacion-ofertas
                            {--dry-run : Muestra qué ofertas cambiarían, sin escribir nada}
                            {--rollback : Restaura la ubicación anterior desde un respaldo}
                            {--backup= : Archivo de respaldo a restaurar con --rollback (por defecto, el más reciente)}
                            {--geocodificar : Para las ofertas sin coordenadas, consulta Nominatim restringido al país (1 petición/s)}
                            {--limite=0 : Con --geocodificar, máximo de ofertas sin coordenadas a geocodificar (0 = todas)}
                            {--force : No pide confirmación antes de escribir}';

    protected $description = 'Corrige país, región y coordenadas de las ofertas de Computrabajo según el dominio de su URL';

    private const PREFIJO_RESPALDO = 'ubicacion-ofertas-';

    private const REGION = 'Latinoamérica';

    /** Subdominio de Computrabajo → [iso2, nombre del país tal como se guarda en job_offers]. */
    private const DOMINIOS = [
        'ar' => ['AR', 'Argentina'],
        'bo' => ['BO', 'Bolivia'],
        'cl' => ['CL', 'Chile'],
        'co' => ['CO', 'Colombia'],
        'ec' => ['EC', 'Ecuador'],
        'mx' => ['MX', 'México'],
        'pe' => ['PE', 'Peru'],
        'py' => ['PY', 'Paraguay'],
        'uy' => ['UY', 'Uruguay'],
        've' => ['VE', 'Venezuela'],
    ];

    /** Columnas que se corrigen y se respaldan. */
    private const CAMPOS = ['country', 'region', 'region_normalized', 'latitude', 'longitude'];

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
        $cambios = $this->calcularCambios();

        if ($this->option('geocodificar')) {
            $cambios = $this->geocodificar($cambios);
        }

        if ($cambios->isEmpty()) {
            $this->info('Todas las ofertas de Computrabajo tienen el país de su dominio. No hay nada que hacer.');

            return self::SUCCESS;
        }

        $this->mostrarResumen($cambios);

        if ($this->option('dry-run')) {
            $this->warn("Simulación (--dry-run): se corregirían {$cambios->count()} oferta(s). No se escribió nada.");

            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm("¿Corregir la ubicación de {$cambios->count()} oferta(s)?", false)) {
            $this->line('Operación cancelada. No se modificó ningún registro.');

            return self::SUCCESS;
        }

        $archivo = $this->escribirRespaldo($cambios);
        $this->info("Respaldo guardado en {$archivo}");

        $this->escribir($cambios, 'antes', 'despues');

        $this->info("{$cambios->count()} oferta(s) corregida(s).");
        $this->line('Para revertir: php artisan observatorio:reparar-ubicacion-ofertas --rollback --backup=' . basename($archivo));

        return self::SUCCESS;
    }

    /**
     * Ofertas de Computrabajo cuyo país no coincide con el del dominio, con sus
     * valores actuales ('antes') y corregidos ('despues').
     */
    private function calcularCambios(): Collection
    {
        $ciudades = $this->indiceCiudades();
        $cambios  = collect();

        foreach (self::DOMINIOS as $sub => [$iso2, $pais]) {
            // La colación utf8mb4_unicode_ci compara sin tildes: 'Perú' = 'Peru'.
            $ofertas = DB::table('job_offers')
                ->where('url', 'like', "https://{$sub}.computrabajo.com/%")
                ->whereRaw('NOT (country <=> ?)', [$pais])
                ->orderBy('id')
                ->get(array_merge(['id', 'url', 'city'], self::CAMPOS));

            foreach ($ofertas as $o) {
                $ciudad = $ciudades[$iso2][$this->clave($o->city)] ?? null;

                $cambios->push([
                    'id'      => (int) $o->id,
                    'url'     => $o->url,
                    'city'    => $o->city,
                    'dominio' => "{$sub}.computrabajo.com",
                    'iso2'    => $iso2,
                    'antes'   => collect(self::CAMPOS)->mapWithKeys(fn ($c) => [$c => $o->{$c}])->all(),
                    'despues' => [
                        'country'           => $pais,
                        'region'            => self::REGION,
                        'region_normalized' => $o->region_normalized === null ? null : self::REGION,
                        'latitude'          => $ciudad?->lat,
                        'longitude'         => $ciudad?->lng,
                    ],
                ]);
            }
        }

        return $cambios;
    }

    /**
     * Completa con Nominatim las coordenadas que el catálogo cities no resolvió.
     * Se consulta una vez por ciudad y país; si no hay resultado, siguen vacías.
     */
    private function geocodificar(Collection $cambios): Collection
    {
        $pendientes = $cambios->filter(fn ($c) => $c['despues']['latitude'] === null
            && trim((string) $c['city']) !== ''
            && strtolower((string) $c['city']) !== 'remote');

        if (($limite = (int) $this->option('limite')) > 0) {
            $pendientes = $pendientes->take($limite);
        }

        $ciudades = $pendientes->unique(fn ($c) => $c['iso2'] . '|' . mb_strtolower($c['city']));
        $this->info("Geocodificando {$pendientes->count()} oferta(s) sin coordenadas: {$ciudades->count()} consulta(s) a Nominatim (~{$ciudades->count()} s)…");

        $resultados = [];
        $this->withProgressBar($ciudades, function ($c) use (&$resultados) {
            $resultados[$c['iso2'] . '|' . mb_strtolower($c['city'])] = ComputrabajoHelper::geocode($c['city'], $c['despues']['country'], $c['iso2']);
        });
        $this->newLine(2);

        $ids = $pendientes->pluck('id')->flip();

        $cambios = $cambios->map(function ($c) use ($ids, $resultados) {
            if (!$ids->has($c['id'])) {
                return $c;
            }

            $geo = $resultados[$c['iso2'] . '|' . mb_strtolower($c['city'])] ?? null;
            $c['despues']['latitude']  = $geo['lat'] ?? null;
            $c['despues']['longitude'] = $geo['lng'] ?? null;
            $c['geocodificado']        = $geo['display_name'] ?? null;

            return $c;
        });

        $this->table(
            ['Oferta', 'Ciudad', 'País nuevo', 'Lat', 'Lng', 'Resultado de Nominatim'],
            $cambios->filter(fn ($c) => $ids->has($c['id']))->map(fn ($c) => [
                $c['id'], $c['city'], $c['despues']['country'],
                $c['despues']['latitude'] ?? '—', $c['despues']['longitude'] ?? '—',
                $c['geocodificado'] ? mb_strimwidth($c['geocodificado'], 0, 70, '…') : 'no encontrada',
            ])->values()->all()
        );

        return $cambios;
    }

    /**
     * Ciudades de los países de DOMINIOS indexadas por iso2 y nombre normalizado
     * (city y city_ascii). Si hay homónimas en el mismo país, gana la más poblada.
     */
    private function indiceCiudades(): array
    {
        $indice = [];

        DB::table('cities')
            ->whereIn('iso2', array_column(self::DOMINIOS, 0))
            ->orderByRaw('COALESCE(population, 0) DESC')
            ->get(['city', 'city_ascii', 'iso2', 'lat', 'lng'])
            ->each(function ($c) use (&$indice) {
                foreach ([$c->city, $c->city_ascii] as $nombre) {
                    $indice[$c->iso2][$this->clave($nombre)] ??= $c;
                }
            });

        return $indice;
    }

    /* =====================================================
       REVERTIR
    ===================================================== */

    private function revertir(): int
    {
        $archivo  = $this->archivoRespaldo();
        $respaldo = json_decode(File::get($archivo), true);

        if (!is_array($respaldo) || empty($respaldo['ofertas'])) {
            throw new RuntimeException("El respaldo {$archivo} no tiene el formato esperado.");
        }

        $this->info('Respaldo: ' . basename($archivo) . " (creado el {$respaldo['creado_en']})");

        $bd = DB::connection()->getDatabaseName();
        if (($respaldo['base_de_datos'] ?? null) !== $bd) {
            throw new RuntimeException("El respaldo es de la base «{$respaldo['base_de_datos']}» y la conexión actual es «{$bd}».");
        }

        $ofertas = collect($respaldo['ofertas']);
        $actuales = DB::table('job_offers')
            ->whereIn('id', $ofertas->pluck('id'))
            ->get(array_merge(['id', 'url'], self::CAMPOS))
            ->keyBy('id');

        // Solo se restaura sobre el valor corregido. Si ya tiene el anterior, se omite;
        // si tiene otro distinto, se detiene para no pisar un cambio posterior.
        $pendientes = $ofertas->filter(function ($o) use ($actuales) {
            $actual = $actuales->get($o['id']);

            if (!$actual || $actual->url !== $o['url']) {
                throw new RuntimeException("La oferta {$o['id']} ya no existe o su URL cambió. Revísela antes de revertir.");
            }

            if ($this->coincide($actual, $o['antes'])) {
                return false;
            }

            if (!$this->coincide($actual, $o['despues'])) {
                throw new RuntimeException("La oferta {$o['id']} cambió después de aplicar el respaldo. Revísela antes de revertir.");
            }

            return true;
        })->values();

        if ($pendientes->isEmpty()) {
            $this->info('Las ofertas ya tienen la ubicación del respaldo. No hay nada que hacer.');

            return self::SUCCESS;
        }

        $this->mostrarResumen($pendientes->map(fn ($o) => ['antes' => $o['despues'], 'despues' => $o['antes']] + $o));

        if ($this->option('dry-run')) {
            $this->warn("Simulación (--dry-run): se restaurarían {$pendientes->count()} oferta(s). No se escribió nada.");

            return self::SUCCESS;
        }

        $this->escribir($pendientes, 'despues', 'antes');

        $this->info("{$pendientes->count()} oferta(s) restaurada(s) desde el respaldo.");

        return self::SUCCESS;
    }

    /* =====================================================
       ESCRITURA
    ===================================================== */

    /**
     * Escribe $hacia en cada oferta, en una sola transacción. Se vuelve a leer con
     * bloqueo y se exige que el valor actual siga siendo $desde.
     */
    private function escribir(Collection $cambios, string $desde, string $hacia): void
    {
        DB::transaction(function () use ($cambios, $desde, $hacia) {
            foreach ($cambios->chunk(500) as $lote) {
                $actuales = DB::table('job_offers')
                    ->whereIn('id', $lote->pluck('id'))
                    ->lockForUpdate()
                    ->get(array_merge(['id'], self::CAMPOS))
                    ->keyBy('id');

                foreach ($lote as $c) {
                    $actual = $actuales->get($c['id']);

                    if (!$actual || !$this->coincide($actual, $c[$desde])) {
                        throw new RuntimeException("La oferta {$c['id']} cambió durante la operación. Se revirtió todo.");
                    }

                    DB::table('job_offers')->where('id', $c['id'])->update($c[$hacia] + ['updated_at' => now()]);
                }
            }
        });
    }

    private function escribirRespaldo(Collection $cambios): string
    {
        $carpeta = storage_path('app' . DIRECTORY_SEPARATOR . 'backups');
        File::ensureDirectoryExists($carpeta);

        $archivo = $carpeta . DIRECTORY_SEPARATOR . self::PREFIJO_RESPALDO . now()->format('Ymd_His') . '.json';

        File::put($archivo, json_encode([
            'creado_en'     => now()->toDateTimeString(),
            'entorno'       => app()->environment(),
            'base_de_datos' => DB::connection()->getDatabaseName(),
            'ofertas'       => $cambios->values()->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $archivo;
    }

    /* =====================================================
       APOYO
    ===================================================== */

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

    /** Compara la fila con los valores esperados; las coordenadas, con 6 decimales. */
    private function coincide(object $fila, array $esperado): bool
    {
        foreach (self::CAMPOS as $campo) {
            $a = $fila->{$campo};
            $b = $esperado[$campo] ?? null;

            $iguales = in_array($campo, ['latitude', 'longitude'], true)
                ? ($a === null && $b === null) || ($a !== null && $b !== null && round((float) $a, 6) === round((float) $b, 6))
                : (string) $a === (string) $b && ($a === null) === ($b === null);

            if (!$iguales) {
                return false;
            }
        }

        return true;
    }

    /** Nombre de ciudad sin tildes, en minúsculas y sin espacios sobrantes. */
    private function clave(?string $nombre): string
    {
        return Str::of((string) $nombre)->ascii()->lower()->squish()->toString();
    }

    private function mostrarResumen(Collection $cambios): void
    {
        $this->table(
            ['País anterior', 'País nuevo', 'Ofertas', 'Con coordenadas', 'Sin coordenadas'],
            $cambios
                ->groupBy(fn ($c) => ($c['antes']['country'] ?? '(vacío)') . ' → ' . $c['despues']['country'])
                ->map(fn ($g) => [
                    $g->first()['antes']['country'] ?? '(vacío)',
                    $g->first()['despues']['country'],
                    $g->count(),
                    $g->filter(fn ($c) => $c['despues']['latitude'] !== null)->count(),
                    $g->filter(fn ($c) => $c['despues']['latitude'] === null)->count(),
                ])
                ->sortByDesc(2)
                ->values()
                ->all()
        );

        $this->line("Total: {$cambios->count()} oferta(s).");
    }
}
