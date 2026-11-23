<?php

namespace App\Http\Controllers;

use App\Jobs\ExportJobOffersJob;
use App\Models\JobOffer;
use App\Models\JobOfferProcess;
use App\Models\City;
use App\Services\JobOffers\JobOfferExportService;
use App\Services\JobOffers\JobOfferFilters;
use App\Services\JobOffers\TitleExclusionMatcher;
use App\Services\Matching\EntityTextMatcher;
use App\Services\Ranking\ExclusionSettings;
use Illuminate\Support\Facades\Schema;
use App\Support\GetOnBoardFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;

use Illuminate\Support\Facades\Log;
class JobOfferController extends Controller
{
   public function index(Request $request)
{
    // Filtros de la URL (compartidos con el export): selección múltiple, fechas, periodo
    // (año/semestre) y exclusión por título. Ver App\Services\JobOffers\JobOfferFilters.
    $filters = JobOfferFilters::fromInput($request->all());

    $query = JobOfferFilters::apply(JobOffer::query(), $filters)
        ->with('titleExclusion:id,pattern,category')
        ->orderBy('id', 'desc');

    $offers = $query->paginate(10)->appends(JobOfferFilters::compact($filters));

    // === Datos de combos ===
    $comboData = [
        'companies'    => JobOffer::whereNotNull('company')->select('company')->distinct()->orderBy('company')->pluck('company'),
        'countries'    => JobOffer::whereNotNull('country')->select('country')->distinct()->orderBy('country')->pluck('country'),
        'cities'       => JobOffer::whereNotNull('city')->select('city')->distinct()->orderBy('city')->pluck('city'),
        'sources'      => JobOffer::select('source')->distinct()->orderBy('source')->pluck('source'),
        'modalities'   => JobOffer::select('modality')->distinct()->orderBy('modality')->pluck('modality'),
        'job_types'    => JobOffer::select('job_type')->distinct()->orderBy('job_type')->pluck('job_type'),
        'remote_types' => JobOffer::select('remote_type')->distinct()->orderBy('remote_type')->pluck('remote_type'),
        'workloads'    => JobOffer::select('workload')->distinct()->orderBy('workload')->pluck('workload'),
        // Años con ofertas publicadas (lee solo el índice de published_at)
        'years'        => collect(DB::select('SELECT DISTINCT YEAR(published_at) AS y FROM job_offers WHERE published_at IS NOT NULL ORDER BY y DESC'))
            ->pluck('y')->map(fn ($y) => (string) $y),
    ];

    return Inertia::render('job_offers/index', [
       'offers' => $offers->through(fn($offer) => [
    'id'                => $offer->id,
    'title'             => $offer->title,
    'company'           => $offer->company,
    'country'           => $offer->country,
    'region'            => $offer->region,
    'city'              => $offer->city,

    'modality'          => $offer->modality,
    'workload'          => $offer->workload,

    'experience_level'  => $offer->experience_level,
    'education_level'   => $offer->education_level,

    'certifications'    => $offer->certifications,
    'skills'            => $offer->skills,
    'description'       => $offer->description,
    'benefits'          => $offer->benefits,

    'salary_min'        => $offer->salary_min,
    'salary_max'        => $offer->salary_max,
    'currency'          => $offer->currency,
    'compensation_type' => $offer->compensation_type,

    'source'            => $offer->source,
    'search_query'      => $offer->search_query,

    'published_at'      => optional($offer->published_at)->format('Y-m-d'),
    'created_at'        => optional($offer->created_at)->format('Y-m-d'),

    // Exclusión por título (rol no TI): patrón que la excluyó, o null
    'title_exclusion'   => $offer->titleExclusion ? [
        'id' => $offer->titleExclusion->id,
        'pattern' => $offer->titleExclusion->pattern,
        'category' => $offer->titleExclusion->category,
    ] : null,
]),


        // filtros activos
        'filters' => $filters,

        // combos para selects
        'combos' => $comboData,

        // export en curso o terminado (último del usuario)
        'lastExport' => Schema::hasTable('job_offer_processes')
            ? JobOfferProcess::where('type', 'export')->where('user_id', auth()->id())->latest('id')->first()?->toApi()
            : null,
    ]);
}

