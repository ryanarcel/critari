<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $table = 'submissions';

    protected $fillable = [
        'assignment_id',
        'user_id',
        'student_response',
        'status',
        'telemetry',
    ];

    protected $casts = [
        'id' => 'integer',
        'assignment_id' => 'integer',
        'user_id' => 'integer',
        'telemetry' => 'array',
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
