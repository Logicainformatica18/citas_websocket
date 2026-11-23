<?php

namespace Database\Factories;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 *
 * Requiere support_detail_id y user_id. internal_state_id registra el estado
 * interno elegido al comentar (funciona como historial de estados).
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'comment' => fake('es_ES')->randomElement([
                'Se contactó al cliente por WhatsApp, queda pendiente su respuesta.',
                'Se revisó el expediente del lote, la documentación está completa.',
                'Cliente indica que volverá a comunicarse la próxima semana.',
                'Se derivó al área correspondiente para su evaluación.',
            ]),
            'internal_state_id' => 3,
        ];
    }
}
