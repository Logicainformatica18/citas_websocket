<?php

namespace App\Helpers;

use Illuminate\Support\Collection;

class DataSanitizer
{
    /**
     * 🧹 Limpia y normaliza los resultados de cualquier consulta SQL.
     *
     * - Quita espacios de strings.
     * - Convierte '0', '', 'null' y 0 numérico a null (solo en campos no numéricos).
     * - Aplica reglas personalizadas por campo si existen.
     * - Mantiene filas incluso si contienen valores vacíos.
     */
    public static function cleanCollection(Collection|array $data, array $rules = []): Collection
    {
        return collect($data)->map(function ($row) use ($rules) {
            $r = (array) $row;

            foreach ($r as $key => $value) {
                // ⚙️ 1️⃣ Ignorar valores complejos (arrays u objetos)
                if (is_array($value) || is_object($value)) {
                    $r[$key] = json_decode(json_encode($value), true);
                    continue;
                }

                // 🧼 2️⃣ Limpieza de strings
                if (is_string($value)) {
                    $value = trim($value);
                    if ($value === '' || strtolower($value) === 'null' || $value === '0') {
                        $value = null;
                    }
                }

                // 🔥 3️⃣ Convertir 0 numérico a null (solo en campos no numéricos)
                if (
                    is_numeric($value)
                    && (int)$value === 0
                    && !str_contains($key, 'id')
                    && !str_contains($key, 'count')
                    && !str_contains($key, 'salary')
                ) {
                    $value = null;
                }

                // 🎯 4️⃣ Aplicar reglas personalizadas (si existen)
                if (isset($rules[$key]) && is_callable($rules[$key])) {
                    $value = $rules[$key]($value);
                }

                $r[$key] = $value;
            }

            return $r;
        })->values();
    }
}
