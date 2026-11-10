<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InstructorProfileFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory()->state(['role' => 'instructor']), 'professional_title' => fake()->jobTitle(), 'biography' => fake()->paragraph(), 'specialties' => 'Design', 'publicly_listed' => false, 'show_photo' => false];
    }
}
