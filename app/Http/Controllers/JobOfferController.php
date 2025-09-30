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
        $offers = JobOffer::orderBy('published_at', 'desc')->paginate(10);

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
        $offers = JobOffer::orderBy('published_at', 'desc')->paginate(10);

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
    $request->validate([
        'api_url' => 'required|url',
    ]);

    try {
        $response = Http::get($request->api_url);

        if ($response->failed()) {
            return response()->json(['error' => 'Error al consultar la API'], 500);
        }

        $data = $response->json('data') ?? [];
        $saved = 0;

        foreach ($data as $job) {
            $attr = $job['attributes'] ?? [];

            // Normalizar campos
            $title       = $attr['title'] ?? 'N/A';
            $company     = $attr['company']['data']['attributes']['name'] ?? null;
            $country     = isset($attr['countries']) ? implode(',', $attr['countries']) : null;
            $city        = $attr['city'] ?? null;
            $modality    = $attr['remote_modality'] ?? null;
            $salary_min  = $attr['min_salary'] ?? null;
            $salary_max  = $attr['max_salary'] ?? null;
            $currency    = $attr['salary_currency'] ?? 'USD';
            $source      = "GetOnBoard";
            $url         = $job['links']['public_url'] ?? null;
            $publishedAt = isset($attr['published_at'])
                ? \Carbon\Carbon::createFromTimestamp($attr['published_at'])->toDateString()
                : null;

            // Si city está vacío, intentar deducirla
            if (empty($city)) {
                // Posibles fuentes de texto donde puede estar la ciudad
                $possibleSources = [
                    strtolower($attr['location'] ?? ''),
                    strtolower($attr['title'] ?? ''),
                    strtolower($job['id'] ?? ''),
                ];

                // Solo ciudades del país (si hay)
                $cityCandidates = \App\Models\City::query()
                    ->when($country, fn($q) => $q->where('country', $country))
                    ->get()
                    ->filter(fn($c) => strlen($c->city) >= 3); // Evita "An", "Ye", etc.

                $matchedCity = null;

                foreach ($possibleSources as $source) {
                    foreach ($cityCandidates as $c) {
                        $cityName = strtolower(trim($c->city));

                        // Busca ciudad como palabra completa
                        if (preg_match('/\b' . preg_quote($cityName, '/') . '\b/i', $source)) {
                            $matchedCity = $c;
                            break 2; // Sal del doble loop
                        }
                    }
                }

                // Si encontró ciudad, la asigna
                if ($matchedCity) {
                    $city = $matchedCity->city;
                    $country = $matchedCity->country;
                }
            }

            JobOffer::updateOrCreate(
                ['url' => $url],
                [
                    'title'        => $title,
                    'company'      => $company,
                    'country'      => $country,
                    'city'         => $city,
                    'modality'     => $modality,
                    'salary_min'   => $salary_min,
                    'salary_max'   => $salary_max,
                    'currency'     => $currency,
                    'source'       => $source,
                    'published_at' => $publishedAt,
                ]
            );

            $saved++;
        }

        return response()->json([
            'message' => "Se importaron {$saved} ofertas correctamente.",
        ]);
    } catch (\Throwable $e) {
        Log::error("Error importando ofertas: " . $e->getMessage());
        return response()->json(['error' => 'Error interno al importar.'], 500);
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
