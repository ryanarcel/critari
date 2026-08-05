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

    public function scores()
    {
        return $this->hasMany(CriterionScore::class);
    }
}
