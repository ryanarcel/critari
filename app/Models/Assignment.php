<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'join_code',
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

    public function overallMaxScore(): int
    {
        if ($this->relationLoaded('questions')) {
            $questionCount = $this->questions->count();
        } elseif (isset($this->questions_count)) {
            $questionCount = (int) $this->questions_count;
        } else {
            $questionCount = $this->questions()->count();
        }

        return (int) $this->max_score * max(1, $questionCount);
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

    /**
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class)->orderByDesc('created_at');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'assigned_at')
            ->withTimestamps();
    }

    public function ownedBy(?User $user): bool
    {
        return $user !== null
            && $user->role !== 'student'
            && $this->demo_id === null
            && (int) $this->created_by === (int) $user->id;
    }

    public function joinedBy(?User $user): bool
    {
        return $user !== null
            && $user->role === 'student'
            && $this->students()->where('users.id', $user->id)->exists();
    }

    public static function normalizeJoinCode(?string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $code));
    }

    public static function generateJoinCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';

            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::query()->where('join_code', $code)->exists());

        return $code;
    }

    public static function findByJoinCode(?string $code): ?self
    {
        $normalized = static::normalizeJoinCode($code);

        if ($normalized === '') {
            return null;
        }

        return static::query()
            ->whereNull('demo_id')
            ->where('join_code', $normalized)
            ->first();
    }
}
