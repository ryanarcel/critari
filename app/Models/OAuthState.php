<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OAuthState extends Model
{
    protected $fillable = ['token', 'tenant_host', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
