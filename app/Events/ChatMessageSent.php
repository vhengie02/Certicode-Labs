<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $sessionId;
    public array $chat;

    /**
     * Create a new event instance.
     */
    public function __construct(int $sessionId, array $chat)
    {
        $this->sessionId = $sessionId;
        $this->chat = $chat;
    }

    /**
     * Get the channels the event should broadcast on.
     * Note: Instructor monitoring strictly excluded per specification.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('lab-session.' . $this->sessionId . '.chat'),
        ];
    }

    /**
     * Broadcast event alias name.
     */
    public function broadcastAs(): string
    {
        return 'chat.message';
    }

    /**
     * Get data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'chat' => $this->chat,
        ];
    }
}
