<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IoTDevice extends Model
{
    use HasFactory;

    protected $table = 'iot_devices';

    protected $fillable = [
        'terminal_name',
        'device_type',
        'status',
        'ip_address',
        'wifi_strength',
        'uptime',
        'free_memory',
        'last_seen'
    ];

    protected $casts = [
        'last_seen' => 'datetime',
        'wifi_strength' => 'integer',
        'free_memory' => 'integer'
    ];

    /**
     * Get the device status color for UI
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'online' => 'green',
            'offline' => 'red',
            'error' => 'yellow',
            default => 'gray',
        };
    }

    /**
     * Get the device icon based on type
     */
    public function getDeviceIconAttribute()
    {
        return match($this->device_type) {
            'camera' => '📷',
            'access_control' => '🔒',
            'sensor' => '📡',
            default => '🔧',
        };
    }

    /**
     * Check if device is currently online (seen in last 2 minutes)
     */
    public function getIsCurrentlyOnlineAttribute()
    {
        if (!$this->last_seen) {
            return false;
        }
        
        return $this->last_seen->gt(now()->subMinutes(2));
    }
}