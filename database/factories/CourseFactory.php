<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    public function definition(): array
    {
        return ['instructor_id' => User::factory(), 'category_id' => Category::factory(), 'title' => fake()->sentence(4), 'slug' => fake()->unique()->slug(4), 'summary' => fake()->sentence(), 'description' => fake()->paragraph(), 'level' => 'beginner', 'language' => 'en', 'price_minor' => 120000, 'currency' => 'ETB', 'is_free' => false, 'status' => 'published', 'published_at' => now()];
    }
}
