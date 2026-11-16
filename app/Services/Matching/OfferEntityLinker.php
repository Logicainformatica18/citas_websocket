<?php

namespace App\Services\Matching;

use App\Models\JobOffer;
use Illuminate\Support\Facades\DB;

/**
 * Puente entre los scrapers y EntityTextMatcher para un indicador (clave de config/entity_rankings.php).
 *
 *   - Scrapers de feed: match($title, $body) → [catalogId => entityId] de todo lo que menciona la oferta.
 *   - Scrapers de búsqueda: confirms($catalogId, $title, $body) antes de asociar lo que devolvió la API.
 *   - attach($offer, [catalogId, …]) escribe el pivote CON market_entity_id (antes lo completaba
 *     market:fix-null-entities después).
 *
 * Catálogo = filas de languages/technologies/… habilitadas y vinculadas a una entidad activa. Una fila
 * del catálogo sin entidad no se puede confirmar: el scraper no la asocia (criterio estricto).
 * $body debe llevar todo el texto que dio la API (descripción completa, requisitos, etiquetas), no solo
 * lo que se guarda.
 */
final class OfferEntityLinker
{
    /** @var array<string, self> */
    private static array $instances = [];

    private EntityTextMatcher $matcher;

    private string $relation;

    /** @var array<int, int> catalogId => entityId */
    private array $entityByCatalog = [];

    /** @var array<int, int> entityId => catalogId */
    private array $catalogByEntity = [];

    /** @var array{confirmed: int, rejected: int} */
    public array $stats = ['confirmed' => 0, 'rejected' => 0];

    private function __construct(string $type)
    {
        $cfg = config("entity_rankings.{$type}");
        $this->matcher = EntityTextMatcher::for($cfg['entity_type']);
        $this->relation = $cfg['offer_relation'];

        $rows = DB::table($cfg['catalog_table'])
            ->where('enabled', 1)
            ->whereIn('market_entity_id', $this->matcher->entityIds() ?: [0])
            ->orderBy('id')
            ->get(['id', 'market_entity_id']);

        foreach ($rows as $row) {
            $this->entityByCatalog[(int) $row->id] = (int) $row->market_entity_id;
            $this->catalogByEntity[(int) $row->market_entity_id] ??= (int) $row->id;
        }
    }

    public static function for(string $type): self
    {
        return self::$instances[$type] ??= new self($type);
    }

    public static function flush(): void
    {
        self::$instances = [];
        EntityTextMatcher::flush();
    }

    /** Une los textos disponibles de una oferta (null, arreglos y HTML admitidos). */
    public static function body(mixed ...$parts): string
    {
        $out = [];
        foreach ($parts as $p) {
            if (is_array($p)) {
                $p = implode(', ', array_filter(array_map(fn ($x) => is_scalar($x) ? (string) $x : '', $p)));
            }
            if (is_scalar($p) && trim((string) $p) !== '') {
                $out[] = (string) $p;
            }
        }

        return implode("\n", $out);
    }

    public function entityFor(int $catalogId): ?int
    {
        return $this->entityByCatalog[$catalogId] ?? null;
    }

    public function catalogFor(int $entityId): ?int
    {
        return $this->catalogByEntity[$entityId] ?? null;
    }

    public function confirms(?int $catalogId, ?string $title, ?string $body = null): bool
    {
        $entityId = $catalogId ? $this->entityFor($catalogId) : null;
        $ok = $entityId !== null && $this->matcher->confirms($entityId, (string) $title, $body);
        $this->stats[$ok ? 'confirmed' : 'rejected']++;

        return $ok;
    }

    /** Igual que confirms(), por id de entidad (scrapers que recorren market_entities). */
    public function confirmsEntity(int $entityId, ?string $title, ?string $body = null): bool
    {
        $ok = $this->matcher->confirms($entityId, (string) $title, $body);
        $this->stats[$ok ? 'confirmed' : 'rejected']++;

        return $ok;
    }

    /** @return array<int, int> catalogId => entityId de las entidades que menciona la oferta */
    public function match(?string $title, ?string $body = null): array
    {
        $out = [];
        foreach ($this->matcher->match((string) $title, $body) as $entityId => $hit) {
            if ($catalogId = $this->catalogFor($entityId)) {
                $out[$catalogId] = $entityId;
            }
        }

        return $out;
    }

    /** Asocia la oferta a las filas del catálogo indicadas, con su market_entity_id. */
    public function attach(JobOffer $offer, array $catalogIds): void
    {
        $sync = [];
        foreach ($catalogIds as $catalogId) {
            if ($entityId = $this->entityFor((int) $catalogId)) {
                $sync[(int) $catalogId] = ['market_entity_id' => $entityId];
            }
        }
        if ($sync) {
            $offer->{$this->relation}()->syncWithoutDetaching($sync);
        }
    }
}
