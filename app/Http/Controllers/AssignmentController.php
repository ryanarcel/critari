<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Criterion;
use App\Models\Demo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;

class AssignmentController extends Controller
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
    public function store(Request $request)
    {
        // Log raw request for debugging
        Log::info('Assignment store raw request: '.json_encode($request->all()));
        error_log('Assignment store raw request: '.json_encode($request->all()));

        // Validate incoming data
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'question' => 'required|string',
                'levels' => 'required|array|min:1',
                'criteria' => 'required|array|min:1',
                'session_id' => 'nullable|string|max:255',
            ]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            Log::error('Assignment validation failed: '.json_encode($ve->errors()));
            error_log('Assignment validation failed: '.json_encode($ve->errors()));
            throw $ve;
        }

        try {
            // Log incoming validated payload for debugging
            Log::info('Assignment store payload: '.json_encode($validated));
            error_log('Assignment store payload: '.json_encode($validated));
            // Wrap database operations in a transaction
            $result = DB::transaction(function () use ($validated) {
                // Calculate max score based on the highest level's range
                // E.g., if Excellent level has range "9-10", max score per criterion is 10
                $levels = $validated['levels'];
                $criteriaCount = count($validated['criteria']);

                // Get the last level (should be the highest scoring level)
                $maxLevel = end($levels);
                $rangeString = $maxLevel['range'] ?? '0-0';

                // Extract the maximum value from the range (format: "min-max")
                $rangeParts = explode('-', $rangeString);
                $maxScorePerCriterion = (int) end($rangeParts);

                // Calculate total max score
                $maxScore = $maxScorePerCriterion * $criteriaCount;

                // 1. Create or update Demo record using session_id as identifier
                $demo = Demo::updateOrCreate(
                    ['session_id' => $validated['session_id'] ?? null],
                    ['title' => $validated['title']]
                );

                // 2. Create or update Assignment record using demo_id as identifier
                $assignment = Assignment::updateOrCreate(
                    ['demo_id' => $demo->id],
                    [
                        'title' => $validated['title'],
                        'description' => $validated['question'], // Maps 'question' to 'description'
                        'levels' => $validated['levels'], // Store levels as array (will be auto-JSON encoded)
                        'max_score' => $maxScore,
                    ]
                );

                // 3. Delete old criteria for this assignment and recreate them
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
                    'demo_id' => $demo->id,
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
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function getAIRubricSuggestion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'nullable|string',
            'title' => 'nullable|string',
            'levels' => 'required|array|min:1',
            'num_criteria' => 'nullable|integer|min:1|max:20',
        ]);

        // Ensure at least one of question or title is provided
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
        $numCriteria = $validated['num_criteria'] ?? 3; // Default to 3 criteria if not specified

        // Use question if available, otherwise fall back to title
        $context = $question ?? $title;
        $contextLabel = $question ? 'QUESTION' : 'RUBRIC TITLE';

        // Build a sample cell structure based on the number of levels
        $sampleCells = array_fill(0, $numLevels, '"Cell description"');
        $cellsExample = implode(', ', $sampleCells);

        // PART 1: Generate level names
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
            // Get level name suggestions
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

            // PART 2: Generate criteria
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

        // Use question if available, otherwise fall back to title
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

            // Clean up any markdown formatting
            $cleanJson = preg_replace('/^```json|^```|```$/m', '', trim($rawContent));
            $data = json_decode($cleanJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON returned from AI.');
            }

            // Validate we got the right number of levels
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
