<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthorizedUser extends Model
{
    use HasFactory;

    protected $table = 'authorized_users';

    protected $fillable = [
        'name',
        'email',
        'rfid_uid',
        'fingerprint_id',
        'role',
        'is_active',
        'created_by',
        'notes'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'fingerprint_id' => 'integer'
    ];

    /**
     * Get access logs for this user
     */
    public function accessLogs()
    {
        return $this->hasMany(AccessLog::class, 'user', 'name');
    }

    /**
     * Get the user who created this authorized user
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for active users only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get formatted RFID UID
     */
    public function getFormattedRfidUidAttribute(): string
    {
        if (!$this->rfid_uid) return 'Not Set';
        
        return strtoupper(chunk_split($this->rfid_uid, 2, ':'));
    }
}
