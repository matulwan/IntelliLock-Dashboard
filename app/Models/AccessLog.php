<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user',
        'type',
        'timestamp',
        'status',
        'role',
        'device',
        'lab_key_id',
        'key_name'
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];

    /**
     * Get the lab key associated with this access log
     */
    public function labKey()
    {
        return $this->belongsTo(LabKey::class);
    }

    /**
     * Get photos associated with this access log
     */
    public function photos()
    {
        return $this->hasMany(EventPhoto::class);
    }

    /**
     * Get the display name for the accessed item
     * Returns key name if available, otherwise device name
     */
    public function getAccessedItemAttribute(): string
    {
        return $this->key_name ?? $this->device ?? 'Unknown';
    }

    /**
     * Scope for successful access
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope for denied access
     */
    public function scopeDenied($query)
    {
        return $query->where('status', 'denied');
    }

    /**
     * Scope for today's logs
     */
    public function scopeToday($query)
    {
        return $query->whereDate('timestamp', today());
    }
}
