<?php

namespace App\Services\AiClassification;

/** Costo en USD con los precios de config/ai_classification.php (Batch API: con descuento). */
final class Cost
{
    public static function usd(string $model, int $in, int $cached, int $out, bool $batch = false): float
    {
        // El nombre del modelo lleva puntos (gpt-4.1-mini): no se puede usar la notación de puntos de config()
        $prices = config('ai_classification.prices', []);
        [$pin, $pcached, $pout] = $prices[$model] ?? $prices['gpt-4.1-mini'];
        $usd = (max(0, $in - $cached) * $pin + $cached * $pcached + $out * $pout) / 1e6;

        return $batch ? $usd * (float) config('ai_classification.batch_discount', 0.5) : $usd;
    }

    /** Tokens aproximados de un texto (≈ 3,5 caracteres por token en español/inglés mezclados). */
    public static function tokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 3.5);
    }
}
