<?php

namespace App\Http\Controllers;

use App\Models\JobOffer;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;

use Illuminate\Support\Facades\Log;
    use Maatwebsite\Excel\Facades\Excel;
class JobOfferController extends Controller
{
   public function index(Request $request)
{
    // === Filtros que vienen desde la URL ===
    $filters = [
        'companies'       => $request->input('companies', []),
        'countries'       => $request->input('countries', []),
        'cities'          => $request->input('cities', []),
        'sources'         => $request->input('sources', []),
        'modalities'      => $request->input('modalities', []),
        'job_types'       => $request->input('job_types', []),
        'remote_types'    => $request->input('remote_types', []),
        'workloads'       => $request->input('workloads', []),

        // === NUEVOS FILTROS POR FECHAS ===
        'published_from'  => $request->input('published_from'),
        'published_to'    => $request->input('published_to'),
        'created_from'    => $request->input('created_from'),
        'created_to'      => $request->input('created_to'),
    ];

    $query = JobOffer::query()->orderBy('id', 'desc');

    // === Filtros múltiples por select ===
    foreach ([
        'companies'    => 'company',
        'countries'    => 'country',
        'cities'       => 'city',
        'sources'      => 'source',
        'modalities'   => 'modality',
        'job_types'    => 'job_type',
        'remote_types' => 'remote_type',
        'workloads'    => 'workload',
    ] as $filterKey => $column) {
        if (!empty($filters[$filterKey])) {
            $query->whereIn($column, $filters[$filterKey]);
        }
    }

    // === NUEVO: FILTRO POR FECHAS (published_at) ===
    if ($filters['published_from']) {
        $query->whereDate('published_at', '>=', $filters['published_from']);
    }

    if ($filters['published_to']) {
        $query->whereDate('published_at', '<=', $filters['published_to']);
    }

    // === NUEVO: FILTRO POR FECHAS (created_at) ===
    if ($filters['created_from']) {
        $query->whereDate('created_at', '>=', $filters['created_from']);
    }

    if ($filters['created_to']) {
        $query->whereDate('created_at', '<=', $filters['created_to']);
    }

    // === Paginación que retiene filtros ===
    $offers = $query->paginate(10)->appends($filters);
// 🔥 Normalizar valores "null" o vacíos
foreach ($filters as $key => $value) {
    if ($value === "null" || $value === "" || $value === null) {
        $filters[$key] = null;
    }
}

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
    ];

    return Inertia::render('job_offers/index', [
        'offers'  => $offers->through(fn($offer) => [
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
        ]),

        // filtros activos
        'filters' => $filters,

        // combos para selects
        'combos' => $comboData,
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

                JobOffer::updateOrCreate(
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
            ],
        ]);
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
    ini_set('max_execution_time', 0);
    ini_set('memory_limit', '-1');

    // ============================
    // 1. Recuperar filtros crudos
    // ============================
    $filters = $request->all();

    // ============================
    // 2. Normalizar "null" y vacíos
    // ============================
    foreach ($filters as $k => $v) {
        if ($v === "null" || $v === "" || $v === null) {
            $filters[$k] = null;
        }
    }

    // ============================
    // 3. Normalizar filtros múltiples
    // ============================
    $multi = [
        'companies','countries','cities','sources',
        'modalities','job_types','remote_types','workloads'
    ];

    foreach ($multi as $m) {

        if (!isset($filters[$m]) || $filters[$m] === null) {
            $filters[$m] = [];
            continue;
        }

        // si viene como array -> ok
        if (is_array($filters[$m])) continue;

        // si viene como string "A,B,C"
        $filters[$m] = array_filter(
            explode(',', $filters[$m]),
            fn($v) => trim($v) !== ""
        );
    }

    // ============================
    // 4. Construir query
    // ============================
    $query = JobOffer::orderBy('id', 'desc');

    $filterMap = [
        'companies'    => 'company',
        'countries'    => 'country',
        'cities'       => 'city',
        'sources'      => 'source',
        'modalities'   => 'modality',
        'job_types'    => 'job_type',
        'remote_types' => 'remote_type',
        'workloads'    => 'workload',
    ];

    foreach ($filterMap as $key => $column) {
        if (!empty($filters[$key])) {
            $query->whereIn($column, $filters[$key]);
        }
    }

    // ============================
    // 5. Filtros por fecha
    // ============================
    if (!empty($filters['published_from'])) {
        $query->whereDate('published_at', '>=', $filters['published_from']);
    }

    if (!empty($filters['published_to'])) {
        $query->whereDate('published_at', '<=', $filters['published_to']);
    }

    if (!empty($filters['created_from'])) {
        $query->whereDate('created_at', '>=', $filters['created_from']);
    }

    if (!empty($filters['created_to'])) {
        $query->whereDate('created_at', '<=', $filters['created_to']);
    }

    // ============================
    // 6. Obtener resultados
    // ============================
    $query->limit(4000);
    $rows = $query->get()->map(function ($o) {

        return [
            'ID'                => $o->id,
            'Titulo'            => $o->title,
            'Empresa'           => $o->company,
            'Pais'              => $o->country,
            'Region'            => $o->region,
            'State Code'        => $o->state_code,
            'Ciudad'            => $o->city,
            'Ciudad ASCII'      => $o->city_ascii,
            'Location'          => $o->location,
            'ZIP'               => $o->zip_code,
            'Modalidad'         => $o->modality,
            'Job Type'          => $o->job_type,
            'Remote Type'       => $o->remote_type,
            'Workload'          => $o->workload,
            'Experiencia'       => $o->experience_level,
            'Educación Nivel'   => $o->education_level,
            'Educación Campo'   => $o->education_field,
            'Certificaciones'   => $o->certifications,
            'Requisitos'        => $o->requirements,
            'Skills'            => $o->skills,
            'Descripción'       => $o->description,
            'Beneficios'        => $o->benefits,
            'Latitud'           => $o->latitude,
            'Longitud'          => $o->longitude,
            'Salario Min'       => $o->salary_min,
            'Salario Max'       => $o->salary_max,
            'Moneda'            => $o->currency,
            'Tipo Pago'         => $o->compensation_type,
            'Fuente'            => $o->source,
            'Search Query'      => $o->search_query,
            'External ID'       => $o->external_id,
            'URL'               => $o->url,
            'Application URL'   => $o->application_url,
            'Application Type'  => $o->application_type,
            'Publicado'         => optional($o->published_at)->format('Y-m-d'),
            'Expira'            => optional($o->expiry)->format('Y-m-d'),
            'Creado'            => optional($o->created_at)->format('Y-m-d H:i'),
            'Actualizado'       => optional($o->updated_at)->format('Y-m-d H:i'),
        ];
    });

    if ($rows->isEmpty()) {
        return back()->with('error', '⚠ No hay datos para exportar con los filtros aplicados.');
    }

    return Excel::download(new \App\Exports\JobOffersExport($rows), 'job_offers.xlsx');
}




}
