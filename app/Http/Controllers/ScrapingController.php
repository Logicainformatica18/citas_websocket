<?php

namespace App\Http\Controllers;

use App\Models\Scraping;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ScrapingController extends Controller
{
    public function index(Request $request)
    {
        $scrapings = Scraping::orderBy('id', 'desc')->paginate(10);

        if ($request->wantsJson()) {
            return response()->json([
                'scrapings' => $scrapings,
            ]);
        }

        return Inertia::render('Scrapings/Index', [
            'scrapings' => $scrapings,
        ]);
    }

    public function fetchPaginated()
    {
        $scrapings = Scraping::latest()->paginate(10);
        return response()->json(['scrapings' => $scrapings]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'base_url' => 'required|url|max:255',
        ]);

        $scraping = new Scraping();
        $scraping->fill($request->only(['name', 'base_url']));
        $scraping->save();

        return response()->json([
            'message' => '✅ Scraping creado',
            'scraping' => $scraping,
        ]);
    }

    public function show($id)
    {
        $scraping = Scraping::findOrFail($id);
        return response()->json(['scraping' => $scraping]);
    }

    public function update(Request $request, $id)
    {
        $scraping = Scraping::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'base_url' => 'required|url|max:255',
        ]);

        $scraping->fill($request->only(['name', 'base_url']));
        $scraping->save();

        return response()->json([
            'message' => '✅ Scraping actualizado',
            'scraping' => $scraping,
        ]);
    }

    public function destroy($id)
    {
        $scraping = Scraping::findOrFail($id);
        $scraping->delete();

        return response()->json(['message' => '✅ Scraping eliminado']);
    }

 public function run($id)
{
    $scraping = \App\Models\Scraping::with('fields')->findOrFail($id);

    if ($scraping->fields->isEmpty()) {
        \Log::warning("⚠️ Scraping {$id} no tiene campos configurados");
        return response()->json([
            'message' => '❌ No hay campos configurados para este scraping',
            'data' => []
        ], 400);
    }

    // Construimos payload con url_base y campos
    $payload = [
        'url_base' => rtrim($scraping->base_url, '/'),
        'fields'   => $scraping->fields->map(function ($f) use ($scraping) {
            $url_final = rtrim($scraping->base_url, '/') . '/' . ltrim($f->path ?? '/', '/');

            return [
                'field_name'     => $f->field_name,
                'selector_type'  => $f->selector_type,
                'selector_value' => $f->selector_value,
                'attr'           => $f->attr,     // 👈 aquí mandamos el atributo extra
                'path'           => $f->path,
                'url_final'      => $url_final,   // debug
            ];
        })->toArray(),
    ];

    // 🔎 LOG DETALLADO
    \Log::info("📤 Enviando request a microservicio de scraping", [
        'payload' => $payload,
        'endpoint' => env('SCRAPER_URL', 'http://127.0.0.1:8000/scrape'),
    ]);

    try {
        $client = new \GuzzleHttp\Client();
        $response = $client->post(env('SCRAPER_URL', 'http://127.0.0.1:8000/scrape'), [
            'json' => $payload,
            'timeout' => 60,
        ]);

        $data = json_decode($response->getBody(), true);

        \Log::info("📥 Respuesta del microservicio de scraping", [
            'status' => $response->getStatusCode(),
            'body'   => $data,
        ]);

        return response()->json([
            'message' => '✅ Scraping ejecutado',
            'data' => $data,
        ]);
    } catch (\Exception $e) {
        \Log::error("❌ Error ejecutando scraping: " . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'payload' => $payload,
        ]);

        return response()->json([
            'message' => '❌ Error ejecutando scraping',
            'error' => $e->getMessage(),
        ], 500);
    }
}

}
