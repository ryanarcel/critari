<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use App\Models\Demo;
use App\Models\Assignment;
use App\Models\Criterion;

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
        // Validate incoming data
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'question' => 'required|string',
            'levels' => 'required|array|min:1',
            'criteria' => 'required|array|min:1',
            'session_id' => 'nullable|string|max:255',
        ]);

        try {
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

            return response()->json([
                'success' => true,
                'message' => 'Assignment created successfully',
                'data' => $result,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Assignment Store Failure: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create assignment: ' . $e->getMessage(),
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
        ]);

        // Ensure at least one of question or title is provided
        if (empty($validated['question']) && empty($validated['title'])) {
            return response()->json([
                'success' => false,
                'message' => 'Either a question or a rubric title must be provided.'
            ], 400);
        }

        $question = $validated['question'];
        $title = $validated['title'];
        $levels = $validated['levels'];

        // Format levels for prompt context
        $levelsFormatted = collect($levels)
            ->map(fn($lvl) => "- {$lvl['name']} ({$lvl['range']} pts)")
            ->implode("\n");

        // Use question if available, otherwise fall back to title
        $context = $question ?? $title;
        $contextLabel = $question ? 'QUESTION' : 'RUBRIC TITLE';

        $prompt = "You are an expert academic assessment designer. Your task is to generate a comprehensive grading rubric tailored specifically for the following assessment:

                {$contextLabel}:
                '{$context}'

                COLUMNS / PERFORMANCE LEVELS:
                {$levelsFormatted}

                Respond with a raw JSON object. Do not include markdown formatting like ```json or any other text. 
                The JSON must follow this structure exactly:
                {
                \"criteria\": [
                    {
                    \"name\": \"Criteria Name (e.g., Technical Accuracy)\",
                    \"cells\": [\"Cell 1 description\", \"Cell 2 description\", \"Cell 3 description\", \"Cell 4 description\"]
                    }
                ]
                }
            Generate between 3 to 4 distinct criteria tailored directly to the details of the " . ($question ? 'question' : 'rubric title') . ".";

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini', // Highly capable for structured JSON tasks
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a system that only speaks in valid raw JSON schemas.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.5, // Lower temperature keeps formatting strictly compliant
            ]);

            $rawContent = $response->choices[0]->message->content;
            
            // Clean up backticks in case OpenAI still wraps it in a code block
            $cleanJson = preg_replace('/^```json|```$/m', '', trim($rawContent));
            $data = json_decode($cleanJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Invalid JSON returned from AI.");
            }

            return response()->json([
                'success' => true,
                'rubric' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('AI Rubric Builder Failure: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate rubric suggestions.'
            ], 500);
        }
    }
}
