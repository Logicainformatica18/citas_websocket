<?php

namespace App\Services\Catalog;

use App\Models\Language;
use App\Models\Methodology;
use App\Models\Technology;
use App\Models\TechnologyCategory;
use App\Support\CatalogName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Convierte los nombres que devuelve la IA en filas del catálogo sin fragmentarlo
 * (orden de búsqueda y convención en config/catalogo.php).
 *
 * Una instancia por sílabo: guarda en report() lo que no fue coincidencia exacta
 * (normalizado, alias, creado, categoría a 'other') para structured_data['catalogo'].
 */
class CatalogResolver
{
    public const TYPES = [
        'technologies' => Technology::class,
        'languages' => Language::class,
        'methodologies' => Methodology::class,
    ];

    public const OTHER_CATEGORY = 'other';

    /** @var array<string, array<string, Model>> tipo → clave normalizada → fila */
    private array $index = [];

    /** @var array<string, array<string, Model>> tipo → nombre exacto → fila */
    private array $exact = [];

    /** @var array<string, TechnologyCategory>|null */
    private ?array $categories = null;

    private array $report = [];

    /** Fila del catálogo para el nombre recibido, creándola si no existe. Null si el nombre está vacío. */
    public function resolve(string $type, ?string $received): ?Model
    {
        $name = CatalogName::clean($received);
        $key = CatalogName::key($name);
        if ($key === '') {
            return null;
        }

        $index = &$this->index($type);

        // El alias va primero: también redirige nombres que existen duplicados en la base.
        // Se busca el canónico por nombre exacto antes que por clave, porque duplicados como
        // "Virtual Box" y "VirtualBox" comparten la clave y el alias decide cuál se usa.
        $canonical = config("catalogo.alias.$type.$key");
        if ($canonical !== null) {
            $canonicalKey = CatalogName::key($canonical);
            $model = $this->exact[$type][$canonical] ?? $index[$canonicalKey] ?? null;
            if ($model) {
                if ($model->name !== $name) {
                    $this->record($type, $name, $model, 'alias');
                }
                return $model;
            }
            $name = $canonical;
            $key = $canonicalKey;
        } elseif (isset($index[$key])) {
            $model = $index[$key];
            if ($model->name !== $name) {
                $this->record($type, $name, $model, 'normalizado');
            }
            return $model;
        }

        // firstOrCreate y no create: otro worker pudo crearlo después de armar el índice.
        $model = (self::TYPES[$type])::firstOrCreate(['name' => $name]);
        $index[$key] = $model;
        $this->exact[$type][$model->name] = $model;
        $this->record($type, (string) $received, $model, $model->wasRecentlyCreated ? 'creado' : 'normalizado');

        if ($model->wasRecentlyCreated) {
            Log::channel('catalogo')->info('Entidad nueva desde un sílabo', [
                'tipo' => $type,
                'id' => $model->id,
                'nombre' => $model->name,
                'recibido' => $received,
                'por_alias' => $canonical !== null,
            ]);
        }

        return $model;
    }

    /**
     * Categoría existente para el tipo que devolvió la IA (normalizado o por alias).
     * Nunca crea categorías: si no existe, 'other'. Null solo si 'other' tampoco existe.
     */
    public function resolveCategory(?string $received): ?TechnologyCategory
    {
        $categories = $this->categories();
        $key = CatalogName::key($received);
        if ($key === '') {
            return null;
        }

        // El alias va primero: así 'metodology' (que existe duplicada) se lleva a 'methodology'.
        $canonical = config("catalogo.alias.categories.$key");
        if ($canonical !== null && isset($categories[CatalogName::key($canonical)])) {
            $category = $categories[CatalogName::key($canonical)];
            if ($category->name !== $received) {
                $this->record('categories', (string) $received, $category, 'alias');
            }
            return $category;
        }

        if (isset($categories[$key])) {
            return $categories[$key];
        }

        $other = $categories[CatalogName::key(self::OTHER_CATEGORY)] ?? null;
        if ($other) {
            $this->record('categories', (string) $received, $other, 'other');
        }
        Log::channel('catalogo')->info('Categoría inexistente asignada a other', [
            'recibido' => $received,
            'other_id' => $other?->id,
        ]);

        return $other;
    }

    /** Lo que no fue coincidencia exacta, agrupado por tipo, para structured_data['catalogo']. */
    public function report(): array
    {
        return $this->report;
    }

    private function record(string $type, string $received, Model $model, string $via): void
    {
        $this->report[$type][] = ['recibido' => $received, 'id' => $model->id, 'nombre' => $model->name, 'via' => $via];
    }

    /**
     * Índice clave → fila. Si dos filas comparten clave (p. ej. "Unity 3D" y "Unity3D")
     * gana la ligada a market_entities y, si no, la de id menor; un alias puede elegir
     * otra por nombre exacto (ver resolve()).
     */
    private function &index(string $type): array
    {
        if (!isset($this->index[$type])) {
            $class = self::TYPES[$type] ?? throw new \InvalidArgumentException("Tipo de catálogo desconocido: $type");
            $hasMarket = self::hasMarketEntity((new $class)->getTable());

            // Filas completas: quien las usa puede leer y guardar otras columnas (category_id).
            $this->index[$type] = [];
            $this->exact[$type] = [];
            foreach ($class::query()->orderBy('id')->get() as $row) {
                $this->exact[$type][$row->name] ??= $row;
                $key = CatalogName::key($row->name);
                $current = $this->index[$type][$key] ?? null;
                if (!$current || ($hasMarket && !$current->market_entity_id && $row->market_entity_id)) {
                    $this->index[$type][$key] = $row;
                }
            }
        }

        return $this->index[$type];
    }

    /** @return array<string, TechnologyCategory> */
    private function categories(): array
    {
        if ($this->categories === null) {
            $this->categories = [];
            foreach (TechnologyCategory::orderBy('id')->get(['id', 'name']) as $category) {
                $this->categories[CatalogName::key($category->name)] ??= $category;
            }
        }

        return $this->categories;
    }

    /** Las tablas del catálogo tienen market_entity_id en la base real; en la de tests, no. */
    public static function hasMarketEntity(string $table): bool
    {
        static $cache = [];
        return $cache[$table] ??= Schema::hasColumn($table, 'market_entity_id');
    }
}
