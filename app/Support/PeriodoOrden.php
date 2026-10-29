<?php

namespace App\Support;

/**
 * Convierte el periodo de silabos_v2 en un número comparable.
 *
 * silabos_v2 mezcla formatos: semestral ("202510"), anual ("2025"), y sin fecha ("TBD", "TBD_EE").
 *  - "202510" → 202510
 *  - "2025"   → 202500 (un periodo anual va antes de los semestres de ese año)
 *  - "TBD*"   → 999999 o 0, según config('silabos_v2.tbd_position')
 *  - otro     → 0 (el llamador lo registra para revisión)
 *
 * Solo se usa para desempatar sílabos con la misma versión.
 */
class PeriodoOrden
{
    public const MAX = 999999;

    public static function from(?string $periodo): int
    {
        $periodo = strtoupper(trim((string) $periodo));

        if (preg_match('/^\d{6}$/', $periodo)) {
            return (int) $periodo;
        }

        if (preg_match('/^\d{4}$/', $periodo)) {
            return (int) $periodo * 100;
        }

        if (str_starts_with($periodo, 'TBD')) {
            return config('silabos_v2.tbd_position') === 'first' ? 0 : self::MAX;
        }

        return 0;
    }

    public static function isTbd(?string $periodo): bool
    {
        return str_starts_with(strtoupper(trim((string) $periodo)), 'TBD');
    }

    public static function isKnownFormat(?string $periodo): bool
    {
        return (bool) preg_match('/^(\d{6}|\d{4}|TBD.*)$/i', trim((string) $periodo));
    }
}
