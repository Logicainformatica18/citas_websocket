<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\JobMarketStatusBuilder;
use App\Support\PerformanceTimer;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/**
 * Indicador de Demanda Laboral Geográfica.
 *
 * Optimización sin índices nuevos:
 * - Las tres consultas de carreras (activas, líder y lista) se resuelven con una sola consulta.
 * - Los KPIs de ciudad y el ranking salen de una sola consulta agrupada.
 * - Los resultados se guardan en caché por filtros; la caché se invalida al reconstruir la alineación.
 */
class JobDemandGeoIndicatorController extends Controller
{
    /** Tiempo de vida de la caché del indicador, en segundos (1 hora). */
    private const CACHE_TTL = 3600;

    /* =====================================================
       Helper: SQL para normalizar región
    ===================================================== */
    private function normalizedRegionSql(): string
    {
        return "
            CASE
                WHEN region IS NULL OR region = '' THEN 'Desconocido'

                WHEN UPPER(region) IN ('AFRICA') THEN 'África'
                WHEN UPPER(region) IN ('ASIA') THEN 'Asia'
                WHEN UPPER(region) IN ('EUROPE', 'EUROPA') THEN 'Europa'

                WHEN UPPER(region) IN (
                    'LATAM',
                    'LATINOAMERICA',
                    'LATINOAMÉRICA',
                    'LATIN AMERICA'
                ) THEN 'Latinoamérica'

                WHEN UPPER(region) IN (
                    'NORTH_AMERICA',
                    'NORTEAMERICA',
                    'NORTEAMÉRICA',
                    'NORTH AMERICA'
                ) THEN 'Norteamérica'

                WHEN UPPER(region) IN ('OCEANIA') THEN 'Oceanía'
                WHEN UPPER(region) IN ('GLOBAL', 'WORLDWIDE') THEN 'Global'
                WHEN UPPER(region) IN ('REMOTE', 'REMOTO') THEN 'Remoto'

                ELSE 'Desconocido'
            END
        ";
    }

    /* =====================================================
       Helper: rango de fechas del periodo
    ===================================================== */
    private function periodRange(int $year, string $period): array
    {
        return $period === 's1'
            ? [$year . '-01-01', $year . '-06-30']
            : [$year . '-07-01', $year . '-12-31'];
    }

    /* =====================================================
       Helper: clave de caché con versión
       Al incrementar la versión se invalida toda la caché del indicador.
    ===================================================== */
    private function cacheKey(string $name, array $params = []): string
    {
        $version = Cache::get('geo_indicator:version', 1);

        return 'geo_indicator:v' . $version . ':' . $name . ':' . md5(json_encode($params));
    }

    /* =====================================================
       Helper: filtros recibidos, para la instrumentación
    ===================================================== */
    private function receivedFilters(Request $request): array
    {
        return $request->only([
            'year', 'period', 'region', 'country', 'career_id', 'per_page', 'page', 'zoom',
        ]);
    }

    /* =====================================================
       Demanda por carrera (una sola consulta para los tres usos)
       Reemplaza careers_with_demand, top_career y careers_list.
       Depende solo del periodo, por eso se cachea por rango.
    ===================================================== */
    private function careerDemand(array $range): Collection
    {
        return Cache::remember(
            $this->cacheKey('career_demand', $range),
            self::CACHE_TTL,
            function () use ($range) {
                // Mapa carrera → tecnología sin duplicados (tablas pequeñas).
                // Evita multiplicar filas cuando una tecnología aparece en varios cursos de la misma carrera.
                $careerTechnology = DB::table('career_course as cc')
                    ->join('course_technology as ct', 'ct.course_id', '=', 'cc.course_id')
                    ->select('cc.career_id', 'ct.technology_id')
                    ->distinct();

                // Se parte de las ofertas del periodo y se llega a sus tecnologías
                // por el índice existente technology_job(job_offer_id).
                return DB::table('job_offers as jo')
                    ->join('technology_job as tj', 'tj.job_offer_id', '=', 'jo.id')
                    ->joinSub($careerTechnology, 'm', function ($join) {
                        $join->on('m.technology_id', '=', 'tj.technology_id');
                    })
                    ->join('careers as c', 'c.id', '=', 'm.career_id')
                    ->whereBetween('jo.published_at', $range)
                    ->groupBy('c.id', 'c.name')
                    ->select(
                        'c.id',
                        'c.name',
                        DB::raw('COUNT(DISTINCT jo.id) as total_jobs')
                    )
                    ->orderByDesc('total_jobs')
                    ->get();
            }
        );
    }

