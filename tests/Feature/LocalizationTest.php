<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_amharic_authentication_and_student_workspace_labels(): void
    {
        $this->withSession(['locale' => 'am'])->get('/login')->assertOk()->assertSee('የኢሜይል አድራሻ')->assertSee('ለመመልከት ዝግጁ ነው።');
        $this->actingAs(User::factory()->create())->withSession(['locale' => 'am']);
        foreach (['/profile' => 'የእርስዎ ዝርዝሮች', '/dashboard' => 'በቅርቡ የታዩ', '/support' => 'የእርዳታ ጥያቄ ክፈት', '/notifications' => 'እስካሁን ማሳወቂያዎች የሉም።'] as $url => $label) {
            $this->get($url)->assertOk()->assertSee($label)->assertDontSee('academy.home')->assertDontSee('academy.dashboard');
        }
    }

    public function test_english_and_invalid_session_locales_keep_english_fallback(): void
    {
        foreach (['en', 'unknown'] as $locale) {
            $this->withSession(['locale' => $locale])->get('/login')->assertOk()->assertSee('Email address')->assertSee('lang="en"', false)->assertDontSee('academy.home')->assertSee('Home')->assertSee('id="mobile-locale"', false);
        }
    }

    public function test_amharic_public_and_instructor_and_administrator_flows(): void
    {
        $this->withSession(['locale' => 'am']);
        $this->get('/courses')->assertOk()->assertSee('ትምህርቶችን ይፈልጉ');
        $this->get('/instructors')->assertOk()->assertSee('አስተማሪዎቻችንን ይተዋወቁ።');
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'title' => 'Original course title']);
        $this->actingAs($instructor);
        foreach ([route('instructor.profile.edit') => 'የአስተማሪ መገለጫዎ።', route('instructor.materials', $course) => 'የትምህርት ምስል', route('instructor.assessments', $course) => 'ምዘና ይፍጠሩ', route('instructor.courses.edit', $course) => 'ትምህርት ያስቀምጡ'] as $url => $label) {
            $this->get($url)->assertOk()->assertSee($label)->assertDontSee('academy.home');
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/academy-admin')->assertOk()->assertSee('የሪፖርት ጊዜ');
    }

    public function test_learning_keeps_course_content_and_localizes_private_controls(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create(['title' => 'Original course title']);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'published' => true, 'type' => 'text', 'body' => 'Original lesson content']);
        Enrollment::factory()->create(['course_id' => $course->id, 'user_id' => $student->id]);
        $this->actingAs($student)->withSession(['locale' => 'am'])->get(route('learning.show', [$course, $lesson]))->assertOk()->assertSee('የግል ማስታወሻዎችዎ')->assertSee('Original lesson content')->assertSee('Original course title');
        $this->get(route('assessments.index', $course))->assertOk()->assertSee('የማጠናቀቂያ ምስክር ወረቀት ይጠይቁ');
    }

    public function test_locale_change_rejects_unsupported_values(): void
    {
        $this->withSession(['locale' => 'am'])->post('/locale', ['locale' => 'xx'])->assertSessionHasErrors('locale')->assertSessionHas('locale', 'am');
        $this->post('/locale', ['locale' => 'en'])->assertRedirect()->assertSessionHas('locale', 'en');
    }

    public function test_amharic_validation_keeps_attribute_placeholders_resolved(): void
    {
        app()->setLocale('am');
        $message = Validator::make([], ['email' => 'required'])->errors()->first('email');
        $this->assertSame('የኢሜይል አድራሻ ያስፈልጋል።', $message);
        $this->assertSame('የይለፍ ቃልዎ ተቀይሯል።', __('passwords.reset'));
        $this->withSession(['locale' => 'am', 'status' => 'Your profile has been updated.'])->get('/login')->assertOk()->assertSee('መገለጫዎ ተዘምኗል።');
    }

    public function test_marketing_and_instructor_consent_and_editor_controls_are_localized(): void
    {
        $this->withSession(['locale' => 'am'])->get('/')->assertOk()->assertDontSee('Find your creative path');
        $owner = User::factory()->create(['role' => 'instructor']);
        $this->actingAs($owner)->get('/instructor/profile')->assertOk()->assertDontSee('I consent to publishing my name and instructor profile in the public directory.');
        $this->get('/instructor/courses/create')->assertOk()->assertDontSee('Start with an idea.');
        $this->get('/login')->assertRedirect();
    }
}
