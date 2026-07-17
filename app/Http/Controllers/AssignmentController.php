<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
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
        return $request->all();

        return 'test';
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
        $validated =$request->validate([
            'question' => 'required|string',
            'levels' => 'required|array|min:1', // Needs to contain your current columns
        ]);

        $question =$validated['question'];
        $levels =$validated['levels'];

        // Format levels for prompt context
        $levelsFormatted = collect($levels)
            ->map(fn($lvl) => "- {$lvl['name']} ({$lvl['range']} pts)")
            ->implode("\n");

        $prompt = "You are an expert academic assessment designer. Your task is to generate a comprehensive grading rubric tailored specifically for the following assessment question:

                QUESTION:
                '{$question}'

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
            Generate between 3 to 4 distinct criteria tailored directly to the details of the question.";

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
