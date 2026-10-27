<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Question;
use App\Models\Submission;
use App\Models\User;
use App\Services\PaperOcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaperSubmissionController extends Controller
{
    public function __construct(private PaperOcrService $ocr) {}

    /**
     * Create a walk-in paper or attach to a joined student's submission.
     */
    public function store(Request $request, Assignment $assignment): JsonResponse
    {
        abort_unless($assignment->ownedBy(Auth::user()), 404);

        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'student_name' => 'required_without:user_id|nullable|string|max:255',
        ]);

        if (filled($validated['user_id'] ?? null)) {
            $student = User::query()->findOrFail($validated['user_id']);

            if ($student->role !== 'student' || ! $assignment->joinedBy($student)) {
                throw ValidationException::withMessages([
                    'user_id' => 'That student has not joined this assignment.',
                ]);
            }

            $submission = Submission::query()
                ->where('assignment_id', $assignment->id)
                ->where('user_id', $student->id)
                ->first();

            if ($submission) {
                return response()->json([
                    'success' => true,
                    'submission_id' => $submission->id,
                    'student_name' => $student->name,
                    'answers' => $this->answersFrom($submission),
                ]);
            }

            $submission = Submission::query()->create([
                'assignment_id' => $assignment->id,
                'user_id' => $student->id,
                'payload' => [
                    'student_name' => $student->name,
                    'student_response' => '',
                    'answers' => [],
                    'source' => 'paper',
                ],
                'status' => 'pending',
                'submitted_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'submission_id' => $submission->id,
                'student_name' => $student->name,
                'answers' => [],
            ], 201);
        }

        $submission = Submission::query()->create([
            'assignment_id' => $assignment->id,
            'user_id' => null,
            'payload' => [
                'student_name' => $validated['student_name'],
                'student_response' => '',
                'answers' => [],
                'source' => 'paper',
            ],
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'submission_id' => $submission->id,
            'student_name' => $validated['student_name'],
            'answers' => [],
        ], 201);
    }

    /**
     * Transcribe one photo before a submission exists.
     */
    public function ocrPhoto(Request $request, Assignment $assignment): JsonResponse
    {
        abort_unless($assignment->ownedBy(Auth::user()), 404);

        return $this->transcribePhoto($request, $assignment);
    }

    /**
     * Transcribe one photo. The text is not saved until the teacher confirms it.
     */
    public function ocr(Request $request, Assignment $assignment, Submission $submission): JsonResponse
    {
        abort_unless($assignment->ownedBy(Auth::user()), 404);
        abort_unless((int) $submission->assignment_id === (int) $assignment->id, 404);

        return $this->transcribePhoto($request, $assignment);
    }

    /**
     * Run OCR on an uploaded photo for one assignment question.
     */
    private function transcribePhoto(Request $request, Assignment $assignment): JsonResponse
    {
        $validated = $request->validate([
            'question_id' => 'required|integer|exists:questions,id',
            'image' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $question = Question::query()->findOrFail($validated['question_id']);

        if ((int) $question->assignment_id !== (int) $assignment->id) {
            throw ValidationException::withMessages([
                'question_id' => 'That question does not belong to this assignment.',
            ]);
        }

        try {
            $text = $this->ocr->transcribe($validated['image']);
        } catch (\Exception $e) {
            Log::error('Paper OCR Failure: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Could not read the photo. Try another picture.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'text' => $text,
            'question_id' => $question->id,
        ]);
    }

    /**
     * Persist teacher-reviewed OCR text onto the paper.
     */
    public function update(Request $request, Assignment $assignment, Submission $submission): JsonResponse
    {
        abort_unless($assignment->ownedBy(Auth::user()), 404);
        abort_unless((int) $submission->assignment_id === (int) $assignment->id, 404);

        $validated = $request->validate([
            'answers' => 'required|array|min:1',
            'answers.*.question_id' => 'required|integer|exists:questions,id',
            'answers.*.response' => 'nullable|string',
            'student_name' => 'nullable|string|max:255',
        ]);

        $assignment->load('questions');
        $questionIds = $assignment->questions->pluck('id');
        $answers = $this->answersFrom($submission);

        foreach ($validated['answers'] as $answer) {
            $questionId = (int) $answer['question_id'];

            if (! $questionIds->contains($questionId)) {
                throw ValidationException::withMessages([
                    'answers' => 'One of the questions does not belong to this assignment.',
                ]);
            }

            $answers[$questionId] = trim((string) ($answer['response'] ?? ''));
        }

        $payload = array_merge($submission->payload ?? [], [
            'answers' => $answers,
            'student_response' => $assignment->formattedStudentResponse($answers),
        ]);

        if ($submission->user_id === null) {
            $payload['source'] = 'paper';

            if (filled($validated['student_name'] ?? null)) {
                $payload['student_name'] = $validated['student_name'];
            }
        }

        $updates = [
            'payload' => $payload,
            'submitted_at' => now(),
        ];

        if ($submission->status === 'graded') {
            $updates['status'] = 'pending';
            $updates['score'] = null;
            $updates['graded_at'] = null;
        }

        $submission->update($updates);

        return response()->json([
            'success' => true,
            'submission_id' => $submission->id,
            'status' => $submission->fresh()->status,
            'answers' => $answers,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function answersFrom(Submission $submission): array
    {
        return collect($submission->payload['answers'] ?? [])
            ->mapWithKeys(fn ($response, $questionId): array => [(int) $questionId => (string) $response])
            ->all();
    }
}
