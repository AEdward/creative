<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assessment extends Model
{
    use HasFactory;

    protected $fillable = ['course_id', 'type', 'title', 'instructions', 'questions', 'pass_percent', 'attempt_limit', 'required', 'advance_on'];

    protected function casts(): array
    {
        return ['questions' => 'array', 'required' => 'boolean'];
    }

    protected $hidden = ['questions'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
