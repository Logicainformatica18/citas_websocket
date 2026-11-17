<?php

namespace App\Support;

/**
 * Convierte HTML a texto plano conservando la estructura de párrafos y listas.
 *
 * A diferencia de OfferEntityLinker::plain() (todo en una línea, pensado para
 * el matcher), aquí cada párrafo, salto de línea o elemento de lista queda en
 * su propia línea y los <li> se prefijan con "- ".
 */
final class HtmlText
{
    /** Devuelve null si la entrada está vacía o queda vacía tras limpiar. */
    public static function toPlain(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $text = str_replace(["\r\n", "\r"], "\n", $html);

        // Saltos de línea del HTML original no significan nada: se tratan como espacio.
        $text = str_replace("\n", ' ', $text);

        // Estructura → saltos de línea / viñetas
        $text = preg_replace('~<\s*br\s*/?\s*>~iu', "\n", $text) ?? $text;
        $text = preg_replace('~<\s*/\s*(p|li|div|h[1-6])\s*>~iu', "\n", $text) ?? $text;
        $text = preg_replace('~<\s*li\b[^>]*>~iu', "\n- ", $text) ?? $text;

        // Resto de etiquetas fuera: las de formato en línea sin dejar rastro
        // ("<strong>Jira</strong>." → "Jira."); las demás con un espacio para no pegar palabras.
        $text = preg_replace('~<\s*/?\s*(strong|b|em|i|u|s|span|a|code|small|sup|sub|mark)\b[^>]*>~iu', '', $text) ?? $text;
        $text = preg_replace('~<[^>]*>~u', ' ', $text) ?? $text;

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Caracteres de ancho cero y espacios no separables
        $text = preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $text) ?? $text;
        $text = preg_replace('/[\x{00A0}\x{202F}]/u', ' ', $text) ?? $text;

        // Espacios repetidos dentro de cada línea; líneas vacías fuera
        $lines = [];
        foreach (explode("\n", $text) as $line) {
            $line = trim(preg_replace('/[ \t]+/u', ' ', $line) ?? $line);
            if ($line !== '' && $line !== '-') {
                $lines[] = $line;
            }
        }

        return $lines === [] ? null : implode("\n", $lines);
    }
}
