<?php

namespace App\Services\Indicators;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Construye job_offer_alignment por tramos de job_offer_id (jobs:build-alignment y el paso
 * 'alignment' de indicators:refresh-all): (oferta, carrera, fuente, mentions = entidades distintas
 * de esa fuente en los cursos de la carrera). Asociaciones completas (criterio de los indicadores
 * que no son rankings).
 *
 * Cada tramo calcula el conjunto nuevo (oferta, carrera, menciones), inserta o actualiza solo las
 * filas que faltan o cambiaron y borra las que ya no corresponden a ninguna asociación. Antes borraba y
 * reinsertaba todo el tramo: de un día a otro casi nada cambia, y reescribir ~80 000 filas por
 * tramo tardaba hasta 2 min con poca memoria para MySQL.
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
    public static function chunks(int $size = 25000): array
    {
        $max = (int) DB::table('job_offers')->max('id');
        $out = [];
        for ($from = 0; $from <= $max; $from += $size) {
            $out[] = [$from, $from + $size];
        }

        return $out;
    }

    /** Pares (carrera, entidad) de una fuente, sin duplicados (SQL de tabla derivada). */
    private static function careerEntities(string $col, string $courseTable): string
    {
        return "SELECT DISTINCT cc.career_id, x.{$col} AS e FROM career_course cc JOIN {$courseTable} x ON x.course_id = cc.course_id";
    }

    /**
     * Actualiza las filas de las ofertas con id en [$from, $to).
     *
     * @return array{upsert:int, borradas:int}
     */
    public static function buildRange(int $from, int $to): array
    {
        $out = ['upsert' => 0, 'borradas' => 0];

        if (! Schema::hasColumn('job_offer_alignment', 'mentions')) {
            // Sin la migración 2026_11_25_000002: comportamiento anterior (sin menciones)
            DB::transaction(function () use ($from, $to, &$out) {
                $out['borradas'] = DB::table('job_offer_alignment')->where('job_offer_id', '>=', $from)->where('job_offer_id', '<', $to)->delete();
                foreach (self::SOURCES as $source => [$pivot, $col, $courseTable]) {
                    $out['upsert'] += DB::affectingStatement("INSERT IGNORE INTO job_offer_alignment (job_offer_id, career_id, source, created_at, updated_at)
                        SELECT DISTINCT p.job_offer_id, m.career_id, ?, NOW(), NOW() FROM {$pivot} p
                          JOIN (" . self::careerEntities($col, $courseTable) . ") m ON m.e = p.{$col}
                         WHERE p.job_offer_id >= ? AND p.job_offer_id < ?", [$source, $from, $to]);
                }
            });

            return $out;
        }

        foreach (self::SOURCES as $source => [$pivot, $col, $courseTable]) {
            // Conjunto nuevo del tramo: (oferta, carrera, menciones)
            $fresh = "SELECT p.job_offer_id AS o, m.career_id AS c, COUNT(DISTINCT p.{$col}) AS k
                        FROM {$pivot} p
                        JOIN (" . self::careerEntities($col, $courseTable) . ") m ON m.e = p.{$col}
                       WHERE p.job_offer_id >= ? AND p.job_offer_id < ?
                       GROUP BY p.job_offer_id, m.career_id";

            // Solo filas que faltan o cuyas menciones cambiaron (las iguales no se tocan)
            $out['upsert'] += DB::affectingStatement("
                INSERT INTO job_offer_alignment (job_offer_id, career_id, source, mentions, created_at, updated_at)
                SELECT n.o, n.c, ?, n.k, NOW(), NOW()
                  FROM ({$fresh}) n
                  LEFT JOIN job_offer_alignment a ON a.job_offer_id = n.o AND a.career_id = n.c AND a.source = ?
                 WHERE a.job_offer_id IS NULL OR a.mentions <> n.k
                ON DUPLICATE KEY UPDATE mentions = VALUES(mentions)", [$source, $from, $to, $source]);

            // Filas que ya no corresponden a ninguna asociación (asociación o curso quitados)
            $out['borradas'] += DB::affectingStatement("
                DELETE a FROM job_offer_alignment a
                  LEFT JOIN ({$fresh}) n ON n.o = a.job_offer_id AND n.c = a.career_id
                 WHERE a.job_offer_id >= ? AND a.job_offer_id < ? AND a.source = ? AND n.o IS NULL", [$from, $to, $from, $to, $source]);
        }

        return $out;
    }
}
