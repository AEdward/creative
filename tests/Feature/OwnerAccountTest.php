<?php

namespace Tests\Feature;

use App\Filament\Pages\Staff;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Livewire\Instructor\CourseEditor;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AccountAdministration;
use App\Services\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OwnerAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_is_hidden_from_and_protected_against_other_super_admins(): void
    {
        $owner = User::factory()->create(['role' => 'admin', 'staff_role' => 'content', 'name' => 'Anahom Owner', 'email' => 'anahom50@gmail.com']);
        $this->artisan('academy:owner', ['email' => 'anahom50@gmail.com'])->assertSuccessful();
        $owner->refresh();
        $this->assertTrue($owner->is_owner);
        $this->assertSame('super', $owner->staffRole());
        AuditLog::create(['actor_id' => $owner->id, 'action' => 'course.published', 'subject_type' => 'course', 'subject_id' => 1, 'changes' => []]);

        // Another super admin can't see the owner anywhere…
        $other = User::factory()->create(['role' => 'admin', 'staff_role' => 'super', 'name' => 'Second Super']);
        $this->actingAs($other);
        $this->get('/admin/staff')->assertOk()->assertSee('Second Super')->assertDontSee('Anahom Owner')->assertDontSee('anahom50@gmail.com');
        $this->get('/admin/users')->assertOk()->assertDontSee('anahom50@gmail.com');
        $this->get('/admin/audit-logs')->assertOk()->assertDontSee('Anahom Owner')->assertSee('Owner');
        Livewire::test(CourseEditor::class)->assertDontSee('Anahom Owner');

        // …and can't change, remove, suspend or reset it, even by calling the actions directly.
        $admin = app(AccountAdministration::class);
        $this->assertThrows(fn () => $admin->setRole($other, $owner, 'admin', 'support'));
        $this->assertThrows(fn () => $admin->setRole($other, $owner, 'student'));
        $this->assertThrows(fn () => $admin->setStatus($other, $owner, 'suspended'));
        $owner->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->assertThrows(fn () => app(TwoFactor::class)->reset($other, $owner));
        $owner->refresh();
        $this->assertSame(['admin', 'super', 'active', true], [$owner->role, $owner->staffRole(), $owner->status, (bool) $owner->two_factor_confirmed_at]);
        Livewire::test(Staff::class)->assertCanNotSeeTableRecords([$owner]);
        Livewire::test(ManageUsers::class)->assertCanNotSeeTableRecords([$owner]);

        // The owner still sees everything, including other staff.
        $this->actingAs($owner)->get('/admin/staff')->assertOk()->assertSee('Anahom Owner')->assertSee('Second Super');
        Livewire::test(Staff::class)->callTableAction('changeRole', $other, ['staff_role' => 'support']);
        $this->assertSame('support', $other->fresh()->staff_role);

        // Only the server can switch it off.
        $this->artisan('academy:owner', ['email' => 'anahom50@gmail.com', '--remove' => true])->assertSuccessful();
        $this->assertFalse($owner->fresh()->is_owner);
        $this->artisan('academy:owner', ['email' => 'nobody@academy.test'])->assertFailed();
    }
}
