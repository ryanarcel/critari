<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CriterionScore extends Model
{
    protected $table = 'criterion_scores';

    protected $fillable = [
        'submission_id',
        'criterion_id',
        'points_awarded',
        'feedback',
    ];

    protected $casts = [
        'id' => 'integer',
        'submission_id' => 'integer',
        'criterion_id' => 'integer',
        'points_awarded' => 'integer',
    ];

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function criterion()
    {
        return $this->belongsTo(Criterion::class);
    }
}
