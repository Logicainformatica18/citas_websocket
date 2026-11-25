<?php

namespace App\Services\AiClassification;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fila única de ai_classification_settings (sin variables de entorno, como ExclusionSettings):
 *   enabled                interruptor de emergencia: con 0 los comandos no llaman a la IA (las reglas y la
 *                          herencia por título siguen, porque no cuestan nada)
 *   trends_filter_enabled  los rankings ignoran las tendencias en estado ruido (apagado hasta que la usuaria lo
 *                          active después de ver su efecto)
 *   *_history_max_id       corte: id <= corte es histórico (ai:classify-history); id > corte, nuevo (ai:classify)
 *   history_submitted_at   el operador ejecutó ai:classify-history --submit: --collect sigue enviando lo que falte
 */
final class AiSettings
{
    public const TABLE = 'ai_classification_settings';

    private static ?object $row = null;

    public static function row(): ?object
    {
        if (self::$row === null && Schema::hasTable(self::TABLE)) {
            self::$row = DB::table(self::TABLE)->orderBy('id')->first();
        }

        return self::$row;
    }

    public static function enabled(): bool
    {
        return (bool) (self::row()->enabled ?? false);
    }

    public static function trendsFilterEnabled(): bool
    {
        return (bool) (self::row()->trends_filter_enabled ?? false);
    }

    public static function historyMaxId(string $kind): int
    {
        return (int) (self::row()->{$kind === 'offers' ? 'offers_history_max_id' : 'trends_history_max_id'} ?? 0);
    }

    public static function historySubmittedAt(): ?string
    {
        return self::row()->history_submitted_at ?? null;
    }

    public static function update(array $values, ?string $by = null): void
    {
        DB::table(self::TABLE)->where('id', self::row()->id)->update($values + ['updated_by' => $by, 'updated_at' => now()]);
        self::flush();
    }

    public static function flush(): void
    {
        self::$row = null;
    }
}
