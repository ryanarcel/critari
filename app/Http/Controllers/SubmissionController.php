<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Criterion;
use App\Models\CriterionScore;
use App\Models\Question;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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
            'student_response' => 'required_without:answers|nullable|string',
            'answers' => 'required_without:student_response|nullable|array|min:1',
            'answers.*.question_id' => 'required_with:answers|integer',
            'answers.*.response' => 'required_with:answers|string',
            'student_name' => 'nullable|string|max:255',
            'demo_id' => 'nullable|integer|exists:demos,id',
        ]);

        $assignment = Assignment::query()->with('questions')->findOrFail($validated['assignment_id']);

        abort_if((bool) $assignment->created_by, 403);

        $answersById = collect($validated['answers'] ?? [])
            ->mapWithKeys(fn (array $answer): array => [
                (int) $answer['question_id'] => trim($answer['response']),
            ]);

        if ($answersById->isNotEmpty()) {
            foreach ($assignment->questions as $question) {
                if (! filled($answersById->get($question->id))) {
                    throw ValidationException::withMessages([
                        'answers' => 'Please answer every question before submitting.',
                    ]);
                }
            }
        }

        try {
            $payload = [
                'student_response' => filled($validated['student_response'] ?? null)
                    ? $validated['student_response']
                    : $this->formattedResponse($assignment, $answersById->all()),
            ];

            if ($answersById->isNotEmpty()) {
                $payload['answers'] = $answersById->all();
            }

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
            $reusedQuestionIds = [];

            foreach ($targets as $target) {
                $questionId = $target['question']?->id;
                $data = $this->matchingGradedQuestion(
                    $assignment,
                    $submission,
                    $target['question'],
                    $target['response']
                );

                if ($data !== null) {
                    if ($questionId !== null) {
                        $reusedQuestionIds[] = $questionId;
                    }
                } else {
                    $data = $this->gradeResponseWithAi(
                        $assignment,
                        $target['prompt'],
                        $target['response']
                    );
                }

                $questionFeedback[$questionId ?? 'paper'] = $data['overall_feedback'] ?? '';

                foreach ($this->scoresForCriteria($assignment, $data['scores'] ?? []) as $scoreData) {
                    $persistedScores[] = [
                        'question_id' => $questionId,
                        'criterion_id' => $scoreData['criterion_id'],
                        'criterion_name' => $scoreData['criterion_name'],
                        'score' => $scoreData['score'],
                        'feedback' => $scoreData['feedback'],
                    ];
                }
            }

            $gradedMaxScore = $assignment->overallMaxScore();

            return DB::transaction(function () use ($submission, $assignment, $persistedScores, $questionFeedback, $reusedQuestionIds, $gradedMaxScore) {
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

                $submission->refresh();
                $submission->load('scores.criterion');

                return response()->json([
                    'success' => true,
                    'message' => 'Assessment completed successfully',
                    'submission_id' => $submission->id,
                    'total_score' => $totalScore,
                    'max_score' => $gradedMaxScore,
                    'reused_question_ids' => $reusedQuestionIds,
                    'question_grades' => $submission->questionGrades($assignment),
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
     * @return array{scores: array<int, array{criterion_name: string, score: mixed, feedback?: string}>, overall_feedback?: string}|null
     */
    private function matchingGradedQuestion(
        Assignment $assignment,
        Submission $submission,
        ?Question $question,
        string $response
    ): ?array {
        $candidates = Submission::query()
            ->where('assignment_id', $assignment->id)
            ->where('status', 'graded')
            ->whereKeyNot($submission->id)
            ->with('scores.criterion')
            ->orderByDesc('graded_at')
            ->get();

        foreach ($candidates as $candidate) {
            $otherResponse = $question
                ? $candidate->answerFor($question->id)
                : $candidate->answerFor(null);

            if (! $this->responsesMatch($response, $otherResponse)) {
                continue;
            }

            $scores = $question
                ? $candidate->scores->where('question_id', $question->id)
                : $candidate->scores->whereNull('question_id');

            if ($scores->isEmpty() && $question === null) {
                $scores = $candidate->scores;
            }

            if ($scores->count() < $assignment->criteria->count()) {
                continue;
            }

            $feedback = $candidate->payload['question_feedback'] ?? [];
            $overall = $question === null
                ? ($candidate->payload['overall_feedback'] ?? '')
                : ($feedback[$question->id] ?? $feedback[(string) $question->id] ?? '');

            return [
                'overall_feedback' => $overall,
                'scores' => $scores->map(fn (CriterionScore $score): array => [
                    'criterion_name' => $score->criterion?->name,
                    'score' => $score->score,
                    'feedback' => $score->feedback,
                ])->values()->all(),
            ];
        }

        return null;
    }

    private function responsesMatch(string $left, string $right): bool
    {
        $a = $this->normalizedResponse($left);
        $b = $this->normalizedResponse($right);

        if ($a === '' || $b === '') {
            return false;
        }

        if ($a === $b) {
            return true;
        }

        similar_text($a, $b, $percent);

        return $percent >= 90.0;
    }

    private function normalizedResponse(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @return array{scores: array<int, array{criterion_name: string, score: mixed, feedback?: string}>, overall_feedback?: string}
     */
    private function gradeResponseWithAi(Assignment $assignment, string $prompt, string $studentResponse): array
    {
        $levels = $this->assignmentLevels($assignment);
        $maxScorePerCriterion = $this->maxScorePerCriterion($levels);
        $criterionNames = $assignment->criteria->pluck('name')->filter()->values()->all();
        $namesJson = json_encode($criterionNames, JSON_UNESCAPED_UNICODE);
        $rubricMatrix = $this->formattedRubricMatrix($assignment, $levels);

        $aiPrompt = "You are an expert academic assessor. Grade the student response using only the rubric matrix below.

                    ASSIGNMENT QUESTION:
                    {$prompt}

                    RUBRIC MATRIX:
                    {$rubricMatrix}

                    STUDENT RESPONSE:
                    \"{$studentResponse}\"

                    Respond with a raw JSON object. Do not include markdown formatting.
                    The JSON must follow this structure exactly:
                    {
                        \"scores\": [
                            {
                                \"criterion_name\": \"Criterion Name\",
                                \"score\": 7,
                                \"feedback\": \"Reasoning for this score, citing evidence from the student response\"
                            }
                        ],
                        \"overall_feedback\": \"Brief summary of the assessment\"
                    }

                    Use these criterion_name values exactly, one score per criterion: {$namesJson}.
                    For each criterion, pick the level whose cell best matches the response, then assign an integer score inside that level's point range (0-{$maxScorePerCriterion}). Cite evidence from the student response. Do not invent criteria.";

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a system that only speaks in valid raw JSON schemas.'],
                ['role' => 'user', 'content' => $aiPrompt],
            ],
            'temperature' => 0,
            'seed' => abs(crc32((string) $assignment->id.'|'.$prompt.'|'.$studentResponse)),
        ]);

        $rawContent = $response->choices[0]->message->content;
        $cleanJson = preg_replace('/^```json|```$/m', '', trim($rawContent));
        $data = json_decode($cleanJson, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data) || ! isset($data['scores']) || ! is_array($data['scores'])) {
            throw new \Exception('Invalid JSON returned from AI assessment.');
        }

        return $data;
    }

    /**
     * @param  array<int, mixed>  $levels
     */
    private function formattedRubricMatrix(Assignment $assignment, array $levels): string
    {
        $header = collect($levels)
            ->map(function ($level): string {
                $name = trim((string) ($level['name'] ?? 'Level'));
                $range = trim((string) ($level['range'] ?? ''));

                return $range === '' ? $name : "{$name} ({$range})";
            })
            ->implode(', ');

        $rows = $assignment->criteria->map(function ($criterion) use ($levels): string {
            $cells = $this->criterionCells($criterion);
            $line = "Criterion \"{$criterion->name}\":";

            if (filled($criterion->description)) {
                $line .= " {$criterion->description}";
            }

            $details = collect($levels)
                ->values()
                ->map(function ($level, int $index) use ($cells): string {
                    $name = trim((string) ($level['name'] ?? 'Level'));
                    $range = trim((string) ($level['range'] ?? ''));
                    $cell = trim((string) ($cells[$index] ?? ''));
                    $label = $range === '' ? $name : "{$name} ({$range})";

                    return $cell === '' ? "- {$label}" : "- {$label}: {$cell}";
                })
                ->implode("\n");

            return $line."\n".$details;
        })->implode("\n\n");

        return "Levels: {$header}\n\n{$rows}";
    }

    /**
     * @param  array<int, array{criterion_name?: mixed, score?: mixed, feedback?: mixed}>  $rawScores
     * @return list<array{criterion_id: int, criterion_name: string, score: int, feedback: string}>
     */
    private function scoresForCriteria(Assignment $assignment, array $rawScores): array
    {
        $maxScore = $this->maxScorePerCriterion($this->assignmentLevels($assignment));
        $byName = [];

        foreach ($rawScores as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = $this->criterionKey((string) ($row['criterion_name'] ?? ''));

            if ($key === '') {
                continue;
            }

            $byName[$key] = $row;
        }

        return $assignment->criteria->map(function ($criterion) use ($byName, $maxScore) {
            $match = $byName[$this->criterionKey((string) $criterion->name)] ?? [];
            $score = is_numeric($match['score'] ?? null) ? (int) round((float) $match['score']) : 0;
            $score = max(0, min($maxScore, $score));

            return [
                'criterion_id' => $criterion->id,
                'criterion_name' => $criterion->name,
                'score' => $score,
                'feedback' => trim((string) ($match['feedback'] ?? '')),
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array{name?: mixed, range?: mixed}>
     */
    private function assignmentLevels(Assignment $assignment): array
    {
        $levels = is_array($assignment->levels) ? $assignment->levels : json_decode((string) $assignment->levels, true);

        return is_array($levels) ? array_values($levels) : [];
    }

    /**
     * @param  array<int, array{range?: mixed}>  $levels
     */
    private function maxScorePerCriterion(array $levels): int
    {
        if ($levels === []) {
            return 0;
        }

        $maxLevel = $levels[array_key_last($levels)];
        $rangeParts = explode('-', (string) ($maxLevel['range'] ?? '0-0'));

        return (int) end($rangeParts);
    }

    /**
     * @return array<int, mixed>
     */
    private function criterionCells(Criterion $criterion): array
    {
        $cells = $criterion->cells;

        if (is_string($cells)) {
            $decoded = json_decode($cells, true);
            $cells = is_array($decoded) ? $decoded : [];
        }

        return is_array($cells) ? array_values($cells) : [];
    }

    private function criterionKey(string $name): string
    {
        return mb_strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $name));
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