    public function fetchPaginated()
    {
        $offers = JobOffer::orderBy('id', 'desc')->paginate(10);

        $formatted = $offers->through(function ($offer) {
            return [
                'id'          => $offer->id,
                'title'       => $offer->title,
                'company'     => $offer->company,
                'country'     => $offer->country,
                'city'        => $offer->city,
                'modality'    => $offer->modality,
                'workload'    => $offer->workload,
                'salary_min'  => $offer->salary_min,
                'salary_max'  => $offer->salary_max,
                'currency'    => $offer->currency,
                'source'      => $offer->source,
                'external_id' => $offer->external_id,
                'url'         => $offer->url,
                'published_at'=> optional($offer->published_at)->format('Y-m-d'),
                'created_at'  => optional($offer->created_at)->format('Y-m-d'),
            ];
        });

        return response()->json($formatted);
    }



public function import(Request $request)
{
    $source  = $request->input('source');
    $query   = $request->input('query', 'developer');
    $country = $request->input('country', 'us');
    $saved   = 0;

    try {
        // ============================
        // 🚀 Caso Adzuna
        // ============================
        if ($source === 'adzuna') {
            $appId   = config('services.adzuna.app_id');
            $appKey  = config('services.adzuna.app_key');
            $baseUrl = config('services.adzuna.base_url');

            $apiUrl = "{$baseUrl}/{$country}/search/1?app_id={$appId}&app_key={$appKey}&results_per_page=10&what=" . urlencode($query);

            $response = Http::get($apiUrl);
            if ($response->failed()) {
                return response()->json(['error' => 'Error al consultar Adzuna'], 500);
            }

            $offers = $response->json('results') ?? [];

            foreach ($offers as $job) {
                $title   = $job['title'] ?? 'N/A';
                $company = $job['company']['display_name'] ?? null;
                $country = $job['location']['area'][0] ?? null;
                $city    = $job['location']['area'][1] ?? null;
                $urlJob  = $job['redirect_url'] ?? null;

                // 📌 Si city está vacío, tratar de deducirla
                if (empty($city)) {
                    $possibleSources = [
                        strtolower($job['location']['display_name'] ?? ''),
                        strtolower($title),
                        strtolower($job['description'] ?? ''),
                    ];

                    $cityCandidates = \App\Models\City::query()
                        ->when($country, fn($q) => $q->where('country', $country))
                        ->get()
                        ->filter(fn($c) => strlen($c->city) >= 3);

                    foreach ($possibleSources as $src) {
                        foreach ($cityCandidates as $c) {
                            $cityName = strtolower(trim($c->city));
                            if (preg_match('/\b' . preg_quote($cityName, '/') . '\b/i', $src)) {
                                $city    = $c->city;
                                $country = $c->country;
                                break 2;
                            }
                        }
                    }
                }

                JobOffer::updateOrCreate(
                    ['url' => $urlJob],
                    [
                        'title'        => $title,
                        'company'      => $company,
                        'country'      => $country,
                        'city'         => $city,
                        'modality'     => null,
                        'salary_min'   => $job['salary_min'] ?? null,
                        'salary_max'   => $job['salary_max'] ?? null,
                        'currency'     => $job['salary_currency'] ?? 'USD',
                        'source'       => 'Adzuna',
                        'published_at' => $job['created'] ?? null,
                    ]
                );
                $saved++;
            }
        }

        // ============================
        // 🚀 Caso GetOnBoard
        // ============================
        if ($source === 'getonboard') {
            $apiUrl   = "https://www.getonbrd.com/api/v0/search/jobs?query={$query}&per_page=10";
            $response = Http::get($apiUrl);

            if ($response->failed()) {
                return response()->json(['error' => 'Error al consultar GetOnBoard'], 500);
            }

            $offers = $response->json('data') ?? [];

            foreach ($offers as $job) {
                $attr    = $job['attributes'] ?? [];
                $title   = $attr['title'] ?? 'N/A';
                $company = $attr['company']['data']['attributes']['name'] ?? null;
                $country = isset($attr['countries']) ? implode(',', $attr['countries']) : null;
                $city    = $attr['city'] ?? null;
                $urlJob  = $job['links']['public_url'] ?? null;

                // 📌 Si city está vacío, intentar deducirla
                if (empty($city)) {
                    $possibleSources = [
                        strtolower($attr['location'] ?? ''),
                        strtolower($title),
                        strtolower($job['id'] ?? ''),
                    ];

                    $cityCandidates = \App\Models\City::query()
                        ->when($country, fn($q) => $q->where('country', $country))
                        ->get()
                        ->filter(fn($c) => strlen($c->city) >= 3);

                    foreach ($possibleSources as $src) {
                        foreach ($cityCandidates as $c) {
                            $cityName = strtolower(trim($c->city));
                            if (preg_match('/\b' . preg_quote($cityName, '/') . '\b/i', $src)) {
                                $city    = $c->city;
                                $country = $c->country;
                                break 2;
                            }
                        }
                    }
                }

                $offer = JobOffer::updateOrCreate(
                    ['url' => $urlJob],
                    [
                        'title'        => $title,
                        'company'      => $company,
                        'country'      => $country,
                        'city'         => $city,
                        'modality'     => $attr['remote_modality'] ?? null,
                        'salary_min'   => $attr['min_salary'] ?? null,
                        'salary_max'   => $attr['max_salary'] ?? null,
                        'currency'     => $attr['salary_currency'] ?? 'USD',
                        'source'       => 'GetOnBoard',
                        'published_at' => isset($attr['published_at'])
                            ? \Carbon\Carbon::createFromTimestamp($attr['published_at'])->toDateString()
                            : null,
                    ]
                );
                // Funciones del puesto: solo se completan si hoy están en null (no pisa nada)
                GetOnBoardFunctions::fillMissing($offer, $attr);
                $saved++;
            }
        }

        return response()->json(['message' => "✅ Se importaron {$saved} ofertas correctamente."]);
    } catch (\Throwable $e) {
        Log::error("Error importando ofertas: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response()->json(['error' => 'Error interno al importar.'], 500);
    }
}








public function preview(Request $request)
{
    $source  = $request->input('source', 'adzuna'); // por defecto adzuna
    $query   = $request->input('query', 'developer');
    $country = $request->input('country', 'us');

    Log::info("🔎 [Preview] Source={$source}, Query={$query}, Country={$country}");

    try {
        if ($source === 'adzuna') {
            $appId   = config('services.adzuna.app_id');
            $appKey  = config('services.adzuna.app_key');
            $baseUrl = config('services.adzuna.base_url');

            $url = "{$baseUrl}/{$country}/search/1?app_id={$appId}&app_key={$appKey}&results_per_page=10&what=" . urlencode($query);

            Log::info("🔎 [Adzuna Preview] URL: {$url}");

            $response = Http::get($url);

            if ($response->failed()) {
                return response()->json(['error' => 'Error al consultar Adzuna'], 500);
            }

            return response()->json([
                'source' => 'Adzuna',
                'raw'    => $response->json()
            ]);
        }

        if ($source === 'getonboard') {
            $url = "https://www.getonbrd.com/api/v0/search/jobs?query=" . urlencode($query) . "&per_page=10";
            Log::info("🔎 [GetOnBoard Preview] URL: {$url}");

            $response = Http::get($url);

            if ($response->failed()) {
                return response()->json(['error' => 'Error al consultar GetOnBoard'], 500);
            }

            return response()->json([
                'source' => 'GetOnBoard',
                'raw'    => $response->json()
            ]);
        }

        return response()->json(['error' => 'Fuente no soportada'], 400);

    } catch (\Throwable $e) {
        Log::error("💥 [Preview] Error", ['msg' => $e->getMessage()]);
        return response()->json(['error' => 'Error interno en preview'], 500);
    }
}







    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'company'     => 'nullable|string|max:255',
            'country'     => 'nullable|string|max:100',
            'city'        => 'nullable|string|max:150',
            'modality'    => 'nullable|string|max:100',
            'workload'    => 'nullable|string|max:100',
            'salary_min'  => 'nullable|numeric',
            'salary_max'  => 'nullable|numeric',
            'currency'    => 'nullable|string|max:10',
            'source'      => 'required|string|max:50',
            'external_id' => 'nullable|string|max:255',
            'url'         => 'nullable|url',
            'published_at'=> 'nullable|date',
        ]);

        return DB::transaction(function () use ($validated) {
            $offer = JobOffer::create($validated);

            if (! $offer->exists) {
                $match = TitleExclusionMatcher::active()->excludedBy($offer->title);

                return response()->json([
                    'message' => 'No se guardó: el título corresponde a un rol no TI (patrón "' . ($match['pattern'] ?? '?') . '" de la lista de exclusión).',
                ], 422);
            }

            return response()->json([
                'message' => '✅ Oferta creada correctamente',
                'offer'   => $offer,
            ], 201);
        });
    }

