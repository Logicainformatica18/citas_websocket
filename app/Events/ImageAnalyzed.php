<?php

namespace App\Events;

use App\Models\ImageAnalysis;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class ImageAnalyzed implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public string $model = 'Analysis';
    public string $action = 'created';
    public array $data;

    public function __construct(ImageAnalysis $analysis)
    {
        $this->data = $analysis->toArray(); // 👈 importante: convertir a array para enviar
    }

    public function broadcastOn(): Channel
    {
        return new Channel('analyses'); // 👈 usa el canal que escucha tu frontend
    }

    public function broadcastAs(): string
    {
        return 'record.changed'; // 👈 nombre esperado por Echo
    }
}
