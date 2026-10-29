<?php

namespace Database\Factories;

use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

class LessonFactory extends Factory
{
    public function definition(): array
    {
        return ['module_id' => Module::factory(), 'title' => fake()->sentence(3), 'type' => 'video', 'published' => false, 'sort_order' => 0];
    }
}