public function show($id)
{
    $offer = JobOffer::findOrFail($id);

    return response()->json([
        'offer' => [
            'id'                => $offer->id,
            'title'             => $offer->title,
            'company'           => $offer->company,
            'country'           => $offer->country,
            'region'            => $offer->region,
            'city'              => $offer->city,
            'modality'          => $offer->modality,
            'workload'          => $offer->workload,
            'experience_level'  => $offer->experience_level,
            'education_level'   => $offer->education_level,
            'education_field'   => $offer->education_field,
            'certifications'    => $offer->certifications,
            'requirements'      => $offer->requirements,
            'skills'            => $offer->skills,
            'description'       => $offer->description,
            'benefits'          => $offer->benefits,
            'salary_min'        => $offer->salary_min,
            'salary_max'        => $offer->salary_max,
            'currency'          => $offer->currency,
            'compensation_type' => $offer->compensation_type,
            'source'            => $offer->source,
            'search_query'      => $offer->search_query,
            'external_id'       => $offer->external_id,
            'url'               => $offer->url,
            'application_url'   => $offer->application_url,
            'application_type'  => $offer->application_type,
            'published_at'      => optional($offer->published_at)->format('Y-m-d'),
            'created_at'        => optional($offer->created_at)->format('Y-m-d'),
            'title_exclusion'   => $this->titleExclusionInfo($offer),
            'entities'          => $this->offerEntities($offer),
        ],
    ]);
}

