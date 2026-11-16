<?php

namespace App\Services\Matching;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Términos de búsqueda de una certificación para las APIs de empleo.
 *
 * certifications.keywords es JSON (["CISSP", "Certified Information Systems Security Professional"]).
 * Varios scrapers lo leían como texto plano y enviaban a la API el literal ["…"]. Aquí se decodifica;
 * si está vacío o es inválido se usan el nombre de la certificación y sus alias fuertes del matcher
 * (market_entity_aliases: kind = alias, sin regex, no débiles).
 *
 * El primer término es el principal (el que se envía cuando la API admite un solo término por llamada).
 * Buscar NO asocia: la asociación la sigue decidiendo el matcher (OfferEntityLinker).
 */
final class SearchTerms
{
    /** @var array<int, list<string>> alias fuertes por entidad (una consulta por proceso) */
    private static ?array $aliases = null;

    /** @var array<int, object> filas de certifications por id y por market_entity_id */
    private static array $catalogById = [];

    private static array $catalogByEntity = [];

    /** Decodifica keywords: JSON (arreglo o texto), arreglo PHP o el formato antiguo separado por comas. */
    public static function decode(mixed $keywords): array
    {
        if (is_string($keywords)) {
            $trim = trim($keywords);
            $json = json_decode($trim, true);
            if (is_array($json)) {
                $keywords = $json;
            } elseif (is_string($json)) {
                $keywords = [$json];
            } else {
                $keywords = $trim === '' ? [] : explode(',', $trim);
            }
        }
        if (! is_array($keywords)) {
            return [];
        }

        return self::clean(array_filter($keywords, 'is_scalar'));
    }

    /** @return list<string> términos (el primero es el principal); nunca vacío si hay nombre */
    public static function forCertification(mixed $keywords, string $name, ?int $entityId = null): array
    {
        $terms = self::decode($keywords);
        if ($terms === []) {
            $terms = self::clean([$name, ...($entityId ? self::aliasesOf($entityId) : [])]);
        }

        return $terms ?: self::clean([$name]);
    }

    /** Por fila de catálogo (certifications.id). */
    public static function forCatalog(int $certificationId, string $name): array
    {
        $row = self::$catalogById[$certificationId]
            ??= DB::table('certifications')->where('id', $certificationId)->first(['id', 'keywords', 'market_entity_id']);

        return self::forCertification($row->keywords ?? null, $name, $row->market_entity_id ?? null);
    }

    /** Por entidad (market_entities.id), para los scrapers que recorren entidades. */
    public static function forEntity(int $entityId, string $name): array
    {
        $row = self::$catalogByEntity[$entityId] ??= DB::table('certifications')
            ->where('market_entity_id', $entityId)->where('enabled', 1)->orderBy('id')->first(['id', 'keywords']);

        return self::forCertification($row->keywords ?? null, $name, $entityId);
    }

    /** Término principal. */
    public static function primary(array $terms): string
    {
        return $terms[0] ?? '';
    }

    private static function clean(array $terms): array
    {
        $out = [];
        foreach ($terms as $t) {
            $t = trim(preg_replace('/\s+/u', ' ', (string) $t) ?? '');
            if (mb_strlen($t) >= 2 && ! isset($out[mb_strtolower($t)])) {
                $out[mb_strtolower($t)] = $t;
            }
        }

        return array_values($out);
    }

    private static function aliasesOf(int $entityId): array
    {
        if (self::$aliases === null) {
            self::$aliases = [];
            if (Schema::hasTable('market_entity_aliases')) {
                foreach (DB::table('market_entity_aliases')->where('kind', 'alias')->where('is_regex', 0)
                    ->where('is_weak', 0)->orderBy('id')->get(['market_entity_id', 'pattern']) as $r) {
                    self::$aliases[$r->market_entity_id][] = $r->pattern;
                }
            }
        }

        return self::$aliases[$entityId] ?? [];
    }
}
