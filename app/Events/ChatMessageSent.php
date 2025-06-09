<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ChatMessageSent implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ChatMessage $chatMessage;

    public function __construct(ChatMessage $chatMessage)
    {
        $this->chatMessage = $chatMessage;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('chat-room');
    }

    public function broadcastAs(): string
    {
        return 'chat.message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->chatMessage->id,
            'message' => $this->chatMessage->message,
            'created_at' => $this->chatMessage->created_at->toDateTimeString(),
            'user' => [
                'id' => $this->chatMessage->user->id,
                'email' => $this->chatMessage->user->email,
                'name' => $this->chatMessage->user->firstname . ' ' . $this->chatMessage->user->lastname,
                'role_name' => $this->chatMessage->user->getRoleNames()->first() ?? '-',
            ],
        ];
    }
}
