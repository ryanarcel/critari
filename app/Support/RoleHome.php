<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class RoleHome
{
    public static function path(?User $user = null): string
    {
        $user ??= Auth::user();

        return $user?->isStudent() ? '/student' : '/dashboard';
    }

    public static function routeName(?User $user = null): string
    {
        $user ??= Auth::user();

        return $user?->isStudent() ? 'student.home' : 'dashboard';
    }
}
