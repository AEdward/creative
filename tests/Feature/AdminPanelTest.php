<?php

namespace Tests\Feature;

use App\Filament\Pages\Certificates;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\CertificateTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_dashboard_media_and_certificate_pages_render(): void
    {
        Course::factory()->create(['title' => 'Dashboard course']);
        $this->actingAs($this->admin());
        $this->get('/admin')->assertOk()->assertSee('Total Students')->assertSee('Dashboard course')->assertSee('Storage Usage')->assertDontSee('ui-avatars.com');
        $this->get('/admin/media-library')->assertOk()->assertSee('No videos uploaded yet.');
        $this->get('/admin/certificates')->assertOk()->assertSee('Live preview');
    }

    public function test_signed_in_non_admins_are_sent_to_their_dashboard_instead_of_403(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertRedirect(route('dashboard'))->assertSessionHas('status');
        $this->actingAs(User::factory()->create(['role' => 'instructor']))->get('/admin/certificates')->assertRedirect(route('dashboard'));
    }

    public function test_admin_can_edit_the_certificate_and_issued_certificates_use_it(): void
    {
        $this->actingAs($this->admin());
        Livewire::test(Certificates::class)
            ->set('template.heading', 'Diploma of Creativity')
            ->set('template.accent', 'not-a-colour')
            ->call('save')->assertHasErrors(['template.accent'])
            ->set('template.accent', '#3054F5')
            ->call('save')->assertHasNoErrors();
        $this->assertSame('Diploma of Creativity', CertificateTemplate::get()['heading']);

        $learner = User::factory()->create();
        $enrollment = Enrollment::factory()->create(['user_id' => $learner->id]);
        $certificate = Certificate::create(['enrollment_id' => $enrollment->id, 'reference' => (string) \Illuminate\Support\Str::uuid(), 'learner_name' => 'Sara Bekele', 'course_title' => 'Motion', 'issued_at' => now()]);
        $this->actingAs($learner)->get(route('certificates.print', $certificate))->assertOk()->assertSee('Diploma of Creativity')->assertSee('Sara Bekele')->assertSee('#3054F5', false);
    }

    public function test_certificate_editor_is_admin_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'instructor']));
        Livewire::test(Certificates::class)->assertForbidden();
    }
}
