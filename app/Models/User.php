<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'email', 'phone', 'matrix_number', 'role', 'rfid_uid', 'fingerprint_id', 'iot_access', 'notes', 'password'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login' => 'datetime',
            'iot_access' => 'boolean',
            'fingerprint_id' => 'integer',
        ];
    }

    /**
     * Get access logs for this user
     */
    public function accessLogs()
    {
        return $this->hasMany(AccessLog::class, 'user', 'name');
    }

    /**
     * Get key transactions for this user
     */
    public function keyTransactions()
    {
        return $this->hasMany(KeyTransaction::class, 'user_name', 'name');
    }

    /**
     * Check if user has IoT access
     */
    public function hasIoTAccess(): bool
    {
        return $this->iot_access && ($this->rfid_uid || $this->fingerprint_id);
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
