<?php

namespace App\Services;

class SemanticMapService
{
    public static function reachable(string $table, array $map, array &$visited = []): array
    {
        if (isset($visited[$table])) return [];
        $visited[$table] = true;

        $neighbors = array_keys($map[$table] ?? []);
        foreach ($neighbors as $neighbor) {
            $neighbors = array_merge($neighbors, self::reachable($neighbor, $map, $visited));
        }

        return array_unique($neighbors);
    }
}
