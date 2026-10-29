<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonProgress extends Model
{
    use HasFactory;

    protected $table = 'lesson_progress';

    protected $fillable = ['enrollment_id', 'lesson_id', 'position_seconds', 'watched_seconds', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'position_seconds' => 'integer', 'watched_seconds' => 'integer'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
