<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnomalyDetected implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $sessionId;
    public array $anomaly;

    /**
     * Create a new event instance.
     */
    public function __construct(int $sessionId, array $anomaly)
    {
        $this->sessionId = $sessionId;
        $this->anomaly = $anomaly;
    }

    /**
     * Get the channels the event should broadcast on.
     * Note: Student blindness strictly preserved; broadcasts solely to instructor monitoring.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('instructor.monitoring.' . $this->sessionId),
        ];
    }

    /**
     * Broadcast event alias name.
     */
    public function broadcastAs(): string
    {
        return 'anomaly.detected';
    }

    /**
     * Get data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'anomaly' => $this->anomaly,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
