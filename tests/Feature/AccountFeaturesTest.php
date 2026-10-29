<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_can_be_updated_but_role_input_is_ignored(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/profile')->assertOk();
        $this->patch('/profile', ['name' => 'Updated learner', 'email' => $user->email, 'role' => 'admin'])->assertRedirect('/profile');
        $this->assertSame('Updated learner', $user->fresh()->name);
        $this->assertSame('student', $user->fresh()->role);
    }

    public function test_email_change_requires_current_password_and_reverification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'Current-password123!']);
        $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'email' => 'new@example.test'])->assertSessionHasErrors('current_password');
        $this->patch('/profile', ['name' => $user->name, 'email' => 'NEW@example.test', 'current_password' => 'Current-password123!'])->assertRedirect('/verify-email');
        $this->assertSame('new@example.test', $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);
        Notification::assertSentTo($user->fresh(), VerifyEmail::class);
    }

    public function test_password_change_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => 'Current-password123!']);
        $this->actingAs($user)->patch('/profile/password', ['current_password' => 'wrong', 'password' => 'New-password123!', 'password_confirmation' => 'New-password123!'])->assertSessionHasErrors('current_password');
        $this->patch('/profile/password', ['current_password' => 'Current-password123!', 'password' => 'New-password123!', 'password_confirmation' => 'New-password123!'])->assertRedirect('/profile');
        $this->assertTrue(Hash::check('New-password123!', $user->fresh()->password));
    }

    public function test_contact_messages_are_validated_and_stored_without_client_status(): void
    {
        $data = ['name' => 'A learner', 'email' => 'learner@example.test', 'subject' => 'Course question', 'message' => 'I would like to learn more about the upcoming creative courses.', 'status' => 'read'];
        $this->post('/contact', $data)->assertRedirect('/contact');
        $this->assertDatabaseHas('contact_messages', ['email' => $data['email'], 'status' => 'new']);
        $this->post('/contact', ['name' => ['bad'], 'email' => 'invalid', 'message' => ''])->assertSessionHasErrors(['name', 'email', 'message']);
    }

    public function test_contact_honeypot_does_not_store_spam(): void
    {
        $this->post('/contact', ['name' => 'Bot', 'email' => 'bot@example.test', 'message' => 'Automated spam message.', 'website' => 'spam.example'])->assertRedirect('/contact');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_wishlist_is_idempotent_and_owned_by_the_current_student(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $course = Course::factory()->create();
        $this->actingAs($user)->post('/wishlist/'.$course->slug)->assertRedirect('/wishlist');
        $this->post('/wishlist/'.$course->slug)->assertRedirect('/wishlist');
        $this->assertDatabaseCount('wishlists', 1);
        $this->actingAs($other)->delete('/wishlist/'.$course->slug)->assertRedirect('/wishlist');
        $this->assertDatabaseCount('wishlists', 1);
        $this->get('/wishlist')->assertDontSee($course->title);
        $this->actingAs($user)->delete('/wishlist/'.$course->slug)->assertRedirect('/wishlist');
        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_lesson_notes_and_bookmarks_require_owned_active_enrollment(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $course = Course::factory()->create();
        $lesson = $course->modules()->create(['title' => 'Module'])->lessons()->create(['title' => 'Lesson', 'type' => 'text', 'body' => 'Learn this.', 'published' => true]);
        Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $course->id]);
        $url = '/learn/'.$course->slug.'/'.$lesson->id.'/notes';
        $this->actingAs($other)->put($url, ['body' => 'Not mine', 'bookmarked' => true])->assertForbidden();
        $this->actingAs($user)->put($url, ['body' => 'My private idea', 'bookmarked' => true])->assertRedirect();
        $this->assertDatabaseHas('lesson_notes', ['lesson_id' => $lesson->id, 'body' => 'My private idea', 'bookmarked' => true]);
        $this->get('/learn/'.$course->slug.'/'.$lesson->id)->assertSee('My private idea');
        $lesson->update(['is_preview' => true]);
        $this->actingAs($other)->get('/learn/'.$course->slug.'/'.$lesson->id)->assertOk()->assertDontSee('My private idea');
    }

    public function test_malformed_current_password_returns_validation_errors(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patchJson('/profile/password', ['current_password' => ['invalid'], 'password' => 'New-password123!', 'password_confirmation' => 'New-password123!'])->assertUnprocessable();
        $this->patchJson('/profile', ['name' => 'Learner', 'email' => 'new@example.test', 'current_password' => ['invalid']])->assertUnprocessable();
    }
}
