<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonNote extends Model
{
    protected $fillable = ['enrollment_id', 'lesson_id', 'body', 'bookmarked'];

    protected function casts(): array
    {
        return ['bookmarked' => 'boolean'];
    }
}
