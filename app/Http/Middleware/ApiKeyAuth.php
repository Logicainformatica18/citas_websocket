<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiKeyAuth
{
    public function handle(Request $request, Closure $next)
    {
        $provided = $request->bearerToken(); // lee "Authorization: Bearer ..."
        $expected = config('services.api.key'); // tu clave fija

        if ($provided !== $expected) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
