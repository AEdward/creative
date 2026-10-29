<?php

namespace Database\Factories;

use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LessonResourceFactory extends Factory
{
    public function definition(): array
    {
        return ['lesson_id' => Lesson::factory(), 'title' => 'Exercise', 'kind' => 'resource', 'object_key' => 'lesson-files/'.Str::uuid().'.txt', 'mime' => 'text/plain', 'extension' => 'txt', 'bytes' => 32];
    }
}
