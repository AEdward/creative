<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteHealth as SiteHealthPage;
use App\Models\User;
use App\Support\BackgroundHealth;
use App\Support\SiteHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class SiteHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lists_checks_for_super_admins_only(): void
    {
        BackgroundHealth::beat();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'staff_role' => 'super']))->get('/admin/site-health')
            ->assertOk()->assertSee('Database')->assertSee('Scheduled jobs')->assertSee('Email sending')->assertSee('uptimerobot.com')->assertSee(url('/up'));
        Mail::fake();
        Livewire::test(SiteHealthPage::class)->callAction('testEmail', ['to' => 'me@academy.test'])->assertHasNoActionErrors();
        Livewire::test(SiteHealthPage::class)->call('checkMedia')->assertSee('Not connected yet');
        $this->actingAs(User::factory()->create(['role' => 'admin', 'staff_role' => 'finance']))->get('/admin/site-health')->assertForbidden();
    }

    public function test_alerts_are_sent_once_when_a_check_starts_failing_and_once_when_fixed(): void
    {
        $super = User::factory()->create(['role' => 'admin', 'staff_role' => 'super']);
        $support = User::factory()->create(['role' => 'admin', 'staff_role' => 'support']);

        Cache::forget(BackgroundHealth::HEARTBEAT_KEY); // scheduler "never ran" = fail
        $this->assertContains('scheduler', collect(app(SiteHealth::class)->problems())->pluck('key'));
        $this->artisan('academy:health-alerts')->assertSuccessful();
        $this->artisan('academy:health-alerts')->assertSuccessful();
        $this->assertSame(1, $super->notifications()->count());
        $this->assertSame(0, $support->notifications()->count());
        $this->assertStringContainsString('Scheduled jobs', $super->notifications()->first()->data['body']);

        BackgroundHealth::beat();
        $this->artisan('academy:health-alerts')->assertSuccessful();
        $this->assertSame(2, $super->notifications()->count());
        $this->assertTrue($super->notifications()->get()->contains(fn ($n) => $n->data['title'] === 'Creative Lab: problem fixed'));
    }
}
