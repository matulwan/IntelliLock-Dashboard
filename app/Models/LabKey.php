<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabKey extends Model
{
    use HasFactory;

    protected $table = 'lab_keys';

    protected $fillable = [
        'key_name',
        'key_rfid_uid',
        'description',
        'status',
        'location',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    /**
     * Get all transactions for this key
     */
    public function transactions()
    {
        return $this->hasMany(KeyTransaction::class);
    }

    /**
     * Get the latest transaction
     */
    public function latestTransaction()
    {
        return $this->hasOne(KeyTransaction::class)->latest('transaction_time');
    }

    /**
     * Get current holder of the key
     */
    public function getCurrentHolder()
    {
        $latestCheckout = $this->transactions()
            ->where('action', 'checkout')
            ->latest('transaction_time')
            ->first();

        if (!$latestCheckout) return null;

        // Check if key was returned after this checkout
        $returnAfterCheckout = $this->transactions()
            ->where('action', 'checkin')
            ->where('transaction_time', '>', $latestCheckout->transaction_time)
            ->exists();

        return $returnAfterCheckout ? null : $latestCheckout->user_name;
    }

    /**
     * Check if key is currently available
     */
    public function isAvailable(): bool
    {
        return $this->status === 'available' && $this->getCurrentHolder() === null;
    }

    /**
     * Scope for available keys
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available')->where('is_active', true);
    }

    /**
     * Scope for checked out keys
     */
    public function scopeCheckedOut($query)
    {
        return $query->where('status', 'checked_out');
    }
}
