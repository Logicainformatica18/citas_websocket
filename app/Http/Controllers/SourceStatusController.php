<?php

namespace App\Http\Controllers;

use App\Models\SourceStatus;
use App\Models\ScraperRun;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
class SourceStatusController extends Controller
{
    public function index()
    {
        return Inertia::render('sources/index', $this->getData());
    }

    public function fetch()
    {
        return response()->json($this->getData());
    }

    /**
     * 🆕 CREATE
     */
    public function store(Request $request)
    {
        Log::info('📥 Crear fuente', $request->all());

        try {

            $validated = $request->validate([
                'source' => 'required|string|max:100|unique:source_status,source',
                'api_url' => 'nullable|string|max:500',
                'api_key' => 'nullable|string|max:500',
                'app_id' => 'nullable|string|max:255',
            ]);

            $source = SourceStatus::create([
                'source' => $validated['source'],
                'api_url' => $validated['api_url'] ?? null,
                'api_key' => $validated['api_key'] ?? null,
                'app_id'  => $validated['app_id'] ?? null,

                'connection_status' => 'unknown',
                'last_status' => null,
                'success_count' => 0,
                'fail_count' => 0,
            ]);

            Log::info('✅ Fuente creada', ['id' => $source->id]);

            return response()->json([
                'message' => 'Fuente creada',
                'source' => $source
            ], 201);

        } catch (\Exception $e) {

            Log::error('❌ Error create', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Error al crear',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✏️ UPDATE
     */
    public function update(Request $request, $id)
    {
        Log::info('✏️ Update fuente', [
            'id' => $id,
            'data' => $request->all()
        ]);

        try {

            $source = SourceStatus::findOrFail($id);

            $validated = $request->validate([
                'source' => 'nullable|string|max:100|unique:source_status,source,' . $id,
                'api_url' => 'nullable|string|max:500',
                'api_key' => 'nullable|string|max:500',
                'app_id'  => 'nullable|string|max:255',
            ]);

            $source->update([
                'source'  => $validated['source'] ?? $source->source,
                'api_url' => $validated['api_url'] ?? $source->api_url,
                'api_key' => $validated['api_key'] ?? $source->api_key,
                'app_id'  => $validated['app_id'] ?? $source->app_id,
            ]);

            Log::info('✅ Fuente actualizada', ['id' => $source->id]);

            return response()->json([
                'message' => 'Actualizado correctamente',
                'source' => $source
            ]);

        } catch (\Exception $e) {

            Log::error('❌ Error update', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Error al actualizar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 📊 DATA
     */
    private function getData()
    {
        $totalFuentes = SourceStatus::count();
        $activas = SourceStatus::where('last_status', 'success')->count();
        $conErrores = SourceStatus::where('last_status', 'failed')->count();
        $registrosTotales = SourceStatus::sum('last_records_inserted');

        $counts = DB::table('job_offers')
    ->select('source', DB::raw('COUNT(*) as total'))
    ->groupBy('source')
    ->pluck('total', 'source');

        $uptime = ScraperRun::selectRaw("
            ROUND(
                (SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) / COUNT(*)) * 100,
                2
            ) as uptime
        ")->value('uptime') ?? 0;

        $sources = SourceStatus::orderByDesc('last_finished_at')
            ->paginate(13);

      $sources->getCollection()->transform(function ($item) use ($counts) {
    return [
        'id' => $item->id,
        'name' => $item->source,

        'last_run_at' => $item->last_finished_at,
        'last_status' => $item->last_status ?? 'pending',
 'api_url' => $item->api_url,
        'api_status' => match ($item->connection_status) {
            'ok' => 'success',
            'failed' => 'failed',
            default => 'pending'
        },

        // 🔥 AQUÍ ESTÁ LA MAGIA
        'registros' => $counts[$item->source] ?? 0,

        'success_count' => $item->success_count,
        'fail_count' => $item->fail_count,
        'app_id' => $item->app_id,
    ];
});

        return [
            'metrics' => [
                'total_fuentes' => $totalFuentes,
                'activas' => $activas,
                'registros_totales' => $registrosTotales,
                'con_errores' => $conErrores,
                'uptime' => $uptime
            ],
            'sources' => $sources
        ];
    }

    public function run($id)
    {
        SourceStatus::findOrFail($id);

        return response()->json([
            'message' => 'Ejecutado'
        ]);
    }

    public function testConnection($id)
    {
        $source = SourceStatus::findOrFail($id);

        try {
            $response = \Http::timeout(5)->get($source->api_url);

            $source->connection_status = $response->successful() ? 'ok' : 'failed';
            $source->connection_error = $response->successful() ? null : 'HTTP ' . $response->status();

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

    public function destroy($id)
    {
        SourceStatus::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Eliminado'
        ]);
    }

    public function failed()
    {
        return response()->json([
            'sources' => SourceStatus::where('last_status', 'failed')->get()
        ]);
    }
}
