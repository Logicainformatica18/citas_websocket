<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ChatHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'user_id',
        'report_query_id',
        'user_message',
        'ai_response',
        'context',
        'source',
        'language',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    /**
     * 🔹 Boot model: asigna un UUID si no existe
     */
    protected static function booted()
    {
        static::creating(function ($chat) {
            if (empty($chat->session_id)) {
                $chat->session_id = (string) Str::uuid();
            }
        });
    }

    /**
     * 🔹 Relación: Usuario (cuando haya login)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 🔹 Relación: Pregunta o reporte (opcional)
     */
    public function reportQuery()
    {
        return $this->belongsTo(ReportQuery::class);
    }

    /**
     * 🔹 Scope: Filtrar por sesión (para IA persistente)
     */
    public function scopeBySession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * 🔹 Scope: Filtrar por usuario autenticado
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 🔹 Scope: Últimos mensajes (para continuar una conversación)
     */
    public function scopeRecent($query, int $limit = 20)
    {
        return $query->latest()->limit($limit);
    }

    /**
     * 🔹 Método auxiliar: obtener último mensaje del usuario
     */
    public static function lastMessage(string $sessionId)
    {
        return static::where('session_id', $sessionId)
            ->latest()
            ->first();
    }

    /**
     * 🔹 Método auxiliar: registrar nueva interacción IA
     */
    public static function logInteraction(array $data)
    {
        return static::create([
            'session_id'      => $data['session_id'] ?? (string) Str::uuid(),
            'user_id'         => $data['user_id'] ?? null,
            'report_query_id' => $data['report_query_id'] ?? null,
            'user_message'    => $data['user_message'],
            'ai_response'     => $data['ai_response'] ?? null,
            'context'         => $data['context'] ?? [],
            'source'          => $data['source'] ?? 'dashboard',
            'language'        => $data['language'] ?? 'es',
        ]);
    }
}
