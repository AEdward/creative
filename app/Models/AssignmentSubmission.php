<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignmentSubmission extends Model
{
    use HasFactory;

    protected $fillable = ['assessment_id', 'enrollment_id', 'path', 'original_name', 'status', 'feedback', 'reviewer_id'];

    protected function casts(): array
    {
        return [];
    }
}
