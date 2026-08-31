<?php

namespace App\Http\Controllers;

use App\Helpers\MarcaEncuesta;
use App\Models\Client;
use App\Models\Survey;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class ClientController extends Controller
{
    public function store(Request $request, $id)
    {
        $survey = Survey::findOrFail($id);

        // Bloqueo por dispositivo, antes que cualquier otra cosa.
        //
        // Sin esto, alguien que ya terminó podría pedir un client_id nuevo y
        // arrancar de cero: la encuesta se le negaría recién en el primer
        // POST de respuesta, pero ya habría quedado una fila huérfana en
        // `clients` que el reporte cuenta como abandono.
        if (MarcaEncuesta::yaRespondio($request, (int) $survey->id)) {
            return response()->json([
                'message' => 'Ya registramos una respuesta desde este dispositivo.',
            ], 403);
        }

        $data = $request->validate([
            'state' => 'required|in:public,private',
            'code' => 'nullable|string',
        ]);

        if ($survey->date_end && Carbon::today()->gt(Carbon::parse($survey->date_end))) {
            return response()->json(['message' => 'Esta encuesta ya finalizó.'], 422);
        }

        if ($survey->state === 'private' && $data['state'] !== 'private') {
            return response()->json(['message' => 'Esta encuesta es privada.'], 422);
        }

        if ($survey->state === 'private' && ($data['code'] ?? '') !== $survey->password) {
            return response()->json(['message' => 'El código de acceso no es válido.'], 422);
        }

        $client = Client::create();

        // Cookie de sesión en curso.
        //
        // Guarda el client_id recién creado para que, si el encuestado
        // abandona a la mitad y vuelve, el asistente pueda retomar en la
        // primera pregunta sin responder en vez de empezar de nuevo.
        //
        // Es también lo que autoriza el GET de progreso: ese endpoint exige
        // que el client_id de la URL coincida con el de esta cookie, así no
        // queda un lector público de respuestas por id consecutivo.
        Cookie::queue(MarcaEncuesta::cookieSesion((int) $survey->id, (int) $client->id));

        return response()->json(['client_id' => $client->id]);
    }
}
