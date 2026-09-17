<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $table = 'submissions';

    protected $fillable = [
        'assignment_id',
        'user_id',
        'demo_id',
        'payload',
        'score',
        'status',
        'submitted_at',
        'graded_at',
        'grader_id',
    ];

    protected $casts = [
        'id' => 'integer',
        'assignment_id' => 'integer',
        'user_id' => 'integer',
        'demo_id' => 'integer',
        'grader_id' => 'integer',
        'score' => 'decimal:3',
        'payload' => 'array',
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
    ];

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scores()
    {
        return $this->hasMany(CriterionScore::class);
    }

    public function answerFor(?int $questionId): string
    {
        $answers = $this->payload['answers'] ?? [];

        if ($questionId !== null) {
            return trim((string) ($answers[$questionId] ?? $answers[(string) $questionId] ?? ''));
        }

        return trim((string) ($this->payload['student_response'] ?? ''));
    }

    /**
     * @return list<array{question_id: int|null, prompt: string|null, response: string, score: float|int, max_score: int|null, overall_feedback: string|null, scores: array<int, array{criterion_name: string|null, score: mixed, feedback: string|null}>}>
     */
    public function questionGrades(?Assignment $assignment = null): array
    {
        $assignment ??= $this->assignment()->with('questions')->first();

        if (! $assignment) {
            return [];
        }

        $this->loadMissing('scores.criterion');

        $feedback = collect($this->payload['question_feedback'] ?? []);

        $grades = $assignment->questions->map(function (Question $question) use ($assignment, $feedback) {
            $questionScores = $this->scores->where('question_id', $question->id);

            return [
                'question_id' => $question->id,
                'prompt' => $question->prompt,
                'response' => $this->answerFor($question->id),
                'score' => (float) $questionScores->sum('score'),
                'max_score' => $assignment->max_score,
                'overall_feedback' => $feedback[$question->id] ?? $feedback[(string) $question->id] ?? null,
                'scores' => $questionScores->map(fn (CriterionScore $score) => [
                    'criterion_name' => $score->criterion?->name,
                    'score' => $score->score,
                    'feedback' => $score->feedback,
                ])->values()->all(),
            ];
        });

        $unassigned = $this->scores->filter(fn (CriterionScore $score) => $score->question_id === null);

        if ($unassigned->isNotEmpty()) {
            $grades->push([
                'question_id' => null,
                'prompt' => null,
                'response' => $this->answerFor(null),
                'score' => (float) $unassigned->sum('score'),
                'max_score' => $assignment->max_score,
                'overall_feedback' => $this->payload['overall_feedback'] ?? null,
                'scores' => $unassigned->map(fn (CriterionScore $score) => [
                    'criterion_name' => $score->criterion?->name,
                    'score' => $score->score,
                    'feedback' => $score->feedback,
                ])->values()->all(),
            ]);
        }

        return $grades->values()->all();
    }
}
