<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Submission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        Log::info('Dashboard - request received', [
            'session_id' => session()->getId(),
            'auth_check' => Auth::check(),
            'auth_id' => Auth::id(),
            'session_data_keys' => array_keys(session()->all()),
        ]);

        $user = Auth::user();

        if (! $user) {
            Log::warning('Dashboard accessed without authenticated user');

            return redirect()->route('login');
        }

        $assignments = Assignment::query()
            ->with('questions')
            ->where('created_by', $user->id)
            ->whereNull('demo_id')
            ->orderByDesc('created_at')
            ->get();

        $totalPapersGraded = Submission::whereIn('assignment_id', $assignments->pluck('id'))
            ->where('status', 'graded')
            ->count();

        $activeAssignmentsCount = $assignments->count();

        $minutesSaved = $totalPapersGraded * 3;
        $hoursSaved = round($minutesSaved / 60, 1);

        $assignmentsList = $assignments->map(function ($assignment) {
            $submissions = Submission::where('assignment_id', $assignment->id)->get();
            $gradedCount = $submissions->where('status', 'graded')->count();
            $totalCount = $submissions->count();

            if ($totalCount === 0) {
                $status = 'Draft';
            } elseif ($gradedCount === 0) {
                $status = 'Draft';
            } elseif ($gradedCount < $totalCount) {
                $status = 'Grading';
            } else {
                $status = 'Graded';
            }

            return [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'class' => null,
                'submissions_completed' => $gradedCount,
                'submissions_total' => $totalCount,
                'status' => $status,
                'created_at' => $assignment->created_at,
            ];
        })->values();

        $recentRubrics = $assignments->take(3)->map(function ($assignment) {
            return [
                'id' => $assignment->id,
                'name' => $assignment->title,
                'description' => $assignment->formattedPrompts(),
            ];
        })->values();

        return Inertia::render('Dashboard', [
            'user' => [
                'name' => $user->name,
                'school' => 'Lincoln High School',
                'department' => 'English Department',
                'avatar' => $user->name[0] ?? 'A',
            ],
            'stats' => [
                'totalPapersGraded' => $totalPapersGraded,
                'activeAssignments' => $activeAssignmentsCount,
                'timeSavedHours' => $hoursSaved,
            ],
            'assignments' => $assignmentsList,
            'recentRubrics' => $recentRubrics,
        ]);
    }
}
