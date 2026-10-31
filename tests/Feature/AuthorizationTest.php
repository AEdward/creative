<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_workspaces(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/instructor')->assertRedirect('/login');
    }

    public function test_students_cannot_enter_instructor_or_admin_workspaces(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->get('/instructor')->assertForbidden();
        $this->get('/admin')->assertRedirect(route('dashboard'));
    }

    public function test_only_instructors_and_admins_can_enter_instructor_workspace(): void
    {
        foreach (['instructor', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/instructor')->assertOk();
        }
    }

    public function test_suspended_existing_sessions_are_revoked(): void
    {
        $user = User::factory()->create(['status' => 'suspended']);
        $this->actingAs($user)->get('/dashboard')->assertForbidden();
        $this->assertGuest();
    }

    public function test_course_policy_enforces_ownership_and_administrator_access(): void
    {
        $owner = User::factory()->create(['role' => 'instructor']);
        $other = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $owner->id]);
        $this->assertTrue(Gate::forUser($owner)->allows('update', $course));
        $this->assertFalse(Gate::forUser($other)->allows('update', $course));
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('update', $course));
        $this->assertTrue(Gate::forUser(User::factory()->create(['role' => 'admin']))->allows('update', $course));
    }
}
