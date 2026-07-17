<?php

use App\Http\Controllers\AssignmentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::get('/demos', function () {
    return Inertia::render('Demo/Index');
})->name('demos.index');

Route::middleware(['web', InitializeTenancyBySubdomain::class, PreventAccessFromCentralDomains::class])
    ->group(function () {
        Route::get('/', fn () => Inertia::render('Welcome'))->name('home');
        Route::resource('assignments', AssignmentController::class);
        Route::post('/assignments/ai-rubric-suggestion', [AssignmentController::class, 'getAIRubricSuggestion'])
            ->name('assignments.ai-rubric-suggestion');

    });
