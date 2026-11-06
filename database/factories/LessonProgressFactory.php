<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

class LessonProgressFactory extends Factory
{
    public function definition(): array
    {
        return ['enrollment_id' => Enrollment::factory(), 'lesson_id' => Lesson::factory(), 'position_seconds' => 0, 'watched_seconds' => 0];
    }
}
