<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\CriterionScore;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class SubmissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        // Validate incoming data
        $validated = $request->validate([
            'assignment_id' => 'required|integer|exists:assignments,id',
            'student_response' => 'required|string',
            'demo_id' => 'nullable|integer|exists:demos,id',
        ]);

        try {
            // Create submission record
            $submission = Submission::create([
                'assignment_id' => $validated['assignment_id'],
                'user_id' => auth()->id() ?? null,
                'demo_id' => $validated['demo_id'] ?? null,
                'payload' => [
                    'student_response' => $validated['student_response'],
                ],
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
        // Optionally authorize user can view this submission
        // $this->authorize('view', $submission);

        return response()->json($submission);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Submission $submission)
    {
        //
    }

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
    public function destroy(Submission $submission)
    {
        //
    }

    /**
     * Process AI assessment for a submission.
     * Grades the student response against each criterion and stores scores.
     */
    public function processAIAssessment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'submission_id' => 'required|integer|exists:submissions,id',
        ]);

        try {
            // Load submission with assignment and criteria
            $submission = Submission::findOrFail($validated['submission_id']);
            $assignment = $submission->assignment()->with('criteria')->firstOrFail();
            $studentResponse = $submission->payload['student_response'] ?? '';

            if (empty($studentResponse)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No student response found to assess.',
                ], 400);
            }

            if ($assignment->criteria->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No criteria defined for this assignment.',
                ], 400);
            }

            // Format criteria info for the prompt
            $criteriaList = $assignment->criteria
                ->map(fn ($c) => "- {$c->name}")
                ->implode("\n");

            // Format levels for scoring context
            // $assignment->levels is already an array due to model casting
            $levels = is_array($assignment->levels) ? $assignment->levels : json_decode($assignment->levels, true);
            $levelsFormatted = collect($levels)
                ->map(fn ($lvl) => "{$lvl['name']}: {$lvl['range']} pts")
                ->implode(', ');

            // Calculate max score per criterion from the highest level's range
            $maxLevel = end($levels);
            $rangeString = $maxLevel['range'] ?? '0-0';
            $rangeParts = explode('-', $rangeString);
            $maxScorePerCriterion = (int) end($rangeParts);

            $prompt = "You are an expert academic assessor. Grade the following student response against the provided criteria.

                    ASSIGNMENT:
                    {$assignment->description}

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
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.5,
            ]);

            $rawContent = $response->choices[0]->message->content;
            $cleanJson = preg_replace('/^```json|```$/m', '', trim($rawContent));
            $data = json_decode($cleanJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON returned from AI assessment.');
            }

            // Store scores in a database transaction
            return DB::transaction(function () use ($submission, $assignment, $data) {
                foreach ($data['scores'] as $scoreData) {
                    // Find criterion by name
                    $criterion = $assignment->criteria()
                        ->where('name', $scoreData['criterion_name'])
                        ->first();

                    if ($criterion) {
                        CriterionScore::create([
                            'submission_id' => $submission->id,
                            'criterion_id' => $criterion->id,
                            'score' => $scoreData['score'],
                            'feedback' => $scoreData['feedback'] ?? '',
                        ]);
                    }
                }

                // Update submission status
                $totalScore = collect($data['scores'])->sum('score');
                $submission->update([
                    'score' => $totalScore,
                    'status' => 'graded',
                    'graded_at' => now(),
                    'payload' => array_merge($submission->payload, [
                        'overall_feedback' => $data['overall_feedback'] ?? '',
                    ]),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Assessment completed successfully',
                    'submission_id' => $submission->id,
                    'total_score' => $totalScore,
                    'max_score' => $assignment->max_score,
                    'scores' => $data['scores'],
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
}
