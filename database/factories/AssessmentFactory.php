<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Assessment> */
class AssessmentFactory extends Factory
{
    public function definition(): array
    {
        return ['course_id' => Course::factory(), 'type' => 'quiz', 'title' => fake()->sentence(3), 'questions' => [['prompt' => 'Choose one', 'options' => ['First', 'Second'], 'correct' => 0]], 'pass_percent' => 70, 'attempt_limit' => 3, 'required' => false];
    }
}
