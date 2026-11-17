<?php

namespace App\Support;

use App\Models\JobOffer;

/**
 * Funciones del puesto → columnas functions / functions_headline / functions_origin de job_offers.
 *
 * Mapeo común para las fuentes distintas de Get on Board (Get on Board usa GetOnBoardFunctions).
 *   - origin 'field':     la fuente trae las funciones en un campo propio (Wantedly, USAJOBS).
 *   - origin 'heuristic': se extrajeron de la descripción con FunctionsSection.
 *
 * Los tres valores van juntos: si functions es null, headline y origin también lo son.
 */
final class JobFunctions
{
    public const FIELD     = 'field';
    public const HEURISTIC = 'heuristic';

    private const HEADLINE_MAX = 255;

    /** @return array{functions: null, functions_headline: null, functions_origin: null} */
    public static function none(): array
    {
        return ['functions' => null, 'functions_headline' => null, 'functions_origin' => null];
    }

    /**
     * Funciones que vienen en un campo propio de la fuente.
     * $content puede ser HTML o texto plano con saltos de línea.
     *
     * @return array{functions: ?string, functions_headline: ?string, functions_origin: ?string}
     */
    public static function fromField(?string $content, ?string $headline): array
    {
        return self::build(HtmlText::toPlain(self::asHtml($content)), $headline, self::FIELD);
    }

    /**
     * Funciones como lista de ítems (p. ej. USAJOBS MajorDuties).
     *
     * @param  array<int, mixed>|string|null  $items
     */
    public static function fromList(array|string|null $items, ?string $headline): array
    {
        if (is_string($items)) {
            return self::fromField($items, $headline);
        }

        $li = [];
        foreach ($items ?? [] as $item) {
            if (is_scalar($item) && trim((string) $item) !== '') {
                $li[] = '<li>' . htmlspecialchars((string) $item, ENT_NOQUOTES, 'UTF-8') . '</li>';
            }
        }

        return $li === [] ? self::none() : self::fromField('<ul>' . implode('', $li) . '</ul>', $headline);
    }

    /**
     * Wantedly: description de /api/v1/projects es la sección "こんなことやります"
     * ("Esto es lo que harás") completa del aviso.
     */
    public static function fromWantedly(array $project): array
    {
        return self::fromField(is_string($project['description'] ?? null) ? $project['description'] : null, 'こんなことやります');
    }

    /** USAJOBS: MatchedObjectDescriptor.UserArea.Details.MajorDuties (lista de funciones). */
    public static function fromUsajobs(array $descriptor): array
    {
        return self::fromList($descriptor['UserArea']['Details']['MajorDuties'] ?? null, 'Major Duties');
    }

    /**
     * Funciones extraídas de la descripción completa con la heurística estricta.
     * Si no hay una sección reconocible devuelve todo en null.
     */
    public static function fromDescription(?string $description): array
    {
        $section = FunctionsSection::extract($description);

        return $section === null
            ? self::none()
            : self::build($section['text'], $section['headline'], self::HEURISTIC);
    }

    /**
     * Completa las funciones de una oferta ya guardada solo si hoy no tiene (functions null).
     * Headline y origin se escriben junto con functions; nunca se pisa nada existente.
     *
     * @param  array{functions: ?string, functions_headline: ?string, functions_origin: ?string}  $values
     * @return bool true si se actualizó la oferta
     */
    public static function fillMissing(JobOffer $offer, array $values): bool
    {
        if (($values['functions'] ?? null) === null || $offer->getAttribute('functions') !== null) {
            return false;
        }

        $changes = ['functions' => $values['functions']];
        foreach (['functions_headline', 'functions_origin'] as $column) {
            if (($values[$column] ?? null) !== null && $offer->getAttribute($column) === null) {
                $changes[$column] = $values[$column];
            }
        }

        $offer->forceFill($changes)->save();

        return true;
    }

    /**
     * Igual que fillMissing, para comandos cuya rama de duplicado no tiene el modelo a mano:
     * busca la oferta por fuente + external_id (solo si aún no tiene functions).
     */
    public static function fillMissingFor(string $source, string|int|null $externalId, array $values): bool
    {
        if ($externalId === null || $externalId === '' || ($values['functions'] ?? null) === null) {
            return false;
        }

        $offer = JobOffer::where('source', $source)
            ->where('external_id', (string) $externalId)
            ->whereNull('functions')
            ->first();

        return $offer !== null && self::fillMissing($offer, $values);
    }

    /**
     * Agrega functions_origin a un mapeo que ya trae functions/functions_headline
     * (lo usan los comandos de Get on Board con GetOnBoardFunctions::fromAttributes()).
     *
     * @param  array{functions: ?string, functions_headline: ?string}  $values
     */
    public static function withOrigin(array $values, string $origin): array
    {
        $values['functions_origin'] = ($values['functions'] ?? null) !== null ? $origin : null;

        return $values;
    }

    /**
     * Texto plano (sin etiquetas) → HTML equivalente, para que HtmlText::toPlain conserve
     * sus saltos de línea. Si ya es HTML se devuelve igual. Greenhouse envía el HTML
     * escapado como entidades (&lt;p&gt;): se decodifica una vez.
     */
    public static function asHtml(?string $content): ?string
    {
        if ($content === null || trim($content) === '') {
            return null;
        }

        $hasTags = static fn (string $s): bool => (bool) preg_match('~<\s*/?\s*[a-z][a-z0-9]*\b[^>]*>~i', $s);

        if (! $hasTags($content) && preg_match('~&lt;\s*/?\s*[a-z]~i', $content)) {
            $decoded = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($hasTags($decoded)) {
                return $decoded;
            }
        }

        if ($hasTags($content)) {
            return $content;
        }

        return nl2br(htmlspecialchars($content, ENT_NOQUOTES, 'UTF-8'), false);
    }

    private static function build(?string $functions, ?string $headline, string $origin): array
    {
        if ($functions === null || trim($functions) === '') {
            return self::none();
        }

        $headline = $headline === null ? '' : trim(preg_replace('/\s+/u', ' ', $headline) ?? $headline);

        return [
            'functions'          => $functions,
            'functions_headline' => $headline === '' ? null : mb_substr($headline, 0, self::HEADLINE_MAX),
            'functions_origin'   => $origin,
        ];
    }
}
