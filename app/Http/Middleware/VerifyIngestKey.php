<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Autenticación de silabos_v2: cabecera X-Api-Key contra config('silabos_v2.ingest_key').
 * Sin clave configurada se rechaza todo (nunca se compara null con null).
 */
class VerifyIngestKey
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('silabos_v2.ingest_key');
        $provided = (string) $request->header('X-Api-Key');

        if ($expected === '') {
            Log::warning('Ingesta de sílabos rechazada: SILABOS_INGEST_API_KEY no está configurada.');
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($provided === '' || !hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