/**
 * Exclusión por título de la oferta: el patrón que la marcó (job_offers.title_exclusion_id) y lo que
 * diría la lista activa hoy (si difieren, falta ejecutar "Excluir de los indicadores").
 */
private function titleExclusionInfo(JobOffer $offer): ?array
{
    if (! JobOffer::hasTitleExclusionColumn()) {
        return null;
    }
    $marked = $offer->titleExclusion;
    $now = TitleExclusionMatcher::active()->excludedBy($offer->title);

    return [
        'excluded' => $offer->title_exclusion_id !== null,
        'pattern' => $marked?->pattern,
        'category' => $marked?->category,
        'match_mode' => $marked?->match_mode,
        'keyword_id' => $offer->title_exclusion_id,
        'current_list_pattern' => $now['pattern'] ?? null,
        'pending' => ($now['id'] ?? null) !== ($offer->title_exclusion_id === null ? null : (int) $offer->title_exclusion_id),
    ];
}

/**
 * Lenguajes, tecnologías y certificaciones asociados (tablas pivote) y si el detector
 * (EntityTextMatcher) los confirma en el texto guardado, si la asociación está excluida
 * (entity_association_exclusions) y si la entidad está activa.
 */
private function offerEntities(JobOffer $offer): array
{
    $body = implode("\n", array_filter([$offer->description, $offer->requirements, $offer->skills]));
    $out = [];
    foreach (['languages', 'technologies', 'certifications'] as $type) {
        $cfg = config("entity_rankings.{$type}");
        $pivot = $cfg['pivot_table'];
        $rows = DB::table("{$pivot} as p")
            ->leftJoin('market_entities as me', 'me.id', '=', 'p.market_entity_id')
            ->where('p.job_offer_id', $offer->id)
            ->select('p.market_entity_id', 'me.name', 'me.is_active')
            ->distinct()
            ->get();
        $excluded = ExclusionSettings::active()
            ? DB::table(ExclusionSettings::TABLE)->where('pivot_table', $pivot)->where('job_offer_id', $offer->id)
                ->where('vigente', 1)->pluck('market_entity_id')->map(fn ($id) => (int) $id)->flip()
            : collect();
        $matcher = EntityTextMatcher::for($cfg['entity_type'], true);

        $out[$type] = [
            'label' => $cfg['label'],
            'items' => $rows->map(function ($r) use ($matcher, $offer, $body, $excluded) {
                $id = $r->market_entity_id === null ? null : (int) $r->market_entity_id;
                $v = $id ? $matcher->explain($id, (string) $offer->title, $body) : ['verdict' => null, 'alias' => null];

                return [
                    'id' => $id,
                    'name' => $r->name ?? '(sin entidad)',
                    'active' => (bool) $r->is_active,
                    'confirmed' => $v['verdict'] === EntityTextMatcher::CONFIRMED,
                    'verdict' => $v['verdict'],
                    'alias' => $v['alias'] ?? null,
                    'excluded_association' => $id !== null && $excluded->has($id),
                ];
            })->sortBy('name')->values()->all(),
        ];
    }

    return $out;
}


    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'company'     => 'nullable|string|max:255',
            'country'     => 'nullable|string|max:100',
            'city'        => 'nullable|string|max:150',
            'modality'    => 'nullable|string|max:100',
            'workload'    => 'nullable|string|max:100',
            'salary_min'  => 'nullable|numeric',
            'salary_max'  => 'nullable|numeric',
            'currency'    => 'nullable|string|max:10',
            'source'      => 'required|string|max:50',
            'external_id' => 'nullable|string|max:255',
            'url'         => 'nullable|url',
            'published_at'=> 'nullable|date',
        ]);

        return DB::transaction(function () use ($id, $validated) {
            $offer = JobOffer::findOrFail($id);
            $offer->update($validated);

            return response()->json([
                'message' => '✅ Oferta actualizada correctamente',
                'offer'   => $offer,
            ]);
        });
    }

    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $offer = JobOffer::findOrFail($id);
            $offer->delete();

            return response()->json(['message' => 'Oferta eliminada correctamente']);
        });
    }

    public function bulkDelete(Request $request)
    {
        $ids = (array) $request->input('ids', []);

        return DB::transaction(function () use ($ids) {
            JobOffer::whereIn('id', $ids)->delete();

            return response()->json(['message' => 'Ofertas eliminadas correctamente']);
        });
    }