    /* =====================================================
       Lista de regiones normalizadas (no depende de filtros)
    ===================================================== */
    private function regionList(): Collection
    {
        return Cache::remember(
            $this->cacheKey('regions'),
            self::CACHE_TTL,
            function () {
                $regionSql = $this->normalizedRegionSql();

                return DB::table('job_offers')
                    ->selectRaw("$regionSql as region")
                    ->whereRaw("$regionSql <> 'Desconocido'")
                    ->distinct()
                    ->orderBy('region')
                    ->pluck('region');
            }
        );
    }

    public function index(Request $request)
    {
        $timer = PerformanceTimer::begin('job-demand-geo.index', $this->receivedFilters($request));

        try {
            $response = $this->buildIndex($request, $timer);
        } catch (Throwable $e) {
            $timer->fail($e);
            $timer->finish();
            throw $e;
        }

        $timer->finish();

        return $response;
    }

    private function buildIndex(Request $request, PerformanceTimer $timer)
    {
        /* =====================================================
           1️⃣ Filtros
        ===================================================== */
        $year     = (int) $request->input('year', 2026);
        $period   = $request->input('period', 's1');
        $region   = $request->input('region');
        $country  = $request->input('country');
        $careerId = $request->input('career_id');

        $range     = $this->periodRange($year, $period);
        $regionSql = $this->normalizedRegionSql();

        $filters = [
            'year'      => $year,
            'period'    => $period,
            'region'    => $region,
            'country'   => $country,
            'career_id' => $careerId,
        ];

        /* =====================================================
           2️⃣ Estado del mercado laboral (cacheado por periodo)
        ===================================================== */
        $jobMarketStatus = $timer->measure('job_market_status', fn () => Cache::remember(
            $this->cacheKey('job_market_status', [$year, $period]),
            self::CACHE_TTL,
            fn () => JobMarketStatusBuilder::build([
                'mode'   => 'market',
                'year'   => $year,
                'period' => $period,
            ])
        ));

        /* =====================================================
           3️⃣ Agregado por ciudad (una sola consulta)
           Excluye regiones y ciudades desconocidas. De este resultado
           salen los KPIs de ciudad y el ranking paginado.
        ===================================================== */
        $cityRows = $timer->measure('city_rows', fn () => Cache::remember(
            $this->cacheKey('city_rows', $filters),
            self::CACHE_TTL,
            function () use ($range, $regionSql, $careerId, $region, $country) {
                $query = DB::table('job_offers as jo')
                    ->join('job_offer_alignment as a', 'a.job_offer_id', '=', 'jo.id')
                    ->whereBetween('jo.published_at', $range)
                    ->whereNotNull('jo.city')
                    ->whereRaw("$regionSql <> 'Desconocido'")
                    ->where('jo.city', '<>', 'Desconocido');

                if ($careerId) {
                    $query->where('a.career_id', $careerId);
                }

                if ($region) {
                    $query->whereRaw("$regionSql = ?", [$region]);
                }

                if ($country) {
                    $query->where('jo.country', $country);
                }

                return $query
                    ->select(
                        'jo.city',
                        'jo.region',
                        'jo.country',
                        DB::raw('COUNT(jo.id) as total_jobs')
                    )
                    ->groupBy('jo.city', 'jo.region', 'jo.country')
                    ->orderByDesc('total_jobs')
                    ->get();
            }
        ));

        /* =====================================================
           4️⃣ KPIs de ciudad (calculados en memoria)
        ===================================================== */
        $totalJobs = (int) $cityRows->sum('total_jobs');

        // Totales por ciudad (una ciudad puede venir en varias filas por región o país)
        $cityTotals = $cityRows
            ->groupBy('city')
            ->map(fn ($rows) => (int) $rows->sum('total_jobs'))
            ->sortDesc();

        $citiesCount = $cityTotals->count();
        $topCity     = $cityTotals->keys()->first();
        $top5Jobs    = (int) $cityTotals->take(5)->sum();

        $top5Concentration = $totalJobs > 0
            ? round(($top5Jobs / $totalJobs) * 100, 1)
            : 0;

        /* =====================================================
           5️⃣ KPIs de carreras (una sola consulta cacheada)
        ===================================================== */
        $careers           = $timer->measure('career_demand', fn () => $this->careerDemand($range));
        $careersWithDemand = $careers->count();
        $topCareer         = $careers->first();

        /* =====================================================
           6️⃣ Ranking de ciudades (paginado en memoria)
           Mantiene la misma estructura que paginate()->withQueryString().
        ===================================================== */
        $perPage     = max(1, (int) $request->input('per_page', 10));
        $currentPage = max(1, (int) $request->input('page', 1));

        $pageItems = $cityRows
            ->slice(($currentPage - 1) * $perPage, $perPage)
            ->values()
            ->map(function ($row) use ($totalJobs) {
                $item = clone $row;
                $item->percentage = $totalJobs > 0
                    ? round(($item->total_jobs / $totalJobs) * 100, 1)
                    : 0;

                return $item;
            });

        $ranking = new LengthAwarePaginator(
            $pageItems,
            $cityRows->count(),
            $perPage,
            $currentPage,
            [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]
        );

        /* =====================================================
           7️⃣ Meta
        ===================================================== */
        $meta = [
            'year'               => $year,
            'period'             => $period,
            'periodo_label'      => strtoupper($period) . ' ' . $year,
            'total_jobs'         => $totalJobs,
            'cities_count'       => $citiesCount,
            'careers_count'      => $careersWithDemand,
            'top_city'           => $topCity,
            'top_career'         => $topCareer?->name,
            'top5_concentration' => $top5Concentration,
        ];

        /* =====================================================
           8️⃣ Render
        ===================================================== */
        $regions = $timer->measure('regions', fn () => $this->regionList());

        return Inertia::render('DashboardJobDemandGeo/Index', [
            'ranking'         => $ranking,
            'meta'            => $meta,
            'jobMarketStatus' => $jobMarketStatus,
            'filters'         => $filters,
            'regions'         => $regions,
            'careers'         => $careers,
        ]);
    }

