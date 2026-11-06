<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings;
use App\Models\User;
use App\Support\AcademySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaintenanceAndErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_pages_show_a_helpful_404(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee('This page wandered off.')->assertSee(route('courses.index'))->assertSee('noindex', false);
        $this->get('/courses/not-a-real-course')->assertNotFound()->assertSee('Browse all courses');
    }

    public function test_maintenance_mode_is_switched_in_settings_and_staff_keep_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        Livewire::test(Settings::class)->set('settings.maintenance_enabled', true)->set('settings.maintenance_message', 'Back at 6pm.')->call('save')->assertHasNoErrors();
        $this->assertTrue(AcademySettings::get('maintenance_enabled'));

        // Staff browse normally, with a reminder.
        $this->get('/')->assertOk()->assertSee('Maintenance mode is on');
        $this->get('/admin/settings')->assertOk();

        // Visitors and learners see the back-soon page; sign-in stays open for staff.
        auth()->logout();
        $this->get('/')->assertStatus(503)->assertSee('We’ll be back soon')->assertSee('Back at 6pm.')->assertHeader('Retry-After', '3600');
        $this->get('/courses')->assertStatus(503);
        $this->get('/login')->assertOk();
        $this->get('/up')->assertOk();
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertStatus(503);

        AcademySettings::save(['maintenance_enabled' => false]);
        auth()->logout();
        $this->get('/')->assertOk()->assertDontSee('Maintenance mode is on');
    }
}
