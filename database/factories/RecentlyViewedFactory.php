<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecentlyViewedFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'lesson_id' => Lesson::factory(), 'viewed_at' => now()];
    }
}
