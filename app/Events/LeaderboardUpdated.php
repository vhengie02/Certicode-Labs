<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeaderboardUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $sessionId;
    public array $leaderboard;

    /**
     * Create a new event instance.
     */
    public function __construct(int $sessionId, array $leaderboard)
    {
        $this->sessionId = $sessionId;
        $this->leaderboard = $leaderboard;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('lab-session.' . $this->sessionId),
            new PrivateChannel('instructor.monitoring.' . $this->sessionId),
        ];
    }

    /**
     * Broadcast event alias name.
     */
    public function broadcastAs(): string
    {
        return 'leaderboard.updated';
    }

    /**
     * Get data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'leaderboard' => $this->leaderboard,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
