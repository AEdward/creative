<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssessmentAttempt extends Model
{
    use HasFactory;

    protected $fillable = ['assessment_id', 'enrollment_id', 'snapshot', 'answers', 'score', 'passed'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'answers' => 'array', 'passed' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new \LogicException('Scored attempts cannot be deleted.');
        });
        static::updating(function (): never {
            throw new \LogicException('Scored attempts cannot be changed.');
        });
    }

    protected $hidden = ['snapshot', 'answers'];
}
