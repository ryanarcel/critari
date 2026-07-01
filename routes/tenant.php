<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware(['web', InitializeTenancyBySubdomain::class, PreventAccessFromCentralDomains::class])
    ->group(function () {
        Route::get('/', fn () => Inertia::render('Welcome'))->name('home');
    });
