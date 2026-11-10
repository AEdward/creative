<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentSubmission extends Model
{
    use HasFactory;

    protected $fillable = ['assessment_id', 'enrollment_id', 'path', 'disk', 'original_name', 'bytes', 'mime', 'status', 'feedback', 'reviewer_id', 'file_deleted_at'];

    protected function casts(): array
    {
        return ['bytes' => 'integer', 'file_deleted_at' => 'datetime'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
