<?php

namespace App\Events;

use App\Models\TransactionLine;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionLineCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public TransactionLine $line;

    /**
     * Create a new event instance.
     */
    public function __construct(TransactionLine $line)
    {
        $this->line = $line;
    }

    /**
     * The name of the channel the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('transactions');
    }

    public function broadcastAs(): string
    {
        return 'line.created';
    }
}
