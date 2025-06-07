<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\MessageSent;

class WebSocketTestController extends Controller
{
    public function send()
    {
        event(new MessageSent("¡Hola desde Reverb!"));
        return response()->json(['success' => true, 'message' => 'Evento emitido']);
    }
}
