<?php

namespace App\Http\Controllers;

use App\Models\JobOffer;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;

use Illuminate\Support\Facades\Log;

class JobOfferController extends Controller
{
    public function index()
    {
        $offers = JobOffer::orderBy('id', 'desc')->paginate(10);

        return Inertia::render('job_offers/index', [
            'offers' => $offers->through(function ($offer) {
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
            }),
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
}
