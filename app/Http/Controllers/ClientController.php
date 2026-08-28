<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Survey;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function store(Request $request, $id)
    {
        $survey = Survey::findOrFail($id);
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

        return response()->json(['client_id' => Client::create()->id]);
    }
}