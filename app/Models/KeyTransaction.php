<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeyTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_key_id',
        'user_name',
        'user_rfid_uid',
        'user_fingerprint_id',
        'action',
        'transaction_time',
        'device',
        'notes'
    ];

    protected $casts = [
        'transaction_time' => 'datetime',
        'user_fingerprint_id' => 'integer'
    ];

    /**
     * Get the lab key for this transaction
     */
    public function labKey()
    {
        return $this->belongsTo(LabKey::class);
    }

    /**
     * Get the authorized user who made this transaction
     */
    public function authorizedUser()
    {
        return $this->belongsTo(AuthorizedUser::class, 'user_rfid_uid', 'rfid_uid')
                    ->orWhere('user_fingerprint_id', $this->user_fingerprint_id);
    }

    /**
     * Get photos associated with this transaction
     */
    public function photos()
    {
        return $this->hasMany(EventPhoto::class);
    }

    /**
     * Scope for checkout transactions
     */
    public function scopeCheckouts($query)
    {
        return $query->where('action', 'checkout');
    }

    /**
     * Scope for checkin transactions
     */
    public function scopeCheckins($query)
    {
        return $query->where('action', 'checkin');
    }

    /**
     * Scope for today's transactions
     */
    public function scopeToday($query)
    {
        return $query->whereDate('transaction_time', today());
    }

    /**
     * Get formatted transaction time
     */
    public function getFormattedTimeAttribute(): string
    {
        return $this->transaction_time->format('M j, Y g:i A');
    }
}
