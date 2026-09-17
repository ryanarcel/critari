<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OAuthState extends Model
{
    protected $connection = 'landlord';

    protected $fillable = [
        'token',
        'tenant_host',
        'intended_role',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
