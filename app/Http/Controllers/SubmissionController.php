<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\CriterionScore;
use App\Models\Question;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class SubmissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index() {}

    /**
     * Show the form for creating a new resource.
     */
    public function create() {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assignment_id' => 'required|integer|exists:assignments,id',
            'student_response' => 'required|string',
            'student_name' => 'nullable|string|max:255',
            'demo_id' => 'nullable|integer|exists:demos,id',
        ]);

        $assignment = Assignment::query()->findOrFail($validated['assignment_id']);

        abort_if((bool) $assignment->created_by, 403);

        try {
            $payload = [
                'student_response' => $validated['student_response'],
            ];

            if (filled($validated['student_name'] ?? null)) {
                $payload['student_name'] = $validated['student_name'];
            }

            $submission = Submission::create([
                'assignment_id' => $assignment->id,
                'user_id' => $assignment->created_by ? null : Auth::id(),
                'demo_id' => $validated['demo_id'] ?? null,
                'payload' => $payload,
                'status' => 'pending',
                'submitted_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Submission saved successfully',
                'submission_id' => $submission->id,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save submission: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Submission $submission)
    {
        return response()->json($submission);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Submission $submission) {}

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Submission $submission): JsonResponse
    {
        $validated = $request->validate([
            'student_response' => 'required|string',
        ]);

        try {
            $submission->payload = [
                'student_response' => $validated['student_response'],
            ];
            $submission->save();

            return response()->json([
                'success' => true,
                'message' => 'Submission updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update submission: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Submission $submission) {}

    /**
     * Process AI assessment for a submission.
     * Grades each question response against the full rubric, then sums those scores.
     */
    public function processAIAssessment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'submission_id' => 'required|integer|exists:submissions,id',
        ]);

        $submission = Submission::findOrFail($validated['submission_id']);
        $assignment = $submission->assignment()->with(['criteria', 'questions'])->firstOrFail();

        if ($assignment->created_by) {
            abort_unless($assignment->ownedBy(Auth::user()), 403);
        }

        try {
            if ($assignment->criteria->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No criteria defined for this assignment.',
                ], 400);
            }

            $targets = $this->assessmentTargets($submission, $assignment);

            if ($targets === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'No student response found to assess.',
                ], 400);
            }

            $questionFeedback = [];
            $persistedScores = [];

            foreach ($targets as $target) {
                $data = $this->gradeResponseWithAi(
                    $assignment,
                    $target['prompt'],
                    $target['response']
                );

                $questionId = $target['question']?->id;
                $questionFeedback[$questionId ?? 'paper'] = $data['overall_feedback'] ?? '';

                foreach ($data['scores'] as $scoreData) {
                    $criterion = $assignment->criteria
                        ->firstWhere('name', $scoreData['criterion_name']);

                    if (! $criterion) {
                        continue;
                    }

                    $persistedScores[] = [
                        'question_id' => $questionId,
                        'criterion_id' => $criterion->id,
                        'score' => $scoreData['score'],
                        'feedback' => $scoreData['feedback'] ?? '',
                    ];
                }
            }

            return DB::transaction(function () use ($submission, $assignment, $persistedScores, $questionFeedback) {
                CriterionScore::query()->where('submission_id', $submission->id)->delete();

                foreach ($persistedScores as $scoreData) {
                    CriterionScore::create([
                        'submission_id' => $submission->id,
                        'criterion_id' => $scoreData['criterion_id'],
                        'question_id' => $scoreData['question_id'],
                        'score' => $scoreData['score'],
                        'feedback' => $scoreData['feedback'],
                    ]);
                }

                $totalScore = collect($persistedScores)->sum('score');
                $feedbackByQuestionId = collect($questionFeedback)
                    ->reject(fn ($feedback, $key) => $key === 'paper')
                    ->all();

                $submission->update([
                    'score' => $totalScore,
                    'status' => 'graded',
                    'graded_at' => now(),
                    'payload' => array_merge($submission->payload, [
                        'question_feedback' => $feedbackByQuestionId,
                        'overall_feedback' => collect($questionFeedback)->filter()->implode("\n\n"),
                    ]),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Assessment completed successfully',
                    'submission_id' => $submission->id,
                    'total_score' => $totalScore,
                    'max_score' => $assignment->overallMaxScore(),
                    'scores' => $persistedScores,
                ], 201);
            });

        } catch (\Exception $e) {
            Log::error('AI Assessment Failure: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to process assessment: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return list<array{question: ?Question, prompt: string, response: string}>
     */
    private function assessmentTargets(Submission $submission, Assignment $assignment): array
    {
        $targets = [];

        foreach ($assignment->questions as $question) {
            $response = $submission->answerFor($question->id);

            if ($response !== '') {
                $targets[] = [
                    'question' => $question,
                    'prompt' => $question->prompt,
                    'response' => $response,
                ];
            }
        }

        if ($targets !== []) {
            return $targets;
        }

        $fallback = $submission->answerFor(null);

        if ($fallback === '') {
            return [];
        }

        $question = $assignment->questions->count() === 1
            ? $assignment->questions->first()
            : null;

        return [[
            'question' => $question,
            'prompt' => $question?->prompt ?? $assignment->formattedPrompts(),
            'response' => $fallback,
        ]];
    }

    /**
     * @return array{scores: array<int, array{criterion_name: string, score: mixed, feedback?: string}>, overall_feedback?: string}
     */
    private function gradeResponseWithAi(Assignment $assignment, string $prompt, string $studentResponse): array
    {
        $criteriaList = $assignment->criteria
            ->map(fn ($criterion) => "- {$criterion->name}")
            ->implode("\n");

        $levels = is_array($assignment->levels) ? $assignment->levels : json_decode($assignment->levels, true);
        $levelsFormatted = collect($levels)
            ->map(fn ($level) => "{$level['name']}: {$level['range']} pts")
            ->implode(', ');

        $maxLevel = end($levels);
        $rangeString = $maxLevel['range'] ?? '0-0';
        $rangeParts = explode('-', $rangeString);
        $maxScorePerCriterion = (int) end($rangeParts);

        $aiPrompt = "You are an expert academic assessor. Grade the following student response against the provided criteria.

                    ASSIGNMENT QUESTION:
                    {$prompt}

                    GRADING LEVELS:
                    {$levelsFormatted}

                    CRITERIA TO GRADE:
                    {$criteriaList}

                    STUDENT RESPONSE:
                    \"{$studentResponse}\"

                    Respond with a raw JSON object. Do not include markdown formatting.
                    The JSON must follow this structure exactly:
                    {
                        \"scores\": [
                            {
                                \"criterion_name\": \"Criterion Name\",
                                \"score\": 7,
                                \"feedback\": \"Specific feedback for this criterion\"
                            }
                        ],
                        \"overall_feedback\": \"General assessment summary\"
                    }

                    Assign scores (0-{$maxScorePerCriterion}) for each criterion based on the performance levels provided above. Provide constructive feedback.";

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a system that only speaks in valid raw JSON schemas.'],
                ['role' => 'user', 'content' => $aiPrompt],
            ],
            'temperature' => 0.5,
        ]);

        $rawContent = $response->choices[0]->message->content;
        $cleanJson = preg_replace('/^```json|```$/m', '', trim($rawContent));
        $data = json_decode($cleanJson, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data) || ! isset($data['scores']) || ! is_array($data['scores'])) {
            throw new \Exception('Invalid JSON returned from AI assessment.');
        }

        return $data;
    }
}
