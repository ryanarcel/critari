<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    protected $table = 'assignments';

    protected $fillable = [
        'demo_id',
        'title',
        'levels',
        'max_score',
        'created_by',
        'due_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'demo_id' => 'integer',
        'created_by' => 'integer',
        'max_score' => 'integer',
        'levels' => 'array',
        'due_at' => 'datetime',
    ];

    /**
     * @param  array<int, mixed>  $questions
     * @return list<array{prompt: string}>
     */
    public static function normalizeQuestions(array $questions): array
    {
        return collect($questions)
            ->map(function ($question): string {
                if (is_string($question)) {
                    return trim($question);
                }

                return trim((string) (is_array($question) ? ($question['prompt'] ?? '') : ''));
            })
            ->filter()
            ->map(fn (string $prompt): array => ['prompt' => $prompt])
            ->values()
            ->all();
    }

    public function formattedPrompts(): string
    {
        $prompts = $this->questions->pluck('prompt')->filter()->values();

        if ($prompts->count() <= 1) {
            return (string) $prompts->first();
        }

        return $prompts
            ->map(fn (string $prompt, int $index): string => ($index + 1).'. '.$prompt)
            ->implode("\n\n");
    }

    /**
     * @return BelongsTo<Demo, $this>
     */
    public function demo(): BelongsTo
    {
        return $this->belongsTo(Demo::class);
    }

    /**
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('order');
    }

    /**
     * @return HasMany<Criterion, $this>
     */
    public function criteria(): HasMany
    {
        return $this->hasMany(Criterion::class);
    }
}
