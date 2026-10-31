<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private function course(array $attributes = []): Course
    {
        return Course::factory()->create($attributes);
    }

    public function test_public_pages_have_useful_empty_states(): void
    {
        $this->get('/')->assertOk()->assertSee('Learn Creative Skills')->assertSee('Start Learning')->assertDontSee('student is already learning')->assertDontSee('Active Students');
        $this->get('/courses')->assertOk()->assertSee('No courses published yet');
    }

    public function test_home_path_cards_count_only_published_courses(): void
    {
        $category = Category::factory()->create(['name' => 'Video Editing', 'slug' => 'video-editing', 'active' => true]);
        Course::factory()->count(2)->create(['category_id' => $category->id]);
        Course::factory()->create(['category_id' => $category->id, 'status' => 'draft', 'published_at' => null]);
        Category::factory()->create(['name' => 'Motion Design', 'slug' => 'motion-design', 'active' => true]);

        $this->get('/')->assertOk()
            ->assertSee('2 courses')
            ->assertSee('Courses coming soon')
            ->assertSee(route('categories.show', $category), false);
    }

    public function test_only_published_courses_in_active_categories_are_public(): void
    {
        $visible = $this->course(['title' => 'Visible course']);
        $draft = $this->course(['title' => 'Hidden draft', 'status' => 'draft']);
        $inactive = $this->course(['category_id' => Category::factory()->create(['active' => false])->id]);
        $scheduled = $this->course(['published_at' => now()->addDay()]);

        $this->get('/courses')->assertOk()->assertSee('Visible course')->assertDontSee('Hidden draft');
        $this->get('/courses/'.$visible->slug)->assertOk();
        foreach ([$draft, $inactive, $scheduled] as $course) {
            $this->get('/courses/'.$course->slug)->assertNotFound();
        }
    }

    public function test_catalog_combines_filters_and_search(): void
    {
        $category = Category::factory()->create(['slug' => 'video-editing']);
        $this->course(['title' => 'Editing essentials', 'category_id' => $category->id, 'level' => 'beginner', 'language' => 'en', 'is_free' => true, 'price_minor' => 0]);
        $this->course(['title' => 'Editing advanced', 'level' => 'advanced', 'is_free' => false]);

        $this->get('/courses?q=Editing&category=video-editing&level=beginner&language=en&access=free')
            ->assertOk()->assertSee('Editing essentials')->assertDontSee('Editing advanced');
    }

    public function test_catalog_sorts_and_paginates_without_dropping_filters(): void
    {
        Course::factory()->count(13)->create(['title' => 'Editing course']);
        $response = $this->get('/courses?q=Editing&sort=title');
        $response->assertOk()->assertViewHas('courses', fn ($courses) => $courses->count() === 12 && $courses->total() === 13);
        $response->assertSee('q=Editing', false);
        $this->get('/courses?q=Editing&page=2')->assertViewHas('courses', fn ($courses) => $courses->count() === 1);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        foreach (['sort=sql', 'level=expert', 'q[]=x', 'access=anything', 'page=-1'] as $query) {
            $this->getJson('/courses?'.$query)->assertUnprocessable();
        }
    }

    public function test_category_route_filters_courses_and_rejects_inactive_categories(): void
    {
        $category = Category::factory()->create();
        $course = $this->course(['category_id' => $category->id]);
        $this->get('/categories/'.$category->slug)->assertOk()->assertSee($course->title);
        $category->update(['active' => false]);
        $this->get('/categories/'.$category->slug)->assertNotFound();
    }

    public function test_details_escape_content_and_hide_unpublished_lessons(): void
    {
        $course = $this->course(['description' => '<script>alert(1)</script>']);
        $module = $course->modules()->create(['title' => 'Getting started', 'sort_order' => 1]);
        $module->lessons()->create(['title' => 'Public lesson', 'published' => true, 'sort_order' => 1]);
        $module->lessons()->create(['title' => 'Private lesson', 'published' => false, 'sort_order' => 2]);
        $this->get('/courses/'.$course->slug)->assertOk()->assertSee('Public lesson')->assertDontSee('Private lesson')
            ->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_static_pages_and_legal_review_notices_are_available(): void
    {
        foreach (['about', 'contact', 'faq'] as $page) {
            $this->get('/'.$page)->assertOk();
        }
        foreach (['privacy', 'terms', 'refund-policy'] as $page) {
            $this->get('/'.$page)->assertOk()->assertSee('Pending legal review');
        }
    }

    public function test_paid_courses_do_not_offer_live_checkout(): void
    {
        $course = $this->course(['is_free' => false, 'price_minor' => 120000]);
        $this->get('/courses/'.$course->slug)->assertOk()->assertSee('Checkout is not available yet');
    }

    public function test_html_filter_errors_redirect_to_a_clean_catalog_with_feedback(): void
    {
        $this->from('/courses?q[]=x')->get('/courses?q[]=x')->assertRedirect('/courses')->assertSessionHasErrors('q');
        $response = $this->withCookie(config('session.cookie'), session()->getId())->get('/courses');
        $response->assertOk()->assertSee('Please check your filters');
        $this->from('/')->get('/courses?sort=invalid')->assertRedirect('/courses')->assertSessionHasErrors('sort');
    }

    public function test_signed_in_catalogue_shows_enrollment_wishlist_and_real_counts(): void
    {
        $user = \App\Models\User::factory()->create();
        $course = Course::factory()->create(['title' => 'Enrolled course', 'is_free' => true, 'price_minor' => 0]);
        $other = Course::factory()->create(['title' => 'Other course']);
        $module = $course->modules()->create(['title' => 'One']);
        $module->lessons()->create(['title' => 'A', 'type' => 'text', 'body' => 'x', 'duration_seconds' => 5400, 'published' => true]);
        $module->lessons()->create(['title' => 'Draft', 'type' => 'text', 'body' => 'x', 'duration_seconds' => 600, 'published' => false]);
        \App\Models\Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $course->id]);
        \App\Models\Wishlist::create(['user_id' => $user->id, 'course_id' => $other->id]);
        \App\Models\CourseReview::create(['course_id' => $other->id, 'user_id' => $user->id, 'rating' => 4, 'body' => 'Good', 'status' => 'pending']);

        $this->actingAs($user)->get('/courses')->assertOk()
            ->assertSee('class="app-sidebar"', false)
            ->assertSee('Start Learning')->assertSee('Enroll Now')
            ->assertSee('1 Lesson')->assertSee('1h')
            ->assertSee('Remove Other course from wishlist')
            ->assertDontSee('browse-rating', false);
        $this->get('/courses?sort=popular')->assertOk();
        auth()->logout();
        $this->get('/courses')->assertOk()->assertDontSee('class="app-sidebar"', false);
    }

    public function test_signed_in_pages_use_the_sidebar_and_public_pages_do_not(): void
    {
        $user = \App\Models\User::factory()->create();
        foreach (['/dashboard', '/my-learning', '/certificates', '/wishlist', '/profile', '/payments', '/notifications', '/support'] as $page) {
            $this->actingAs($user)->get($page)->assertOk()->assertSee('class="app-sidebar"', false)->assertDontSee('class="site-footer"', false);
        }
        $this->get('/')->assertOk()->assertDontSee('class="app-sidebar"', false);
        $this->get('/about')->assertOk()->assertDontSee('class="app-sidebar"', false);
        $unverified = \App\Models\User::factory()->unverified()->create();
        $this->actingAs($unverified)->get('/verify-email')->assertOk()->assertDontSee('class="app-sidebar"', false);
    }
}