    public function rebuildAlignment()
    {
        $commands = [
            'normalize:asia-countries',
            'normalize:europe-countries',
            'joboffers:normalize-regions',
            'joboffers:normalize-north-america',
            'joboffers:normalize-oceania',
            'normalize:geo-regions',
            'normalize:peru-regions',
            'market:fix-null-entities',
        ];

        foreach ($commands as $command) {
            Artisan::queue($command);
        }

        Artisan::queue('jobs:build-alignment', [
            '--truncate' => true,
        ]);

        // Invalida la caché del indicador. Como los comandos corren en cola,
        // la caché puede repoblarse con datos previos mientras el pipeline
        // termina; el TTL de 1 hora limita ese desfase.
        Cache::forever('geo_indicator:version', Cache::get('geo_indicator:version', 1) + 1);

        return response()->json([
            'message' => 'Pipeline de normalización enviado a cola',
        ]);
    }

    public function searchCountries(Request $request)
    {
        $term   = trim($request->get('q', ''));
        $region = $request->get('region');

        $regionSql = $this->normalizedRegionSql();

        return DB::table('job_offers')
            ->whereNotNull('country')
            ->when($region, fn ($q) =>
                $q->whereRaw("$regionSql = ?", [$region])
            )
            ->when($term, fn ($q) =>
                $q->where('country', 'like', "%{$term}%")
            )
            ->distinct()
            ->orderBy('country')
            ->limit(15)
            ->pluck('country');
    }

    public function getData(Request $request)
    {
        $timer = PerformanceTimer::begin('job-demand-geo.heatmap', $this->receivedFilters($request));

        try {
            $response = $this->buildHeatmapData($request, $timer);
        } catch (Throwable $e) {
            $timer->fail($e);
            $timer->finish();
            throw $e;
        }

        $timer->finish();

        return $response;
    }

    private function buildHeatmapData(Request $request, PerformanceTimer $timer)
    {
        $year    = (int) $request->get('year', 2026);
        $period  = $request->get('period', 's1');
        $region  = $request->get('region');
        $country = $request->get('country');

        $range     = $this->periodRange($year, $period);
        $regionSql = $this->normalizedRegionSql();

        $results = $timer->measure('heatmap_query', fn () => Cache::remember(
            $this->cacheKey('heatmap', [$range, $region, $country]),
            self::CACHE_TTL,
            function () use ($range, $regionSql, $region, $country) {
                $query = DB::table('job_offers as jo')
                    ->whereBetween('jo.published_at', $range)
                    ->whereNotNull('jo.latitude')
                    ->whereNotNull('jo.longitude')
                    ->whereRaw("$regionSql <> 'Desconocido'");

                if ($country) {
                    $query->where('jo.country', $country);
                }

                if ($region) {
                    $query->whereRaw("$regionSql = ?", [$region]);
                }

                return $query
                    ->selectRaw("
                        jo.country,
                        jo.city,
                        AVG(jo.latitude)  as lat,
                        AVG(jo.longitude) as lng,
                        COUNT(DISTINCT jo.id) as total
                    ")
                    ->groupBy('jo.country', 'jo.city')
                    ->get();
            }
        ));

        return $timer->measure('heatmap_transform', function () use ($results) {
            $max = max(1, $results->max('total'));

            // Se clona cada fila para no modificar el objeto guardado en caché
            $points = $results->map(function ($r) use ($max) {
                $point = clone $r;
                $point->intensity = max(round($point->total / $max, 3), 0.15);

                return $point;
            });

            return response()->json([
                'results' => $points,
                'max'     => $max,
                'message' => '📍 Mapa de calor – demanda laboral por ciudad',
            ]);
        });
    }
}