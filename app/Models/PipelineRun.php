<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PipelineRun extends Model
{
    protected $table = 'pipeline_runs';

    protected $fillable = [
        'submission_id',
        'payload',
        'result',
        'duration_ms',
        'status',
    ];

    protected $casts = [
        'id' => 'integer',
        'submission_id' => 'integer',
        'payload' => 'array',
        'result' => 'array',
        'duration_ms' => 'integer',
    ];

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }
}
