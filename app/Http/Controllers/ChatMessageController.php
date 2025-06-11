<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\User;

class ChatMessageController extends Controller
{
    /**
     * Vista del chat (opcional).
     */
   public function index()
{
    return Inertia::render('Chat/Index', [
        'auth' => [
            'user' => auth()->user(),
        ],
        'users' => User::select('id', 'name', 'email')->where('id', '!=', auth()->id())->get(),
    ]);
}

    /**
     * Obtener últimos mensajes.
     */
    public function fetch()
    {
        $messages = ChatMessage::with('user')->latest()->take(50)->get()->reverse()->values();

        // Adjuntar rol usando Spatie
        $messages->each(function ($msg) {
            $msg->user->role_name = $msg->user->getRoleNames()->first();
        });

        return response()->json($messages);
    }

    /**
     * Guardar y emitir mensaje por la cola.
     */
    public function store(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $user = Auth::user();

        $chatMessage = ChatMessage::create([
            'user_id' => $user->id,
            'message' => $request->message,
        ]);

        // Cargar la relación antes de pasarla al evento
        $chatMessage->load('user');

        // Emitir el evento (irá a la cola y luego se emitirá por Reverb)
        broadcast(new ChatMessageSent($chatMessage))->toOthers();

        return response()->json([
            'message' => '✅ Mensaje enviado',
            'data' => $chatMessage,
        ]);
    }
}
