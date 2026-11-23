<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Proceso en cola del módulo de ofertas (registro y progreso):
 *   apply    marcar ofertas excluidas por título y recalcular los rankings
 *   analyze  "Analizar basura" (simulación con la lista actual)
 *   delete   "Eliminar definitivamente" (con respaldo en job_offer_deletion_backups)
 *   restore  revertir una eliminación desde su respaldo
 *   suggest  "Sugerir con IA"
 *   export   export a Excel
 */
class JobOfferProcess extends Model
{
    protected $table = 'job_offer_processes';

    protected $fillable = [
        'type', 'status', 'user_id', 'user_name', 'params', 'total', 'processed', 'cursor_id',
        'state', 'result', 'message', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'params' => 'array',
        'state' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public const LABELS = [
        'apply' => 'Excluir de los indicadores',
        'analyze' => 'Analizar basura',
        'delete' => 'Eliminar definitivamente',
        'restore' => 'Restaurar eliminación',
        'suggest' => 'Sugerir con IA',
        'export' => 'Exportar Excel',
    ];

    public static function start(string $type, array $params = []): self
    {
        $user = auth()->user();

        return self::create([
            'type' => $type,
            'status' => 'queued',
            'user_id' => $user?->id,
            'user_name' => $user ? self::userLabel($user) : (app()->runningInConsole() ? 'consola' : null),
            'params' => $params,
        ]);
    }

    /** Nombre legible del usuario (users no tiene 'name': firstname/lastname, names o email). */
    public static function userLabel($user): ?string
    {
        if ($user === null) {
            return null;
        }
        $full = trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? ''));

        return mb_substr($full !== '' ? $full : ($user->names ?? $user->email ?? "#{$user->id}"), 0, 255);
    }

    /** Otro proceso del mismo grupo en curso (los de más de 15 min sin avance se consideran caídos). */
    public static function busy(array $types): ?self
    {
        return self::whereIn('type', $types)
            ->whereIn('status', ['queued', 'running'])
            ->where('updated_at', '>', now()->subMinutes(15))
            ->latest('id')
            ->first();
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['done', 'failed'], true);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'label' => self::LABELS[$this->type] ?? $this->type,
            'status' => $this->status,
            'user_name' => $this->user_name,
            'params' => $this->params,
            'total' => $this->total,
            'processed' => $this->processed,
            'percent' => $this->total > 0 ? min(100, round($this->processed * 100 / $this->total, 1)) : ($this->status === 'done' ? 100 : 0),
            'result' => $this->result,
            'message' => $this->message,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'started_at' => $this->started_at?->format('Y-m-d H:i:s'),
            'finished_at' => $this->finished_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
