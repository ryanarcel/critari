<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    protected $table = 'assignments';

    protected $fillable = [
        'demo_id',
        'title',
        'description',
        'question',
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

    public function demo()
    {
        return $this->belongsTo(Demo::class);
    }

    public function criteria()
    {
        return $this->hasMany(Criterion::class);
    }

    /**
     * Map `question` attribute to the `description` column so UI can use `question`.
     */
    public function getQuestionAttribute()
    {
        return $this->attributes['description'] ?? null;
    }

    public function setQuestionAttribute($value)
    {
        $this->attributes['description'] = $value;
    }
}
