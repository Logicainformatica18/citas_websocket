<?php

namespace App\Support;

use App\Models\JobOffer;

/**
 * Funciones del puesto de una oferta de Get on Board
 * (data.attributes.functions y functions_headline) → columnas de job_offers.
 *
 * Único lugar donde se mapean estos dos campos para todos los scrapers.
 */
final class GetOnBoardFunctions
{
    private const HEADLINE_MAX = 255;

    /** @return array{functions: ?string, functions_headline: ?string} */
    public static function fromAttributes(array $attr): array
    {
        $functions = $attr['functions'] ?? null;
        $headline  = $attr['functions_headline'] ?? null;

        $headline = is_string($headline) ? trim(preg_replace('/\s+/u', ' ', $headline) ?? $headline) : '';

        return [
            'functions'          => HtmlText::toPlain(is_string($functions) ? $functions : null),
            'functions_headline' => $headline === '' ? null : mb_substr($headline, 0, self::HEADLINE_MAX),
        ];
    }

    /**
     * Completa functions / functions_headline de una oferta ya guardada,
     * solo en las columnas que hoy están en null. No pisa nada.
     * Si la oferta queda con functions y sin origen, marca functions_origin = 'field'
     * (en Get on Board las funciones siempre vienen de un campo propio).
     *
     * @return bool true si se actualizó alguna columna
     */
    public static function fillMissing(JobOffer $offer, array $attr): bool
    {
        $changes = [];
        foreach (self::fromAttributes($attr) as $column => $value) {
            if ($value !== null && $offer->getAttribute($column) === null) {
                $changes[$column] = $value;
            }
        }

        $hasFunctions = isset($changes['functions']) || $offer->getAttribute('functions') !== null;
        if ($hasFunctions && $offer->getAttribute('functions_origin') === null) {
            $changes['functions_origin'] = JobFunctions::FIELD;
        }

        if ($changes === []) {
            return false;
        }

        $offer->forceFill($changes)->save();

        return true;
    }
}
