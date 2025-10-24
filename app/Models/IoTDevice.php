<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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
        'last_seen',
        'location',
        'description'
    ];

    protected $casts = [
        'last_seen' => 'datetime',
        'wifi_strength' => 'integer',
        'uptime' => 'integer',
        'free_memory' => 'integer'
    ];

    /**
     * Check if device is online (seen within last 5 minutes)
     */
    public function isOnline(): bool
    {
        return $this->last_seen && $this->last_seen->diffInMinutes(now()) <= 5;
    }

    /**
     * Get formatted uptime
     */
    public function getFormattedUptimeAttribute(): string
    {
        if (!$this->uptime) return 'Unknown';
        
        $hours = floor($this->uptime / 3600);
        $minutes = floor(($this->uptime % 3600) / 60);
        
        return "{$hours}h {$minutes}m";
    }

    /**
     * Get WiFi signal strength description
     */
    public function getWifiStrengthDescriptionAttribute(): string
    {
        if (!$this->wifi_strength) return 'Unknown';
        
        if ($this->wifi_strength >= -50) return 'Excellent';
        if ($this->wifi_strength >= -60) return 'Good';
        if ($this->wifi_strength >= -70) return 'Fair';
        return 'Poor';
    }
}
