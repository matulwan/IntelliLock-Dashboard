<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EventPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'access_log_id',
        'key_transaction_id',
        'photo_path',
        'device',
        'event_type',
        'notes'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the access log associated with this photo
     */
    public function accessLog()
    {
        return $this->belongsTo(AccessLog::class);
    }

    /**
     * Get the key transaction associated with this photo
     */
    public function keyTransaction()
    {
        return $this->belongsTo(KeyTransaction::class);
    }

    /**
     * Get the full URL to the photo
     */
    public function getPhotoUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->photo_path);
    }

    /**
     * Get the full path to the photo
     */
    public function getFullPathAttribute(): string
    {
        return Storage::disk('public')->path($this->photo_path);
    }

    /**
     * Check if photo file exists
     */
    public function photoExists(): bool
    {
        return Storage::disk('public')->exists($this->photo_path);
    }

    /**
     * Delete photo file when model is deleted
     */
    protected static function booted()
    {
        static::deleting(function ($photo) {
            if ($photo->photoExists()) {
                Storage::disk('public')->delete($photo->photo_path);
            }
        });
    }

    /**
     * Scope for photos by event type
     */
    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope for photos by device
     */
    public function scopeByDevice($query, string $device)
    {
        return $query->where('device', $device);
    }

    /**
     * Scope for recent photos
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
