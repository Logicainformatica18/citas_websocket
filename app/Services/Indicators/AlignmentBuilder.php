<?php

namespace App\Services\Indicators;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Construye job_offer_alignment por tramos de job_offer_id (jobs:build-alignment y el paso
 * 'alignment' de indicators:refresh-all). Cada tramo, en una transacción, borra sus filas y las
 * vuelve a insertar: (oferta, carrera, fuente, mentions = entidades distintas de esa fuente en los
 * cursos de la carrera). Asociaciones completas (criterio de los indicadores que no son rankings).
 */
final class AlignmentBuilder
{
    /** fuente => [tabla pivote, columna de la entidad, tabla curso–entidad] */
    public const SOURCES = [
        'technology' => ['technology_job', 'technology_id', 'course_technology'],
        'language' => ['language_job', 'language_id', 'course_language'],
        'methodology' => ['methodology_job', 'methodology_id', 'course_methodology'],
    ];

    /** @return array<int, array{0:int, 1:int}> rangos [desde, hasta) de job_offer_id */
    public static function chunks(int $size = 50000): array
    {
        $max = (int) DB::table('job_offers')->max('id');
        $out = [];
        for ($from = 0; $from <= $max; $from += $size) {
            $out[] = [$from, $from + $size];
        }

        return $out;
    }

    /** Reemplaza las filas de las ofertas con id en [$from, $to). Devuelve las filas insertadas. */
    public static function buildRange(int $from, int $to): int
    {
        $hasMentions = Schema::hasColumn('job_offer_alignment', 'mentions');

        return DB::transaction(function () use ($from, $to, $hasMentions) {
            DB::table('job_offer_alignment')->where('job_offer_id', '>=', $from)->where('job_offer_id', '<', $to)->delete();
            $n = 0;
            foreach (self::SOURCES as $source => [$pivot, $col, $courseTable]) {
                $n += DB::affectingStatement('
                    INSERT INTO job_offer_alignment
                        (job_offer_id, career_id, source' . ($hasMentions ? ', mentions' : '') . ', created_at, updated_at)
                    SELECT p.job_offer_id, m.career_id, ?' . ($hasMentions ? ', COUNT(DISTINCT p.' . $col . ')' : '') . ', NOW(), NOW()
                      FROM ' . $pivot . ' p
                      JOIN (SELECT DISTINCT cc.career_id, x.' . $col . ' AS e
                              FROM career_course cc JOIN ' . $courseTable . ' x ON x.course_id = cc.course_id) m
                        ON m.e = p.' . $col . '
                     WHERE p.job_offer_id >= ? AND p.job_offer_id < ?
                     GROUP BY p.job_offer_id, m.career_id
                ', [$source, $from, $to]);
            }

            return $n;
        });
    }
}
