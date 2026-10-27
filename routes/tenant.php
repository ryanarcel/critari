<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaperSubmissionController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([InitializeTenancyBySubdomain::class, PreventAccessFromCentralDomains::class, 'web'])
    ->group(function () {
        // OAuth verification endpoint (no auth required)
        Route::get('/auth/oauth/verify', [SocialiteController::class, 'verify'])->name('auth.oauth.verify');

        Route::get('/', fn () => Inertia::render('Welcome'))->name('home');

        Route::get('/demos', function () {
            return Inertia::render('Demo/Index');
        })->name('demos.index');

        Route::get('/demos/{sessionId}', function ($sessionId) {
            return Inertia::render('Demo/Index', ['sessionId' => $sessionId]);
        })->name('demos.session');

        Route::post('/assignments/ai-rubric-suggestion', [AssignmentController::class, 'getAIRubricSuggestion'])
            ->name('assignments.ai-rubric-suggestion');
        Route::post('/assignments/ai-levels-suggestion', [AssignmentController::class, 'getAILevelsSuggestion'])
            ->name('assignments.ai-levels-suggestion');
        Route::get('/assignments/create', [AssignmentController::class, 'create'])
            ->middleware('auth')
            ->name('assignments.create');
        Route::get('/assignments/{assignment}', [AssignmentController::class, 'show'])
            ->middleware('auth')
            ->name('assignments.show');
        Route::resource('assignments', AssignmentController::class)->except(['create', 'show']);
        Route::resource('submissions', SubmissionController::class);
        Route::post('/submissions/{submission}/assess', [SubmissionController::class, 'processAIAssessment'])
            ->name('submissions.assess');

        // Dashboard requires authentication
        Route::middleware(['auth'])->group(function () {
            Route::post('/tenant/logout', [AuthenticatedSessionController::class, 'destroy'])
                ->name('tenant.logout');

            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
            Route::post('/assignments/{assignment}/paper-ocr', [PaperSubmissionController::class, 'ocrPhoto'])
                ->name('assignments.paper-ocr');
            Route::post('/assignments/{assignment}/paper-submissions', [PaperSubmissionController::class, 'store'])
                ->name('assignments.paper-submissions.store');
            Route::post('/assignments/{assignment}/paper-submissions/{submission}/ocr', [PaperSubmissionController::class, 'ocr'])
                ->name('assignments.paper-submissions.ocr');
            Route::put('/assignments/{assignment}/paper-submissions/{submission}', [PaperSubmissionController::class, 'update'])
                ->name('assignments.paper-submissions.update');
            Route::get('/student', [StudentController::class, 'home'])->name('student.home');
            Route::post('/student/join', [StudentController::class, 'join'])->name('student.join');
            Route::get('/student/assignments/{assignment}', [StudentController::class, 'show'])
                ->name('student.assignments.show');
            Route::post('/student/assignments/{assignment}', [StudentController::class, 'submit'])
                ->name('student.assignments.submit');
        });
    });
