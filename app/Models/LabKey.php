<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'key_name',
        'description', 
        'location',
        'status',
        'key_rfid_uid', // This is the field name in your database
        'is_active',
        'last_used_at'
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    /**
     * Get the current transactions for this key
     */
    public function transactions()
    {
        return $this->hasMany(KeyTransaction::class);
    }

    /**
     * Get the latest transaction for this key
     */
    public function latestTransaction()
    {
        return $this->hasOne(KeyTransaction::class)->latest('transaction_time');
    }

    /**
     * Get the current holder of the key
     */
    public function getCurrentHolder()
    {
        $lastCheckout = $this->transactions()
            ->where('action', 'checkout')
            ->latest('transaction_time')
            ->first();

        if ($lastCheckout) {
            $lastCheckin = $this->transactions()
                ->where('action', 'checkin')
                ->where('transaction_time', '>', $lastCheckout->transaction_time)
                ->latest('transaction_time')
                ->first();

            if (!$lastCheckin) {
                return $lastCheckout->user_name;
            }
        }

        return 'Available';
    }

    /**
     * Scope for available keys
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Scope for checked out keys
     */
    public function scopeCheckedOut($query)
    {
        return $query->where('status', 'checked_out');
    }

    /**
     * Scope for active keys
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}