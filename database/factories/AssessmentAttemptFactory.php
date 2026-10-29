<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssessmentAttempt> */
class AssessmentAttemptFactory extends Factory
{
    public function definition(): array
    {
        return ['assessment_id' => Assessment::factory(), 'enrollment_id' => Enrollment::factory(), 'snapshot' => ['title' => 'Quiz', 'questions' => [['prompt' => 'Choose one', 'options' => ['First', 'Second'], 'correct' => 0]], 'pass_percent' => 70], 'answers' => [0], 'score' => 100, 'passed' => true];
    }
}