public function exportExcel(Request $request)
{
    // Antes: en la petición (Maatwebsite FromCollection, hasta 4 000 filas en memoria).
    // Ahora: en cola (ExportJobOffersJob por tramos), con los filtros del listado y sin ese límite.
    $filters = JobOfferFilters::compact(JobOfferFilters::fromInput($request->all()));

    $running = JobOfferProcess::where('type', 'export')->where('user_id', auth()->id())
        ->whereIn('status', ['queued', 'running'])->where('updated_at', '>', now()->subMinutes(15))->first();
    if ($running) {
        return response()->json(['message' => 'Ya tienes un export en curso.', 'process' => $running->toApi()], 409);
    }

    $process = JobOfferProcess::start('export', ['filters' => $filters]);
    ExportJobOffersJob::dispatch($process->id);

    return response()->json(['message' => 'Export encolado.', 'process' => $process->toApi()]);
}

/** Descarga el .xlsx de un export terminado. */
public function downloadExport(JobOfferProcess $process)
{
    abort_unless($process->type === 'export' && $process->status === 'done', 404);
    $path = JobOfferExportService::filePath($process);
    abort_unless(is_file($path), 410, 'El archivo ya no está disponible (se conserva ' . JobOfferExportService::KEEP_DAYS . ' días). Vuelve a exportar.');

    return response()->download($path, JobOfferExportService::downloadName($process));
}

}
