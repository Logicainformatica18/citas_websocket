<?php

namespace App\Services\Indicators;

use App\Jobs\RefreshIndicatorsJob;
use App\Services\Indicators\Facts\CareerRankingFacts;
use App\Services\Indicators\Facts\CityDemandFacts;
use App\Services\Indicators\Facts\CompanyFacts;
use App\Services\Indicators\Facts\GeoFacts;
use App\Services\Indicators\Facts\ModalityFacts;
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
        'alignment' => 'job_offer_alignment por tramos de 25 000 ofertas (base del ranking de ciudades de Geo y de la evolución por carrera)',
        'seniority' => 'por semestre',
        'geo' => 'por semestre + regiones',
        'companies' => 'por semestre',
        'modality' => 'por semestre',
        'careers_ranking' => 'Ranking de carreras: por semestre',
        'city_demand' => 'Demanda por ciudad: por año',
        'curricular' => 'PE y CCTC: demanda del año en curso en 4 partes + suma, y entidades con ofertas por fuente',
        'pe' => 'pe:recalculate por carrera activa (año en curso)',
        'seniority_evolution' => 'seniority:backfill-all del año en curso',
        'companies_evolution' => 'companies:backfill-all del año en curso, por mes',
        'careers_evolution' => 'careers:backfill-all del año en curso, por semestre',
        'market_status' => 'Datos Generales: conteos cacheados por semestre',
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
            if ($name === 'alignment') {
                foreach (AlignmentBuilder::chunks() as [$from, $to]) {
                    $steps[] = ['alignment', $from, $to];
                }
                continue;
            }
            $year = (int) now()->year;
            if ($name === 'careers_evolution') {
                foreach (now()->month >= 7 ? ['s1', 's2'] : ['s1'] as $semester) {
                    $steps[] = ['careers_evolution', $year, $semester];
                }
                continue;
            }
            if ($name === 'curricular') {
                for ($i = 0; $i < CurricularDemand::PARTS; $i++) {
                    $steps[] = ['curricular_part', $year, $i];
                }
                $steps[] = ['curricular_finish', $year, null];
                foreach (array_keys(CurricularDemand::SOURCES) as $src) {
                    $steps[] = ['curricular_entities', null, $src];
                }
                continue;
            }
            if ($name === 'seniority_evolution') {
                $steps[] = [$name, $year, null];
                continue;
            }
            if ($name === 'pe') {
                foreach (\Illuminate\Support\Facades\DB::table('careers')->where('active', 1)->orderBy('id')->pluck('id') as $id) {
                    $steps[] = ['pe', (int) $id, $year];
                }
                continue;
            }
            if ($name === 'companies_evolution') {
                for ($m = 1; $m <= (int) now()->month; $m++) {
                    $steps[] = ['companies_evolution', $year, $m];
                }
                continue;
            }
            if ($name === 'city_demand') {
                foreach (CityDemandFacts::years() as $year) {
                    $steps[] = ['city_demand', $year, CityDemandFacts::PERIOD];
                }
                continue;
            }
            foreach (IndicatorFacts::periods() as [$year, $period]) {
                if ($name === 'geo') {
                    foreach (GeoFacts::PARTS as $part) {
                        $steps[] = ['geo', $year, $period, $part];   // un paso por parte (< 60 s)
                    }
                } else {
                    $steps[] = [$name, $year, $period];
                }
            }
            if ($name === 'geo') {
                $steps[] = ['geo_regions', null, null];
            }
        }

        return $steps;
    }

    /** Ejecuta un paso y devuelve un resumen legible. */
    public static function run(array $step): string
    {
        [$name, $year, $period, $part] = $step + [null, null, null, null];

        $result = match ($name) {
            'alignment' => AlignmentBuilder::buildRange($year, $period),   // [desde, hasta) de ids
            'seniority' => SeniorityFacts::refresh($year, $period),
            'geo' => GeoFacts::refresh($year, $period, $part),
            'geo_regions' => GeoFacts::refreshRegions(),
            'companies' => CompanyFacts::refresh($year, $period),
            'modality' => ModalityFacts::refresh($year, $period),
            'careers_ranking' => CareerRankingFacts::refresh($year, $period),
            'city_demand' => CityDemandFacts::refresh($year),
            'curricular_part' => CurricularDemand::warmPart($year, $period),
            'curricular_finish' => CurricularDemand::finish($year),
            'curricular_entities' => CurricularDemand::warmEntities($period),
            'pe' => self::artisan('pe:recalculate', ['career_id' => $year, '--year' => $period]),   // [pe, carrera, año]
            'seniority_evolution' => self::artisan('seniority:backfill-all', ['year' => $year]),
            'companies_evolution' => self::artisan('companies:backfill-all', ['year' => $year, '--month' => $period]),
            'careers_evolution' => self::artisan('careers:backfill-all', ['year' => $year, '--semester' => $period]),
            'market_status' => self::warmMarketStatus($year, $period),
        };

        return json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    private static function artisan(string $command, array $args): array
    {
        $code = \Illuminate\Support\Facades\Artisan::call($command, $args);
        if ($code !== 0) {
            throw new \RuntimeException("{$command} terminó con código {$code}");
        }

        return ['comando' => $command . ' ' . json_encode($args)];
    }

    /** Datos Generales: conteos del modal (JobMarketStatusController y JobMarketStatusBuilder). */
    private static function warmMarketStatus(int $year, string $period): array
    {
        \App\Http\Controllers\Dashboard\JobMarketStatusController::warm([[$year, $period]]);
        \App\Services\JobMarketStatusBuilder::warm($year, $period);

        return ['cache' => 'ok'];
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
