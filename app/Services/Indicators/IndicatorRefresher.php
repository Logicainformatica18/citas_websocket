<?php

namespace App\Services\Indicators;

use App\Jobs\RefreshIndicatorsJob;
use App\Services\Indicators\Facts\SeniorityFacts;

/**
 * Recálculo de los precálculos de los indicadores de análisis, en PASOS cortos (< 60 s cada uno):
 * un paso es [indicador, año, periodo] o un tramo de un proceso largo. indicators:refresh-all los
 * ejecuta seguidos en un proceso; los botones de las páginas los encolan como Jobs encadenados en
 * la cola por defecto (RefreshIndicatorsJob: un paso por Job).
 */
final class IndicatorRefresher
{
    /** Indicadores y su forma de dividirse en pasos. */
    public const INDICATORS = [
        'seniority' => 'periodos',
    ];

    /**
     * Pasos de los indicadores pedidos (todos si $only está vacío), en orden.
     *
     * @return array<int, array{0:string, 1:int|null, 2:string|null}>
     */
    public static function steps(array $only = []): array
    {
        $names = $only ?: array_keys(self::INDICATORS);
        $steps = [];
        foreach ($names as $name) {
            if (! isset(self::INDICATORS[$name])) {
                throw new \InvalidArgumentException("Indicador desconocido: {$name}");
            }
            foreach (IndicatorFacts::periods() as [$year, $period]) {
                $steps[] = [$name, $year, $period];
            }
        }

        return $steps;
    }

    /** Ejecuta un paso y devuelve un resumen legible. */
    public static function run(array $step): string
    {
        [$name, $year, $period] = $step + [null, null, null];

        $result = match ($name) {
            'seniority' => SeniorityFacts::refresh($year, $period),
        };

        return json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /** Encola los pasos de estos indicadores como Jobs encadenados (cola por defecto). */
    public static function queue(array $only): void
    {
        $steps = self::steps($only);
        if ($steps !== []) {
            RefreshIndicatorsJob::dispatch($steps);
        }
    }

    public static function label(array $step): string
    {
        return implode(' ', array_filter($step, fn ($v) => $v !== null));
    }
}
