<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Demo extends Model
{
    use HasFactory;

    protected $table = 'demos';

    protected $fillable = [
        'title',
        'session_id',
        'description',
        'user_id',
        'visibility',
        'config',
        'metadata',
    ];

    protected $casts = [
        'id' => 'integer',
    ];

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }
}
