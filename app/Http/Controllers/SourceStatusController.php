<?php

namespace App\Http\Controllers;

use App\Models\SourceStatus;
use Inertia\Inertia;
use Illuminate\Http\Request;

class SourceStatusController extends Controller
{
    /**
     * Listado principal
     */
    public function index(Request $request)
    {
        $sources = SourceStatus::orderByDesc('last_finished_at')->paginate(10);

        if ($request->wantsJson()) {
            return response()->json([
                'sources' => $sources,
            ]);
        }

        return Inertia::render('sources/index', [
            'sources' => $sources,
        ]);
    }

    /**
     * Obtener detalle
     */
    public function show($id)
    {
        $source = SourceStatus::findOrFail($id);

        return response()->json([
            'source' => $source
        ]);
    }

    /**
     * Crear (manual, si quieres registrar sources base)
     */
    public function store(Request $request)
    {
        $request->validate([
            'source' => 'required|string|max:100|unique:source_status,source',
            'api_url' => 'nullable|string|max:500',
            'api_key' => 'nullable|string|max:500',
        ]);

        $source = SourceStatus::create([
            'source' => $request->source,
            'api_url' => $request->api_url,
            'api_key' => $request->api_key,
            'connection_status' => 'unknown'
        ]);

        return response()->json([
            'message' => 'Fuente creada',
            'source' => $source
        ]);
    }

    /**
     * Actualizar config (API URL / KEY)
     */
    public function update(Request $request, $id)
    {
        $source = SourceStatus::findOrFail($id);

        $request->validate([
            'api_url' => 'nullable|string|max:500',
            'api_key' => 'nullable|string|max:500',
        ]);

        $source->api_url = $request->api_url;
        $source->api_key = $request->api_key;

        $source->save();

        return response()->json([
            'message' => 'Fuente actualizada',
            'source' => $source
        ]);
    }

    /**
     * Eliminar
     */
    public function destroy($id)
    {
        $source = SourceStatus::findOrFail($id);
        $source->delete();

        return response()->json([
            'message' => 'Fuente eliminada'
        ]);
    }

    /**
     * Test de conexión (🔥 importante)
     */
    public function testConnection($id)
    {
        $source = SourceStatus::findOrFail($id);

        try {
            $response = \Http::timeout(5)->get($source->api_url);

            if ($response->successful()) {
                $source->connection_status = 'ok';
                $source->connection_error = null;
            } else {
                $source->connection_status = 'failed';
                $source->connection_error = 'HTTP ' . $response->status();
            }

        } catch (\Exception $e) {
            $source->connection_status = 'failed';
            $source->connection_error = $e->getMessage();
        }

        $source->last_connection_check = now();
        $source->save();

        return response()->json([
            'message' => 'Test ejecutado',
            'source' => $source
        ]);
    }

    /**
     * Solo fallidos (útil para dashboard)
     */
    public function failed()
    {
        $sources = SourceStatus::where('last_status', 'failed')
            ->orderByDesc('last_finished_at')
            ->get();

        return response()->json([
            'sources' => $sources
        ]);
    }
}
