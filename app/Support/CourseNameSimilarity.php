<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Parecido entre el nombre de un curso de silabos_v2 y los cursos del observatorio.
 * Es el mismo cálculo del reporte docs/correspondencia-cursos.csv: nombres en minúsculas,
 * sin tildes ni signos, comparados con similar_text; menos del 60 % no se sugiere.
 */
class CourseNameSimilarity
{
    public const MIN_PERCENT = 60;

    public static function normalize(?string $name): string
    {
        $s = mb_strtolower(trim((string) $name), 'UTF-8');
        $s = preg_replace('/\(duplicado\)/u', '', $s);
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $s));
    }

    public static function percent(?string $a, ?string $b): int
    {
        similar_text(self::normalize($a), self::normalize($b), $pct);

        return (int) round($pct);
    }

    /**
     * El curso más parecido de la lista (objetos con ->id y ->name), si llega al mínimo.
     *
     * @return array{course_id: int, similarity: int}|null
     */
    public static function suggest(?string $name, Collection $courses): ?array
    {
        $best = null;
        foreach ($courses as $course) {
            $pct = self::percent($name, $course->name);
            if ($pct >= self::MIN_PERCENT && (!$best || $pct > $best['similarity'])) {
                $best = ['course_id' => $course->id, 'similarity' => $pct];
            }
        }

        return $best;
    }
}
