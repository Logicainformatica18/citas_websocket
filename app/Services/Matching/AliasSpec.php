<?php

namespace App\Services\Matching;

/**
 * Lee los archivos de reglas de database/data/entity_aliases/{tipo}.php
 * ([kind, pattern, opciones] por slug) y los convierte en filas de market_entity_aliases.
 */
final class AliasSpec
{
    /** @return array<string, list<array>> slug => reglas tal como están en el archivo */
    public static function load(string $file): array
    {
        $path = base_path("database/data/entity_aliases/{$file}.php");

        return is_file($path) ? require $path : [];
    }

    /**
     * @param  list<array{0: string, 1: string, 2?: array}>  $rules
     * @return list<array{kind: string, pattern: string, case_sensitive: int, is_regex: int, is_weak: int, window_chars: ?int}>
     */
    public static function toRows(array $rules): array
    {
        return array_map(fn ($r) => [
            'kind' => $r[0],
            'pattern' => $r[1],
            'case_sensitive' => (int) ! empty($r[2]['cs']),
            'is_regex' => (int) ! empty($r[2]['regex']),
            'is_weak' => (int) ! empty($r[2]['weak']),
            'window_chars' => isset($r[2]['window']) ? (int) $r[2]['window'] : null,
        ], $rules);
    }
}
