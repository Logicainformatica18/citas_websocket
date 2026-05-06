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

    $sources = SourceStatus::orderByDesc('created_at')
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

            // 🔥 MÉTRICAS INTERNfAS
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
public function details($id)
{
    try {

        $source = SourceStatus::findOrFail($id);

        return response()->json([
            'success' => true,

            'source' => [

                'id' => $source->id,

                'source' => $source->source,

                'last_run_id' => $source->last_run_id,

                'last_status' => $source->last_status,

                'last_started_at' => $source->last_started_at,

                'last_finished_at' => $source->last_finished_at,

                'last_duration_seconds' =>
                    $source->last_duration_seconds,

                'last_records_found' =>
                    $source->last_records_found,

                'last_records_inserted' =>
                    $source->last_records_inserted,

                'last_records_skipped' =>
                    $source->last_records_skipped,

                'last_error' =>
                    $source->last_error,

                'last_success_at' =>
                    $source->last_success_at,

                'last_failed_at' =>
                    $source->last_failed_at,

                'fail_count' =>
                    $source->fail_count,

                'success_count' =>
                    $source->success_count,

                'api_url' =>
                    $source->api_url,

                'api_key' =>
                    $source->api_key,

                'app_id' =>
                    $source->app_id,

                'connection_status' =>
                    $source->connection_status,

                'last_connection_check' =>
                    $source->last_connection_check,

                'connection_error' =>
                    $source->connection_error,

                'created_at' =>
                    $source->created_at,

                'updated_at' =>
                    $source->updated_at,

                'total_records_found' =>
                    $source->total_records_found,

                'total_records_inserted' =>
                    $source->total_records_inserted,

                'total_records_skipped' =>
                    $source->total_records_skipped,

                // 🔥 UPTIME
                'uptime' => (
                    ($source->success_count + $source->fail_count) > 0
                )
                    ? round(
                        (
                            $source->success_count /
                            (
                                $source->success_count +
                                $source->fail_count
                            )
                        ) * 100,
                        2
                    )
                    : 0,
            ]
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,
            'message' => 'Error al obtener detalles',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function testApi(Request $request)
{
    try {

        $apiUrl = $request->api_url;
        $apiKey = $request->api_key;
        $appId  = $request->app_id;

        if (!$apiUrl) {

            return response()->json([
                'success' => false,
                'message' => 'API URL requerida'
            ], 400);
        }

        $http = \Http::timeout(15)
            ->acceptJson();

        // 🔐 AUTH APP_ID + KEY
        if ($appId && $apiKey) {

            $response = $http->get($apiUrl, [
                'app_id' => $appId,
                'app_key' => $apiKey,
            ]);

        }
        // 🔐 BEARER TOKEN
        elseif ($apiKey) {

            $response = $http
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey
                ])
                ->get($apiUrl);

        }
        // 🌐 PUBLIC API
        else {

            $response = $http->get($apiUrl);
        }

        // ❌ ERROR RESPONSE
        if (!$response->successful()) {

            // 🔒 PRIVADA
            if (in_array($response->status(), [401, 403])) {

                return response()->json([
                    'success' => false,
                    'message' => 'API privada o requiere autenticación',
                    'status' => $response->status()
                ], 403);
            }

            return response()->json([
                'success' => false,
                'message' => 'La API respondió con error',
                'status' => $response->status(),
                'body' => $response->body()
            ], 500);
        }

        $json = $response->json();

        // 🔥 NORMALIZAR DATOS
        $jobs = [];

        // Arbeitnow
        if (isset($json['data'])) {

            foreach (array_slice($json['data'], 0, 10) as $job) {

                $jobs[] = [
                    'title' => $job['title'] ?? 'N/A',

                    'company' =>
                        $job['company_name']
                        ?? 'N/A',

                    'location' =>
                        $job['location']
                        ?? 'Remote',
                ];
            }
        }

        // Remotive
        elseif (isset($json['jobs'])) {

            foreach (array_slice($json['jobs'], 0, 10) as $job) {

                $jobs[] = [
                    'title' => $job['title'] ?? 'N/A',

                    'company' =>
                        $job['company_name']
                        ?? 'N/A',

                    'location' =>
                        $job['candidate_required_location']
                        ?? 'Remote',
                ];
            }
        }

        // ARRAY DIRECTO
        elseif (is_array($json)) {

            foreach (array_slice($json, 0, 10) as $job) {

                $jobs[] = [
                    'title' =>
                        $job['title']
                        ?? $job['name']
                        ?? 'N/A',

                    'company' =>
                        $job['company']
                        ?? $job['company_name']
                        ?? 'N/A',

                    'location' =>
                        $job['location']
                        ?? 'Remote',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'API funcionando correctamente',
            'status' => $response->status(),
            'total' => count($jobs),
            'data' => $jobs
        ]);

    } catch (\Illuminate\Http\Client\ConnectionException $e) {

        return response()->json([
            'success' => false,
            'message' => 'No se pudo conectar a la API'
        ], 500);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,
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
