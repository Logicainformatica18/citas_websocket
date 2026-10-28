<?php

namespace App\Events;

use App\Models\Syllabus;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

/**
 * Un sílabo terminó de procesarse y los pivotes de su curso cambiaron, así que la
 * alineación del curso (calculada al vuelo) ya refleja el sílabo nuevo.
 */
class SyllabusProcessed implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public array $data;

    public function __construct(Syllabus $syllabus)
    {
        // Sin raw_text ni content: pueden ser grandes y el frontend solo necesita refrescar.
        $this->data = $syllabus->only([
            'id', 'source', 'status', 'course_id', 'course_code', 'course_name',
            'external_model', 'external_id', 'external_version', 'structured_data',
        ]);
    }

    public function broadcastOn(): Channel
    {
        return new Channel('observatory');
    }

    public function broadcastAs(): string
    {
        return 'syllabus.processed';
    }
}
