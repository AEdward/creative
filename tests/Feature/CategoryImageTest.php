<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_category_image_can_be_replaced_or_hidden(): void
    {
        $category = Category::create(['name' => 'Graphic Design', 'slug' => 'graphic-design', 'sort_order' => 1, 'active' => true]);
        $this->get('/')->assertOk()->assertSee('images/categories/graphic-design.webp');

        $category->update(['image_path' => 'categories/new-cover.webp']);
        $this->get('/')->assertSee('categories/new-cover.webp')->assertDontSee('images/categories/graphic-design.webp');

        $category->update(['show_image' => false]);
        $this->get('/')->assertDontSee('categories/new-cover.webp');
    }
}
