<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Criterion;
use App\Models\Demo;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use OpenAI\Laravel\Facades\OpenAI;

class AssignmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index() {}

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Assignments/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Log::info('Assignment store raw request: '.json_encode($request->all()));
        error_log('Assignment store raw request: '.json_encode($request->all()));

        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'question' => 'required_without:questions|nullable|string',
                'questions' => 'required_without:question|nullable|array|min:1',
                'questions.*.prompt' => 'required_with:questions|string',
                'levels' => 'required|array|min:1',
                'criteria' => 'required|array|min:1',
                'session_id' => 'nullable|string|max:255',
            ]);
        } catch (ValidationException $ve) {
            Log::error('Assignment validation failed: '.json_encode($ve->errors()));
            error_log('Assignment validation failed: '.json_encode($ve->errors()));
            throw $ve;
        }

        $isDemo = filled($validated['session_id'] ?? null);

        if (! $isDemo && ! Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'You must be signed in to create an assignment.',
            ], 401);
        }

        try {
            Log::info('Assignment store payload: '.json_encode($validated));
            error_log('Assignment store payload: '.json_encode($validated));
            $result = DB::transaction(function () use ($validated, $isDemo) {
                $levels = $validated['levels'];
                $criteriaCount = count($validated['criteria']);

                $maxLevel = end($levels);
                $rangeString = $maxLevel['range'] ?? '0-0';

                $rangeParts = explode('-', $rangeString);
                $maxScorePerCriterion = (int) end($rangeParts);

                $maxScore = $maxScorePerCriterion * $criteriaCount;

                $questions = Assignment::normalizeQuestions(
                    $validated['questions'] ?? [['prompt' => $validated['question'] ?? '']]
                );

                if ($questions === []) {
                    throw ValidationException::withMessages([
                        'questions' => 'Please enter at least one assignment question.',
                    ]);
                }

                $assignmentAttributes = [
                    'title' => $validated['title'],
                    'levels' => $validated['levels'],
                    'max_score' => $maxScore,
                ];

                $demo = null;

                if ($isDemo) {
                    $demo = Demo::updateOrCreate(
                        ['session_id' => $validated['session_id']],
                        ['title' => $validated['title']]
                    );

                    $assignment = Assignment::updateOrCreate(
                        ['demo_id' => $demo->id],
                        $assignmentAttributes
                    );
                } else {
                    $assignment = Assignment::create([
                        ...$assignmentAttributes,
                        'demo_id' => null,
                        'created_by' => Auth::id(),
                        'join_code' => Assignment::generateJoinCode(),
                    ]);
                }

                Question::where('assignment_id', $assignment->id)->delete();

                foreach ($questions as $index => $question) {
                    Question::create([
                        'assignment_id' => $assignment->id,
                        'prompt' => $question['prompt'],
                        'order' => $index,
                    ]);
                }

                Criterion::where('assignment_id', $assignment->id)->delete();

                foreach ($validated['criteria'] as $criteriaItem) {
                    Criterion::create([
                        'assignment_id' => $assignment->id,
                        'key' => Str::slug($criteriaItem['name']),
                        'name' => $criteriaItem['name'],
                        'cells' => json_encode($criteriaItem['cells']),
                    ]);
                }

                return [
                    'assignment_id' => $assignment->id,
                    'demo_id' => $demo?->id,
                ];
            });

            Log::info('Assignment store result: '.json_encode($result));
            error_log('Assignment store result: '.json_encode($result));

            return response()->json([
                'success' => true,
                'message' => 'Assignment created successfully',
                'data' => $result,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Assignment Store Failure: '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());
            error_log('Assignment Store Failure: '.$e->getMessage());
            error_log('Stack trace: '.$e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create assignment: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Assignment $assignment): Response
    {
        abort_unless($assignment->ownedBy(Auth::user()), 404);

        $assignment->load([
            'questions',
            'criteria',
            'submissions' => fn ($query) => $query
                ->whereNotNull('user_id')
                ->with(['scores.criterion', 'user']),
        ]);

        return Inertia::render('Assignments/Show', [
            'user' => [
                'name' => Auth::user()->name,
            ],
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'join_code' => $assignment->join_code,
                'max_score' => $assignment->max_score,
                'overall_max_score' => $assignment->overallMaxScore(),
                'questions' => $assignment->questions->map(fn (Question $question) => [
                    'id' => $question->id,
                    'prompt' => $question->prompt,
                ])->values(),
                'criteria' => $assignment->criteria->map(fn (Criterion $criterion) => [
                    'id' => $criterion->id,
                    'name' => $criterion->name,
                ])->values(),
                'submissions' => $assignment->submissions->map(fn ($submission) => [
                    'id' => $submission->id,
                    'student_name' => $submission->user?->name
                        ?? $submission->payload['student_name']
                        ?? 'Untitled paper',
                    'student_response' => $submission->payload['student_response'] ?? '',
                    'overall_feedback' => $submission->payload['overall_feedback'] ?? null,
                    'status' => $submission->status,
                    'score' => $submission->score,
                    'submitted_at' => $submission->submitted_at,
                    'question_grades' => $submission->questionGrades($assignment),
                ])->values(),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id) {}

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id) {}

    public function getAIRubricSuggestion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'nullable|string',
            'title' => 'nullable|string',
            'levels' => 'required|array|min:1',
            'num_criteria' => 'nullable|integer|min:1|max:20',
        ]);

        if (empty($validated['question']) && empty($validated['title'])) {
            return response()->json([
                'success' => false,
                'message' => 'Either a question or a rubric title must be provided.',
            ], 400);
        }

        $question = $validated['question'];
        $title = $validated['title'];
        $levels = $validated['levels'];
        $numLevels = count($levels);
        $numCriteria = $validated['num_criteria'] ?? 3;

        $context = $question ?? $title;
        $contextLabel = $question ? 'QUESTION' : 'RUBRIC TITLE';

        $sampleCells = array_fill(0, $numLevels, '"Cell description"');
        $cellsExample = implode(', ', $sampleCells);

        $levelNamesPrompt = "You are an expert academic assessment designer. Generate exactly {$numLevels} performance level names that form a clear progression from poor to excellent performance.";

        if (! empty($context)) {
            $levelNamesPrompt .= " These levels should be tailored to the following {$contextLabel}: '{$context}'";
        }

        $levelNamesPrompt .= "

                Respond with ONLY a raw JSON array. Do not include markdown formatting or any other text.
                The JSON must be an array of objects following this exact structure:
                [
                    {
                    \"name\": \"Level Name (e.g., Poor, Below Average, etc)\",
                    \"range\": \"0-2\"
                    },
                    {
                    \"name\": \"Level Name\",
                    \"range\": \"3-4\"
                    }
                ]

                Generate exactly {$numLevels} levels with logical names that form a progression from lowest to highest.
                Distribute the score ranges evenly across {$numLevels} levels (out of 10 points per criterion).
                
                For 5 or more levels, be CREATIVE and use varied professional terminology. Examples of diverse naming conventions:
                - Developing, Proficient, Advanced, Mastery
                - Emerging, Developing, Proficient, Advanced, Expert
                - Novice, Intermediate, Proficient, Advanced, Master
                - Incomplete, In Progress, Meets Expectations, Exceeds Expectations, Outstanding
                - Minimal, Developing, Proficient, Advanced, Exemplary
                
                Choose level names that are appropriate for academic assessment, clear and professional, form a logical progression, and are diverse and varied.";

        try {
            $levelResponse = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a system that only responds with valid raw JSON arrays. No markdown, no extra text.'],
                    ['role' => 'user', 'content' => $levelNamesPrompt],
                ],
                'temperature' => 0.7,
            ]);

            $levelRawContent = $levelResponse->choices[0]->message->content;
            $levelCleanJson = preg_replace('/^```json|^```|```$/m', '', trim($levelRawContent));
            $suggestedLevels = json_decode($levelCleanJson, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($suggestedLevels) || count($suggestedLevels) !== $numLevels) {
                throw new \Exception('Invalid level names returned from AI.');
            }

            $levelsFormatted = collect($suggestedLevels)
                ->map(fn ($lvl) => "- {$lvl['name']} ({$lvl['range']} pts)")
                ->implode("\n");

            $criteriaPrompt = "You are an expert academic assessment designer. Your task is to generate a comprehensive grading rubric tailored specifically for the following assessment:

                {$contextLabel}:
                '{$context}'

                COLUMNS / PERFORMANCE LEVELS ({$numLevels} levels):
                {$levelsFormatted}

                You must generate exactly {$numCriteria} distinct criteria. Each criterion must have exactly {$numLevels} descriptions (one per performance level).

                Respond with a raw JSON object. Do not include markdown formatting like ```json or any other text. 
                The JSON must follow this structure exactly:
                {
                \"criteria\": [
                    {
                    \"name\": \"Criteria Name (e.g., Technical Accuracy)\",
                    \"cells\": [{$cellsExample}]
                    }
                ]
                }
            Generate exactly {$numCriteria} distinct criteria tailored directly to the details of the ".($question ? 'question' : 'rubric title').'. Each criterion description must correspond to the respective performance level above.';

            $criteriaResponse = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a system that only speaks in valid raw JSON schemas.'],
                    ['role' => 'user', 'content' => $criteriaPrompt],
                ],
                'temperature' => 0.5,
            ]);

            $criteriaRawContent = $criteriaResponse->choices[0]->message->content;
            $criteriaCleanJson = preg_replace('/^```json|```$/m', '', trim($criteriaRawContent));
            $criteriaData = json_decode($criteriaCleanJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid criteria returned from AI.');
            }

            return response()->json([
                'success' => true,
                'levels' => $suggestedLevels,
                'rubric' => $criteriaData,
            ]);

        } catch (\Exception $e) {
            Log::error('AI Rubric Builder Failure: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate rubric suggestions.',
            ], 500);
        }
    }

    public function getAILevelsSuggestion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'num_levels' => 'required|integer|min:2|max:10',
            'question' => 'nullable|string',
            'title' => 'nullable|string',
        ]);

        $numLevels = $validated['num_levels'];
        $question = $validated['question'];
        $title = $validated['title'];

        $context = ($question && ! empty($question)) ? $question : $title;
        $contextLabel = ($question && ! empty($question)) ? 'QUESTION' : 'RUBRIC TITLE';

        $prompt = "You are an expert academic assessment designer. Generate exactly {$numLevels} performance level names that form a clear progression from poor to excellent performance.";

        if (! empty($context)) {
            $prompt .= " These levels should be tailored to the following {$contextLabel}: '{$context}'";
        }

        $prompt .= "

                Respond with ONLY a raw JSON array. Do not include markdown formatting or any other text.
                The JSON must be an array of objects following this exact structure:
                [
                    {
                    \"name\": \"Level Name (e.g., Poor, Below Average, etc)\",
                    \"range\": \"0-2\"
                    },
                    {
                    \"name\": \"Level Name\",
                    \"range\": \"3-4\"
                    }
                ]

                Generate exactly {$numLevels} levels with logical names that form a progression from lowest to highest.
                Distribute the score ranges evenly across {$numLevels} levels (out of 10 points per criterion).
                
                EXAMPLES (these are just suggestions, not requirements):
                - 2 levels: Unsatisfactory (0-5), Satisfactory (6-10)
                - 3 levels: Poor (0-3), Satisfactory (4-7), Excellent (8-10)
                - 4 levels: Poor (0-2), Needs Improvement (3-5), Good (6-8), Excellent (9-10)
                - 5 levels: Poor (0-2), Below Average (3-4), Average (5-6), Above Average (7-8), Excellent (9-10)
                
                For 5 or more levels, be CREATIVE and use varied professional terminology. Examples of diverse naming conventions:
                - Developing, Proficient, Advanced, Mastery
                - Emerging, Developing, Proficient, Advanced, Expert
                - Novice, Intermediate, Proficient, Advanced, Master
                - Incomplete, In Progress, Meets Expectations, Exceeds Expectations, Outstanding
                - Minimal, Developing, Proficient, Advanced, Exemplary
                
                The examples above are just suggestions. Choose level names that are:
                1. Appropriate for academic assessment
                2. Clear and professional
                3. Form a logical progression from lowest to highest
                4. Diverse and varied - NOT limited to the example patterns above
                5. Contextually relevant to the assignment when possible";

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a system that only responds with valid raw JSON arrays. No markdown, no extra text.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
            ]);

            $rawContent = $response->choices[0]->message->content;

            $cleanJson = preg_replace('/^```json|^```|```$/m', '', trim($rawContent));
            $data = json_decode($cleanJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON returned from AI.');
            }

            if (! is_array($data) || count($data) !== $numLevels) {
                throw new \Exception("AI did not return exactly {$numLevels} levels.");
            }

            return response()->json([
                'success' => true,
                'levels' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('AI Levels Suggestion Failure: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate level suggestions.',
            ], 500);
        }
    }
}
