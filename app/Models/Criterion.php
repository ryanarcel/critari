<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Criterion extends Model
{
    protected $table = 'criteria';

    protected $fillable = [
        'name',
        'description',
        'assignment_id',
    ];

    protected $casts = [
        'id' => 'integer',
        'assignment_id' => 'integer',
    ];

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }
}
