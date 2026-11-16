<?php

namespace App\Services\Ranking;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de la exclusión de asociaciones (sin variables de entorno): fila única id = 1 de
 * entity_association_exclusion_settings.
 *   enabled      1 = los rankings ignoran los pares de entity_association_exclusions (por defecto)
 *                0 = apagado de emergencia (php artisan entities:exclusions --disable)
 *   trust_since  fecha y hora de la primera ejecución de entities:exclude-associations; las
 *                asociaciones creadas desde entonces no se vuelven a evaluar.
 * Si la tabla de exclusiones no existe, el filtro se omite. Si falta la fila de configuración,
 * la exclusión se considera ACTIVA (comportamiento por defecto).
 */
final class ExclusionSettings
{
    public const TABLE = 'entity_association_exclusions';

    public const SETTINGS_TABLE = 'entity_association_exclusion_settings';

    /** ¿Se aplican las exclusiones al calcular? */
    public static function active(): bool
    {
        if (! Schema::hasTable(self::TABLE)) {
            return false;
        }

        return (bool) (self::row()->enabled ?? true);
    }

    public static function enabled(): bool
    {
        return (bool) (self::row()->enabled ?? true);
    }

    public static function trustSince(): ?string
    {
        return self::row()->trust_since ?? null;
    }

    public static function setEnabled(bool $enabled): void
    {
        self::ensureRow();
        DB::table(self::SETTINGS_TABLE)->where('id', 1)->update(['enabled' => $enabled ? 1 : 0, 'updated_at' => now()]);
    }

    /** Guarda la fecha de confianza solo si aún no hay una (primera ejecución del comando). */
    public static function initTrustSince(string $datetime): bool
    {
        self::ensureRow();

        return DB::table(self::SETTINGS_TABLE)->where('id', 1)->whereNull('trust_since')
            ->update(['trust_since' => $datetime, 'updated_at' => now()]) > 0;
    }

    public static function settingsTableExists(): bool
    {
        return Schema::hasTable(self::SETTINGS_TABLE);
    }

    private static function row(): ?object
    {
        return self::settingsTableExists() ? DB::table(self::SETTINGS_TABLE)->where('id', 1)->first() : null;
    }

    private static function ensureRow(): void
    {
        DB::statement('INSERT INTO ' . self::SETTINGS_TABLE . ' (id, enabled, trust_since, updated_at)
            SELECT 1, 1, NULL, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM ' . self::SETTINGS_TABLE . ' WHERE id = 1)');
    }
}
