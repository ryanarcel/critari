<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Submission;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();
        
        // Get assignments created by this user (or where user has access)
        $assignments = Assignment::query()
            ->where('created_by', $user->id)
            ->orWhereNull('created_by') // Include assignments without explicit creator (legacy)
            ->orderByDesc('created_at')
            ->get();

        // Calculate statistics
        $totalPapersGraded = Submission::whereIn('assignment_id', $assignments->pluck('id'))
            ->where('status', 'graded')
            ->count();

        $activeAssignmentsCount = $assignments->count();

        // Estimate time saved: ~3 min per paper graded (average rubric grading time)
        $minutesSaved = $totalPapersGraded * 3;
        $hoursSaved = round($minutesSaved / 60, 1);

        // Get assignment details with submission counts
        $assignmentsList = $assignments->map(function ($assignment) {
            $submissions = Submission::where('assignment_id', $assignment->id)->get();
            $gradedCount = $submissions->where('status', 'graded')->count();
            $totalCount = $submissions->count();

            // Determine status
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
                'class' => 'Eng 101 - P2', // TODO: Link to actual class/period
                'submissions_completed' => $gradedCount,
                'submissions_total' => $totalCount,
                'status' => $status,
                'created_at' => $assignment->created_at,
            ];
        })->values();

        // Get recently saved rubrics (using assignments as rubric templates)
        $recentRubrics = $assignments->take(3)->map(function ($assignment) {
            return [
                'id' => $assignment->id,
                'name' => $assignment->title,
                'description' => $assignment->description,
            ];
        })->values();

        return Inertia::render('Dashboard', [
            'user' => [
                'name' => $user->name,
                'school' => 'Lincoln High School', // TODO: Get from user/tenant profile
                'department' => 'English Department', // TODO: Get from user profile
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
