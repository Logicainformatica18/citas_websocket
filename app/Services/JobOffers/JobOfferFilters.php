<?php

namespace App\Services\JobOffers;

use App\Models\JobOffer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;

/**
 * Filtros del listado /job-offers, compartidos con el export (mismo resultado en ambos).
 *
 *   - selección múltiple: empresa, país, ciudad, fuente, modalidad, tipo, remoto, carga
 *   - fechas de publicación y de registro (rangos semiabiertos: usan los índices)
 *   - periodo: año y semestre con los rangos de exportar_periodos.py
 *       s1 = [año-01-01, año-07-01)   s2 = [año-07-01, año+1-01-01)   solo año = [año-01-01, año+1-01-01)
 *   - excluidas por título: all (todas) | valid (solo válidas) | excluded (solo excluidas)
 *   - clasificación TI (estado único que leen los indicadores): pendiente | en_proceso | ti | no_ti | dudosa
 */
final class JobOfferFilters
{
    public const MULTI = [
        'companies' => 'company',
        'countries' => 'country',
        'cities' => 'city',
        'sources' => 'source',
        'modalities' => 'modality',
        'job_types' => 'job_type',
        'remote_types' => 'remote_type',
        'workloads' => 'workload',
    ];

    public const TI_STATUSES = ['pendiente', 'en_proceso', 'ti', 'no_ti', 'dudosa'];

    public const SCALAR = ['published_from', 'published_to', 'created_from', 'created_to', 'year', 'semester', 'excluded', 'ti_status'];

    /** Normaliza lo que llega por la URL ("null", "", "a,b", arreglos). */
    public static function fromInput(array $input): array
    {
        $f = [];
        foreach (array_keys(self::MULTI) as $key) {
            $v = $input[$key] ?? [];
            if (is_string($v)) {
                $v = $v === 'null' ? [] : explode(',', $v);
            }
            $f[$key] = array_values(array_filter((array) $v, fn ($x) => $x !== null && trim((string) $x) !== '' && $x !== 'null'));
        }
        foreach (self::SCALAR as $key) {
            $v = $input[$key] ?? null;
            $f[$key] = ($v === null || $v === '' || $v === 'null') ? null : (string) $v;
        }
        $f['year'] = $f['year'] !== null && preg_match('/^\d{4}$/', $f['year']) ? $f['year'] : null;
        $f['semester'] = in_array($f['semester'], ['s1', 's2'], true) ? $f['semester'] : null;
        $f['ti_status'] = in_array($f['ti_status'], self::TI_STATUSES, true) ? $f['ti_status'] : null;
        $f['excluded'] = in_array($f['excluded'], ['valid', 'excluded'], true) ? $f['excluded'] : null;
        foreach (['published_from', 'published_to', 'created_from', 'created_to'] as $key) {
            if ($f[$key] !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $f[$key])) {
                $f[$key] = null;
            }
        }

        return $f;
    }

    /** [inicio, fin) del periodo elegido, o null. */
    public static function periodRange(array $f): ?array
    {
        if ($f['year'] === null) {
            return null;
        }
        $y = (int) $f['year'];

        return match ($f['semester']) {
            's1' => ["{$y}-01-01", "{$y}-07-01"],
            's2' => ["{$y}-07-01", ($y + 1) . '-01-01'],
            default => ["{$y}-01-01", ($y + 1) . '-01-01'],
        };
    }

    public static function apply(Builder|EloquentBuilder $query, array $f, string $alias = 'job_offers'): Builder|EloquentBuilder
    {
        $col = fn (string $c) => "{$alias}.{$c}";

        foreach (self::MULTI as $key => $column) {
            if (! empty($f[$key])) {
                $query->whereIn($col($column), $f[$key]);
            }
        }

        // Rangos semiabiertos en lugar de whereDate(): el índice de la columna se puede usar
        $day = fn (string $d) => Carbon::parse($d)->startOfDay()->toDateTimeString();
        $next = fn (string $d) => Carbon::parse($d)->addDay()->startOfDay()->toDateTimeString();
        if ($f['published_from']) {
            $query->where($col('published_at'), '>=', $day($f['published_from']));
        }
        if ($f['published_to']) {
            $query->where($col('published_at'), '<', $next($f['published_to']));
        }
        if ($f['created_from']) {
            $query->where($col('created_at'), '>=', $day($f['created_from']));
        }
        if ($f['created_to']) {
            $query->where($col('created_at'), '<', $next($f['created_to']));
        }

        if ($range = self::periodRange($f)) {
            $query->where($col('published_at'), '>=', $range[0])->where($col('published_at'), '<', $range[1]);
        }

        if ($f['excluded'] && JobOffer::hasTitleExclusionColumn()) {
            $f['excluded'] === 'valid'
                ? $query->whereNull($col('title_exclusion_id'))
                : $query->whereNotNull($col('title_exclusion_id'));
        }

        if ($f['ti_status'] ?? null) {
            $query->where($col('ti_status'), $f['ti_status']);
        }

        return $query;
    }

    /** Solo los filtros con valor (para la URL y para guardar en el proceso de export). */
    public static function compact(array $f): array
    {
        return array_filter($f, fn ($v) => $v !== null && $v !== []);
    }
}
