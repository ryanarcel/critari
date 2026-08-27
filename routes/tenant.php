<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware(['web', InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class])
    ->group(function () {
        Route::get('/', fn () => Inertia::render('Welcome'))->name('home');

        Route::get('/demos', function () {
            return Inertia::render('Demo/Index');
        })->name('demos.index');

        Route::get('/demos/{sessionId}', function ($sessionId) {
            return Inertia::render('Demo/Index', ['sessionId' => $sessionId]);
        })->name('demos.session');

        Route::resource('assignments', AssignmentController::class);
        Route::resource('submissions', SubmissionController::class);
        Route::post('/submissions/{submission}/assess', [SubmissionController::class, 'processAIAssessment'])
            ->name('submissions.assess');

        Route::post('/assignments/ai-rubric-suggestion', [AssignmentController::class, 'getAIRubricSuggestion'])
            ->name('assignments.ai-rubric-suggestion');
        Route::post('/assignments/ai-levels-suggestion', [AssignmentController::class, 'getAILevelsSuggestion'])
            ->name('assignments.ai-levels-suggestion');
    });
