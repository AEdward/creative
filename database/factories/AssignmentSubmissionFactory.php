<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssignmentSubmission;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssignmentSubmission> */
class AssignmentSubmissionFactory extends Factory
{
    public function definition(): array
    {
        return ['assessment_id' => Assessment::factory()->state(['type' => 'assignment', 'questions' => null]), 'enrollment_id' => Enrollment::factory(), 'path' => 'assignments/'.fake()->uuid().'.pdf', 'original_name' => 'assignment.pdf', 'status' => 'pending'];
    }
}
