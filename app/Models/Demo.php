<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Demo extends Model
{
    use HasFactory;

    protected $table = 'demos';

    protected $fillable = [
        'demo_key',
    ];

    protected $casts = [
        'id' => 'integer',
    ];

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }
}
