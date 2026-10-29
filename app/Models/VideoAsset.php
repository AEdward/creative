<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoAsset extends Model
{
    use HasFactory;

    protected $fillable = ['course_id', 'uploaded_by', 'object_key', 'upload_key', 'filename', 'expected_bytes', 'mime', 'status', 'upload_expires_at'];

    protected function casts(): array
    {
        return ['expected_bytes' => 'integer', 'upload_expires_at' => 'datetime'];
    }
}
