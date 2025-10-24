<?php

namespace App\Events;

use App\Models\AccessLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AccessAttemptLogged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $accessLog;

    public function __construct(AccessLog $accessLog)
    {
        $this->accessLog = $accessLog;
    }

    public function broadcastOn()
    {
        return new Channel('access-logs');
    }

    public function broadcastAs()
    {
        return 'access.attempt.logged';
    }

    public function broadcastWith()
    {
        return [
            'access_log' => [
                'id' => $this->accessLog->id,
                'user' => $this->accessLog->user,
                'type' => $this->accessLog->type,
                'timestamp' => $this->accessLog->timestamp,
                'status' => $this->accessLog->status,
                'role' => $this->accessLog->role,
                'device' => $this->accessLog->device,
            ]
        ];
    }
}
