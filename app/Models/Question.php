<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    protected $table = 'questions';

    protected $fillable = [
        'assignment_id',
        'prompt',
        'order',
    ];

    protected $casts = [
        'id' => 'integer',
        'assignment_id' => 'integer',
        'order' => 'integer',
    ];

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }
}
