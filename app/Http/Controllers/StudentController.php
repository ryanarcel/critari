<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    /**
     * Display the student home page.
     */
    public function home(): Response
    {
        $user = Auth::user();

        return Inertia::render('Student/Home', [
            'status' => session('status'),
            'user' => [
                'name' => $user?->name,
            ],
        ]);
    }
}
