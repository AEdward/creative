<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['video-editing' => 'Video Editing', 'graphic-design' => 'Graphic Design', 'motion-design' => 'Motion Design', '3d-blender' => '3D & Blender', 'digital-marketing' => 'Digital Marketing'] as $slug => $name) {
            Category::firstOrCreate(['slug' => $slug], ['name' => $name, 'active' => true]);
        }
    }
}
