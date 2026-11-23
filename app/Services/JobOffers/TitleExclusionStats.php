<?php

namespace App\Services\JobOffers;

use Illuminate\Support\Facades\Log;

/**
 * Ofertas nuevas que NO se guardaron porque su título coincide con una exclusión activa
 * (JobOffer::creating). Se acumulan en memoria durante el proceso y se registran en el log al
 * terminar cada comando (scraper) o, fuera de la consola, al terminar la petición/Job.
 */
final class TitleExclusionStats
{
    /** @var array{total:int, by_source:array<string,int>, by_pattern:array<string,int>, examples:array<int,string>} */
    private static array $stats = ['total' => 0, 'by_source' => [], 'by_pattern' => [], 'examples' => []];

    /** Último registro: updateOrCreate() intenta guardar dos veces el mismo modelo (create y luego save). */
    private static ?string $last = null;

    public static function record(?string $source, array $match, ?string $title): void
    {
        $src = $source ?: '(sin fuente)';
        $key = $src . "\0" . $title;
        if ($key === self::$last) {
            return;
        }
        self::$last = $key;
        $key = "#{$match['id']} {$match['pattern']}";
        self::$stats['total']++;
        self::$stats['by_source'][$src] = (self::$stats['by_source'][$src] ?? 0) + 1;
        self::$stats['by_pattern'][$key] = (self::$stats['by_pattern'][$key] ?? 0) + 1;
        if (count(self::$stats['examples']) < 5) {
            self::$stats['examples'][] = mb_substr((string) $title, 0, 120);
        }
    }

    public static function total(): int
    {
        return self::$stats['total'];
    }

    /** Escribe el resumen en el log (si hubo descartes) y reinicia el contador. */
    public static function flush(string $context): ?string
    {
        if (self::$stats['total'] === 0) {
            return null;
        }
        $s = self::$stats;
        arsort($s['by_source']);
        arsort($s['by_pattern']);
        $s['by_pattern'] = array_slice($s['by_pattern'], 0, 15, true);
        Log::info('[TITLE_EXCLUSION] Ofertas nuevas descartadas por título', ['scraper' => $context] + $s);
        self::$stats = ['total' => 0, 'by_source' => [], 'by_pattern' => [], 'examples' => []];
        self::$last = null;

        return "🚫 {$s['total']} oferta(s) nueva(s) descartada(s) por título (rol no TI): "
            . implode(', ', array_map(fn ($k, $v) => "{$k} {$v}", array_keys($s['by_source']), $s['by_source']));
    }
}
