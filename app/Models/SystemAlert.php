<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'device',
        'alert_type',
        'severity',
        'title',
        'description',
        'status',
        'user_name',
        'alert_time',
        'acknowledged_at',
        'resolved_at',
        'acknowledged_by',
        'resolution_notes'
    ];

    protected $casts = [
        'alert_time' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope for active alerts
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for acknowledged alerts
     */
    public function scopeAcknowledged($query)
    {
        return $query->where('status', 'acknowledged');
    }

    /**
     * Scope for resolved alerts
     */
    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    /**
     * Scope for alerts by severity
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope for critical alerts
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    /**
     * Scope for alerts by device
     */
    public function scopeByDevice($query, string $device)
    {
        return $query->where('device', $device);
    }

    /**
     * Scope for recent alerts
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('alert_time', '>=', now()->subHours($hours));
    }

    /**
     * Mark alert as acknowledged
     */
    public function acknowledge(string $acknowledgedBy): bool
    {
        return $this->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by' => $acknowledgedBy
        ]);
    }

    /**
     * Mark alert as resolved
     */
    public function resolve(string $resolvedBy, ?string $notes = null): bool
    {
        return $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'acknowledged_by' => $resolvedBy,
            'resolution_notes' => $notes
        ]);
    }

    /**
     * Check if alert is unresolved
     */
    public function isUnresolved(): bool
    {
        return $this->status !== 'resolved';
    }

    /**
     * Get formatted severity badge color
     */
    public function getSeverityColorAttribute(): string
    {
        return match($this->severity) {
            'low' => 'blue',
            'medium' => 'yellow',
            'high' => 'orange',
            'critical' => 'red',
            default => 'gray'
        };
    }
}
