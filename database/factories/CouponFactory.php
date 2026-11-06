<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CouponFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => strtoupper(fake()->unique()->bothify('CREATE????')), 'percent' => 10, 'max_uses' => 100, 'per_user_limit' => 1, 'active' => true];
    }
}
