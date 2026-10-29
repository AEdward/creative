<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Certificate> */
class CertificateFactory extends Factory
{
    public function definition(): array
    {
        return ['enrollment_id' => Enrollment::factory(), 'reference' => fake()->uuid(), 'learner_name' => fake()->name(), 'course_title' => fake()->sentence(3), 'issued_at' => now()];
    }
}
