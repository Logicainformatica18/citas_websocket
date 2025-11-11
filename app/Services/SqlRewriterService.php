<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SqlRewriterService
{
    public static function applyFilters(string $sql, array $filters): array
    {
        // 1️⃣ Detectar tablas mencionadas
        $tables = [];
        if (preg_match_all('/FROM\s+(\w+)|JOIN\s+(\w+)/i', $sql, $matches)) {
            $tables = array_filter(array_merge($matches[1], $matches[2]));
        }

        $jobDomainTables = ['job_offers', 'language_metrics', 'technology_metrics', 'methodology_metrics'];
        $shouldFilter = count(array_intersect($tables, $jobDomainTables)) > 0;

        // 2️⃣ Si no pertenece al dominio laboral → no modificar
        if (!$shouldFilter) {
            Log::info('🟡 SQL fuera del dominio laboral, no se aplican filtros', ['tables' => $tables]);
            return self::executeSafe($sql);
        }

        // 3️⃣ Armar filtros laborales dinámicos
        $wheres = [];

        if (!empty($filters['country'])) {
            $wheres[] = "j.country = '" . addslashes($filters['country']) . "'";
        }
        if (!empty($filters['modality'])) {
            $wheres[] = "j.modality IN ('" . implode("','", array_map('addslashes', (array)$filters['modality'])) . "')";
        }
        if (!empty($filters['experience_level'])) {
            $wheres[] = "j.experience_level IN ('" . implode("','", array_map('addslashes', (array)$filters['experience_level'])) . "')";
        }
        if (!empty($filters['currency'])) {
            $wheres[] = "j.currency IN ('" . implode("','", array_map('addslashes', (array)$filters['currency'])) . "')";
        }

        $whereClause = !empty($wheres) ? ' WHERE ' . implode(' AND ', $wheres) : '';

        $isMetrics = preg_match('/language_metrics|technology_metrics|methodology_metrics/i', $sql);
        $hasJobOffers = preg_match('/\bjob_offers\b/i', $sql);

        $rewritten = $sql;

        // 4️⃣ Si ya usa job_offers directamente, solo agregar filtros
        if ($hasJobOffers) {
            if (!empty($wheres)) {
                $rewritten = self::injectWhere($sql, $whereClause);
            }
            return self::executeSafe($rewritten);
        }

        // 5️⃣ Si es una métrica, verificar si se puede cruzar con job_offers
        if ($isMetrics) {
            // Detectar si hay campo compatible
            $joinField = self::detectJoinableField($tables);

            if (!$joinField) {
                // ❌ No se puede cruzar
                Log::warning('⚠️ No se puede unir la métrica con job_offers (sin campo común)', [
                    'tables' => $tables,
                    'filters' => $filters,
                ]);
                return self::executeSafe($sql);
            }

            // ✅ Hay campo común
            $joinClause = "JOIN job_offers j ON j.$joinField = lm.$joinField";
            $rewritten = self::injectJoinAndWhere($sql, $joinClause, $whereClause);
            return self::executeSafe($rewritten);
        }

        // 6️⃣ Caso genérico
        return self::executeSafe($sql);
    }

    // ==============================================================
    // 🔹 Detectar si hay campo común entre métricas y job_offers
    // ==============================================================
    private static function detectJoinableField(array $tables): ?string
    {
        $possible = ['language_id', 'technology_id', 'methodology_id'];

        try {
            $columns = DB::select("SHOW COLUMNS FROM job_offers");
            $cols = array_map(fn($c) => $c->Field, $columns);
            foreach ($possible as $f) {
                if (in_array($f, $cols)) {
                    return $f;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo verificar columnas de job_offers', ['error' => $e->getMessage()]);
        }

        return null;
    }

    // ==============================================================
    // 🔹 Inyecta JOIN y WHERE en el lugar correcto
    // ==============================================================
    private static function injectJoinAndWhere(string $sql, string $join, string $where): string
    {
        if (preg_match('/\b(GROUP BY|ORDER BY|LIMIT)\b/i', $sql, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];
            $before = substr($sql, 0, $pos);
            $after = substr($sql, $pos);
            return trim("$before $join $where $after");
        }
        return trim("$sql $join $where");
    }

    // ==============================================================
    // 🔹 Inyecta WHERE sin tocar el resto
    // ==============================================================
    private static function injectWhere(string $sql, string $where): string
    {
        if (preg_match('/\b(GROUP BY|ORDER BY|LIMIT)\b/i', $sql, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];
            $before = substr($sql, 0, $pos);
            $after = substr($sql, $pos);
            return trim("$before $where $after");
        }
        return trim("$sql $where");
    }

    // ==============================================================
    // 🔹 Ejecutar query segura con manejo de errores
    // ==============================================================
    private static function executeSafe(string $sql): array
    {
        try {
            $rows = DB::select($sql);
            return [
                'error' => false,
                'final_sql' => $sql,
                'rows' => $rows,
            ];
        } catch (\Throwable $e) {
            Log::error('💥 SqlRewriterService Error', [
                'message' => $e->getMessage(),
                'sql' => $sql,
            ]);
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'final_sql' => $sql,
                'rows' => [],
            ];
        }
    }
}
