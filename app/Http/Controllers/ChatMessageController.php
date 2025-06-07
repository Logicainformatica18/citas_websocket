<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ChatMessageController extends Controller
{
    /**
     * Muestra la vista del chat (solo si decides usar una página exclusiva).
     */
    public function index()
    {
        return Inertia::render('Chat/Index');
    }

    /**
     * Devuelve los últimos mensajes del chat.
     */
    public function fetch()
    {
        $messages = ChatMessage::with('user')->latest()->take(50)->get()->reverse()->values();

        // Adjuntar rol manualmente a cada usuario
        $messages->each(function ($msg) {
            $msg->user->role_name = $msg->user->getRoleNames()->first(); // usando Spatie
        });

        return response()->json($messages);
    }

    /**
     * Almacena un nuevo mensaje de chat.
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

        // Adjuntar rol al usuario para el evento
        $chatMessage->load('user');
        $chatMessage->user->role_name = $user->getRoleNames()->first();

        broadcast(new ChatMessageSent($chatMessage))->toOthers();
\Log::info('Broadcasting message: ', $chatMessage->toArray());

        return response()->json([
            'message' => '✅ Mensaje enviado',
            'data' => $chatMessage,
        ]);
    }
}
