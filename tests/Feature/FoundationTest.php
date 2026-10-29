<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_users_can_be_persisted_with_hashed_passwords(): void
    {
        $user = User::factory()->create(['password' => 'local-test-password']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
        $this->assertTrue(Hash::check('local-test-password', $user->fresh()->password));
    }
}
