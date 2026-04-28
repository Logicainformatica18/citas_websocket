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

    // 🔥 fuente de verdad
    $registrosTotales = DB::table('job_offers')->count();

    // 🔥 conteo por source (UNA SOLA VEZ)
    $counts = DB::table('job_offers')
        ->select('source', DB::raw('COUNT(*) as total'))
        ->groupBy('source')
        ->pluck('total', 'source');

    // 🔥 última ejecución real
    $lastRuns = DB::table('job_offers')
        ->select('source', DB::raw('MAX(created_at) as last_run_at'))
        ->groupBy('source')
        ->pluck('last_run_at', 'source');

    $uptime = ScraperRun::selectRaw("
        ROUND(
            (SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) / COUNT(*)) * 100,
            2
        ) as uptime
    ")->value('uptime') ?? 0;

    $sources = SourceStatus::orderByDesc('last_finished_at')
        ->paginate(13);

    $sources->getCollection()->transform(function ($item) use ($counts, $lastRuns) {

        $isStale = !$item->last_success_at
            ? true
            : now()->diffInHours($item->last_success_at) > 6;

        return [
            'id' => $item->id,
            'name' => $item->source,

            // 🔥 FECHAS
            'last_run_at' => $lastRuns[$item->source]
                ?? $item->last_finished_at
                ?? null,

            'last_status' => $item->last_status ?? 'pending',

            // 🔥 API
            'api_url' => $item->api_url,
            'api_key' => $item->api_key,
            'app_id' => $item->app_id,

            'api_status' => match ($item->connection_status) {
                'ok' => 'success',
                'failed' => 'failed',
                default => 'pending'
            },

            // 🔥 DATA (SIEMPRE DESDE job_offers)
           'registros' => $counts[$item->source]
    ?? $item->total_records_inserted
    ?? 0,

            // 🔥 MÉTRICAS INTERNAS
            'last_run_records' => $item->last_records_inserted ?? 0,
            'total_records' => $item->total_records_inserted ?? 0,

            'success_count' => $item->success_count,
            'fail_count' => $item->fail_count,

            'uptime' => ($item->success_count + $item->fail_count) > 0
                ? round(($item->success_count / ($item->success_count + $item->fail_count)) * 100, 2)
                : 0,

            'is_stale' => $isStale,
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
public function testApiData(Request $request)
{
    try {

        Log::info('🚀 Test API iniciado', [
            'api_url' => $request->api_url,
            'has_api_key' => !empty($request->api_key),
            'has_app_id' => !empty($request->app_id),
        ]);

        $apiUrl = $request->api_url;
        $apiKey = $request->api_key;
        $appId  = $request->app_id;

        if (!$apiUrl) {
            Log::warning('⚠️ API URL faltante');
            return response()->json([
                'message' => 'API URL requerida'
            ], 400);
        }

        $http = \Http::timeout(10);

        // 🔥 REQUEST
        if ($appId && $apiKey) {

            Log::info('🔐 Usando auth tipo Adzuna', [
                'app_id' => $appId
            ]);

            $response = $http->get($apiUrl, [
                'app_id' => $appId,
                'app_key' => $apiKey,
                'what' => 'developer',
                'results_per_page' => 5
            ]);

        } elseif ($apiKey) {

            Log::info('🔐 Usando Bearer token');

            $response = $http->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey
            ])->get($apiUrl, [
                'search' => 'developer'
            ]);

        } else {

            Log::info('🌐 Request sin autenticación');

            $response = $http->get($apiUrl, [
                'search' => 'developer'
            ]);
        }

        Log::info('📡 Response recibida', [
            'status' => $response->status(),
            'ok' => $response->successful()
        ]);

        if (!$response->successful()) {

            Log::error('❌ Error en API', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return response()->json([
                'message' => 'Error API',
                'status' => $response->status(),
                'body' => $response->body()
            ], 500);
        }

        $data = $response->json();

        Log::info('📦 Data recibida (primer nivel)', [
            'keys' => array_keys($data ?? [])
        ]);

        $jobs = [];

        // 🔥 Adzuna
        if (isset($data['results'])) {

            Log::info('📊 Detectado formato Adzuna', [
                'count' => count($data['results'])
            ]);

            foreach ($data['results'] as $job) {
                $jobs[] = [
                    'title' => $job['title'] ?? 'N/A',
                    'company' => $job['company']['display_name'] ?? 'N/A',
                    'location' => $job['location']['display_name'] ?? 'N/A',
                ];
            }

        }
        // 🔥 Remotive / otros
        elseif (isset($data['jobs'])) {

            Log::info('📊 Detectado formato Jobs', [
                'count' => count($data['jobs'])
            ]);

            foreach ($data['jobs'] as $job) {
                $jobs[] = [
                    'title' => $job['title'] ?? 'N/A',
                    'company' => $job['company_name'] ?? 'N/A',
                    'location' => $job['candidate_required_location'] ?? 'Remote',
                ];
            }

        } else {

            Log::warning('⚠️ Formato desconocido de API', [
                'data_sample' => $data
            ]);
        }

        Log::info('✅ Jobs procesados', [
            'total' => count($jobs)
        ]);

        return response()->json([
            'success' => true,
            'jobs' => $jobs
        ]);

    } catch (\Exception $e) {

        Log::error('💥 Error interno en testApiData', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'message' => 'Error interno',
            'error' => $e->getMessage()
        ], 500);
    }
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
