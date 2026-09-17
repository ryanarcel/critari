<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Question;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    /**
     * Display the student home page.
     */
    public function home(): Response|RedirectResponse
    {
        $user = Auth::user();

        if (! $user || $user->role !== 'student') {
            return redirect()->route('dashboard');
        }

        $assignments = $user->assignments()
            ->withCount('questions')
            ->with(['submissions' => fn ($query) => $query->where('user_id', $user->id)])
            ->orderByPivot('assigned_at', 'desc')
            ->get()
            ->map(function (Assignment $assignment) {
                $submission = $assignment->submissions->first();

                return [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'status' => $submission?->status ?? 'not_started',
                    'score' => $submission?->score,
                    'max_score' => $assignment->max_score,
                    'overall_max_score' => $assignment->overallMaxScore(),
                ];
            })
            ->values();

        return Inertia::render('Student/Home', [
            'status' => session('status'),
            'user' => [
                'name' => $user->name,
            ],
            'assignments' => $assignments,
        ]);
    }

    /**
     * Join an assignment with a teacher-issued code.
     */
    public function join(Request $request): RedirectResponse
    {
        $user = $this->student();

        $validated = $request->validate([
            'code' => 'required|string|max:32',
        ]);

        $assignment = Assignment::findByJoinCode($validated['code']);

        if (! $assignment) {
            throw ValidationException::withMessages([
                'code' => 'No assignment uses that code.',
            ]);
        }

        $alreadyJoined = $assignment->joinedBy($user);

        $assignment->students()->syncWithoutDetaching([
            $user->id => [
                'role' => 'assignee',
                'assigned_at' => now(),
            ],
        ]);

        $redirect = redirect()->route('student.assignments.show', $assignment);

        if ($alreadyJoined) {
            return $redirect;
        }

        return $redirect->with('status', 'You now have access to this assignment.');
    }

    /**
     * Show a joined assignment so the student can answer.
     */
    public function show(Assignment $assignment): Response
    {
        $user = $this->student();

        abort_unless($assignment->joinedBy($user), 404);

        $assignment->load('questions');

        $submission = Submission::query()
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $user->id)
            ->with('scores.criterion')
            ->first();
        $savedAnswers = collect($submission?->payload['answers'] ?? []);

        return Inertia::render('Student/Assignment', [
            'status' => session('status'),
            'user' => [
                'name' => $user->name,
            ],
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'max_score' => $assignment->max_score,
                'overall_max_score' => $assignment->overallMaxScore(),
                'questions' => $assignment->questions->map(fn (Question $question) => [
                    'id' => $question->id,
                    'prompt' => $question->prompt,
                    'response' => (string) ($savedAnswers[$question->id]
                        ?? $savedAnswers[(string) $question->id]
                        ?? ''),
                ])->values(),
            ],
            'submission' => $submission ? [
                'status' => $submission->status,
                'score' => $submission->score,
                'question_grades' => $submission->questionGrades($assignment),
            ] : null,
        ]);
    }

    /**
     * Save the student's answers for a joined assignment.
     */
    public function submit(Request $request, Assignment $assignment): RedirectResponse
    {
        $user = $this->student();

        abort_unless($assignment->joinedBy($user), 404);

        $assignment->load('questions');

        $validated = $request->validate([
            'answers' => 'required|array|min:1',
            'answers.*.question_id' => 'required|integer',
            'answers.*.response' => 'required|string',
        ]);

        $answersById = collect($validated['answers'])
            ->mapWithKeys(fn (array $answer): array => [
                (int) $answer['question_id'] => trim($answer['response']),
            ]);

        foreach ($assignment->questions as $question) {
            if (! filled($answersById->get($question->id))) {
                throw ValidationException::withMessages([
                    'answers' => 'Please answer every question before submitting.',
                ]);
            }
        }

        $submission = Submission::query()
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $user->id)
            ->first();

        if ($submission && $submission->status === 'graded') {
            throw ValidationException::withMessages([
                'answers' => 'This assignment has already been graded.',
            ]);
        }

        $payload = array_merge($submission?->payload ?? [], [
            'student_name' => $user->name,
            'student_response' => $this->formattedResponse($assignment, $answersById->all()),
            'answers' => $answersById->all(),
        ]);

        if ($submission) {
            $submission->update([
                'payload' => $payload,
                'status' => 'pending',
                'submitted_at' => now(),
            ]);
        } else {
            Submission::create([
                'assignment_id' => $assignment->id,
                'user_id' => $user->id,
                'payload' => $payload,
                'status' => 'pending',
                'submitted_at' => now(),
            ]);
        }

        return redirect()
            ->route('student.assignments.show', $assignment)
            ->with('status', 'Your answers were submitted.');
    }

    private function student(): User
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->role === 'student', 404);

        return $user;
    }

    /**
     * @param  array<int, string>  $answersByQuestionId
     */
    private function formattedResponse(Assignment $assignment, array $answersByQuestionId): string
    {
        $questions = $assignment->questions;

        if ($questions->count() <= 1) {
            $question = $questions->first();

            return trim((string) ($answersByQuestionId[$question?->id] ?? ''));
        }

        return $questions
            ->map(function (Question $question, int $index) use ($answersByQuestionId): string {
                $answer = trim((string) ($answersByQuestionId[$question->id] ?? ''));

                return ($index + 1).'. '.$question->prompt."\n\n".$answer;
            })
            ->implode("\n\n");
    }
}
