<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\InstructorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstructorProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_requires_listing_consent_and_active_verified_instructor_role(): void
    {
        $visible = InstructorProfile::factory()->create(['publicly_listed' => true]);
        $hidden = InstructorProfile::factory()->create();
        foreach ([['role' => 'admin'], ['status' => 'suspended'], ['email_verified_at' => null]] as $state) {
            $profile = InstructorProfile::factory()->create(['user_id' => User::factory()->create(array_merge(['role' => 'instructor'], $state)), 'publicly_listed' => true]);
            $this->get('/instructors/'.$profile->id)->assertNotFound();
        }
        $this->get('/instructors')->assertOk()->assertSee($visible->user->name)->assertDontSee($hidden->user->name)->assertDontSee($visible->user->email);
        $this->get('/instructors/'.$hidden->id)->assertNotFound();
        $course = Course::factory()->create(['instructor_id' => $visible->user_id, 'status' => 'published', 'published_at' => now()]);
        $draft = Course::factory()->create(['instructor_id' => $visible->user_id, 'status' => 'draft', 'published_at' => null]);
        $this->get('/instructors/'.$visible->id)->assertOk()->assertSee($course->title)->assertDontSee($draft->title)->assertDontSee($visible->user->email);
    }

    public function test_owner_can_edit_plain_text_profile_and_revoke_public_consent(): void
    {
        $user = User::factory()->create(['role' => 'instructor']);
        $this->actingAs($user)->get('/instructor/profile')->assertOk();
        $this->patch('/instructor/profile', ['professional_title' => 'Designer', 'biography' => '<script>alert(1)</script>', 'specialties' => 'Design', 'publicly_listed' => '1', 'show_photo' => '1'])->assertRedirect('/instructor/profile')->assertSessionHasNoErrors();
        $profile = InstructorProfile::firstOrFail();
        $this->assertSame($user->id, $profile->user_id);
        $this->get('/instructors/'.$profile->id)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->patch('/instructor/profile', ['professional_title' => 'Designer'])->assertSessionHasNoErrors();
        $this->get('/instructors/'.$profile->id)->assertNotFound();
        $this->actingAs(User::factory()->create())->get('/instructor/profile')->assertForbidden();
        $this->actingAs(User::factory()->unverified()->create(['role' => 'instructor']))->get('/instructor/profile')->assertRedirect(route('verification.notice'));
    }

    public function test_malformed_queries_and_old_input_are_safe_and_length_limits_are_enforced(): void
    {
        $this->get('/instructors?q[]=bad&page[]=bad')->assertOk();
        $this->get('/instructors?q='.str_repeat('a', 201))->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'instructor']))->from('/instructor/profile')->patch('/instructor/profile', ['professional_title' => ['bad'], 'biography' => str_repeat('a', 5001), 'specialties' => str_repeat('a', 1001), 'publicly_listed' => ['bad']])->assertSessionHasErrors(['professional_title', 'biography', 'specialties', 'publicly_listed']);
        $this->get('/instructor/profile')->assertOk();
    }

    public function test_search_and_pagination_keep_only_public_profiles_and_admins_can_edit_their_own(): void
    {
        $profile = InstructorProfile::factory()->create(['professional_title' => 'Motion specialist', 'publicly_listed' => true]);
        InstructorProfile::factory()->count(12)->create(['publicly_listed' => true]);
        $this->get('/instructors?q=Motion+specialist')->assertOk()->assertSee($profile->user->name);
        $this->get('/instructors?page=2')->assertOk()->assertSee('Previous');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch('/instructor/profile', ['professional_title' => 'Academy lead', 'publicly_listed' => '1'])->assertSessionHasNoErrors();
        $adminProfile = InstructorProfile::where('user_id', $admin->id)->firstOrFail();
        $this->get('/instructors/'.$adminProfile->id)->assertNotFound();
        $this->assertSame('Motion specialist', $profile->fresh()->professional_title);
    }

    public function test_public_photo_requires_both_consents_and_is_revoked_with_eligibility(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('avatars/portrait.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        $profile = InstructorProfile::factory()->create(['publicly_listed' => true, 'show_photo' => false]);
        $profile->user->forceFill(['avatar_key' => 'avatars/portrait.png'])->save();
        $url = '/instructors/'.$profile->id.'/photo';
        $this->get($url)->assertNotFound();
        $profile->update(['show_photo' => true]);
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'no-store, private');
        $profile->user->forceFill(['status' => 'suspended'])->save();
        $this->get($url)->assertNotFound();
    }
}
