<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    public function definition(): array
    {
        $createdAt = fake('es_ES')->dateTimeBetween('-7 days', 'now');

        return [
            'message' => fake('es_ES')->randomElement([
                'Buenos días equipo, ¿alguien puede revisar el TK pendiente de Legal?',
                'Ya derivé la solicitud de traspaso a Titulación.',
                'El cliente de la Mz. C Lt. 14 está en recepción.',
                'Recuerden registrar el canal en cada solicitud.',
                '¿Quién tiene la agenda de visitas a proyecto de mañana?',
                'Listo, ya quedó atendido.',
                'Contabilidad confirma que el EE.CC se envía hoy por correo.',
                'Hay tres clientes esperando para recojo de contrato.',
            ]),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }
}
