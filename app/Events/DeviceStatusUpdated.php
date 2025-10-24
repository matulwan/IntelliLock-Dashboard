<?php

namespace App\Events;

use App\Models\IoTDevice;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $device;

    public function __construct(IoTDevice $device)
    {
        $this->device = $device;
    }

    public function broadcastOn()
    {
        return new Channel('iot-devices');
    }

    public function broadcastAs()
    {
        return 'device.status.updated';
    }

    public function broadcastWith()
    {
        return [
            'device' => [
                'id' => $this->device->id,
                'terminal_name' => $this->device->terminal_name,
                'status' => $this->device->status,
                'ip_address' => $this->device->ip_address,
                'wifi_strength' => $this->device->wifi_strength,
                'uptime' => $this->device->uptime,
                'free_memory' => $this->device->free_memory,
                'last_seen' => $this->device->last_seen,
                'formatted_uptime' => $this->device->formatted_uptime,
                'wifi_strength_description' => $this->device->wifi_strength_description,
            ]
        ];
    }
}
