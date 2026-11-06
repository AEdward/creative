<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VideoAssetFactory extends Factory
{
    public function definition(): array
    {
        return ['course_id' => Course::factory(), 'uploaded_by' => User::factory(), 'object_key' => 'videos/'.Str::uuid().'.mp4', 'upload_key' => 'uploads/'.Str::uuid().'.mp4', 'filename' => 'lesson.mp4', 'expected_bytes' => 32, 'mime' => 'video/mp4', 'status' => 'pending', 'upload_expires_at' => now()->addMinutes(15)];
    }
}
