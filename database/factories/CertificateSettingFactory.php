<?php

namespace Database\Factories;

use App\Models\CertificateSetting;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CertificateSetting> */
class CertificateSettingFactory extends Factory
{
    public function definition(): array
    {
        return ['course_id' => Course::factory(), 'enabled' => false];
    }
}
