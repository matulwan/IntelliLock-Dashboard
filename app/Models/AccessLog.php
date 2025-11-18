<?php

namespace App\Models; 

use Illuminate\Database\Eloquent\Model;

class AccessLog extends Model
{
    protected $fillable = [
        'action',
        'user',
        'key_name',
        'device',
        'status', // Add this field
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}