<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use App\Models\VideoAsset;
use App\Support\AcademySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthAndBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_reports_up(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_storage_alert_notifies_admins_once_per_crossing(): void
    {
        AcademySettings::save(['storage_limit_gb' => 1, 'storage_alert_percent' => 50]);
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();
        $asset = VideoAsset::create(['course_id' => $course->id, 'uploaded_by' => $admin->id, 'object_key' => 'videos/a.mp4', 'upload_key' => 'uploads/a.mp4', 'filename' => 'a.mp4', 'expected_bytes' => 700 * 1024 ** 2, 'status' => 'ready', 'upload_expires_at' => now()]);

        $this->artisan('academy:check-storage')->assertSuccessful();
        $this->artisan('academy:check-storage')->assertSuccessful();
        $this->assertSame(1, $admin->notifications()->count());

        $asset->update(['status' => 'failed']);
        $this->artisan('academy:check-storage')->assertSuccessful();
        $asset->update(['status' => 'ready']);
        $this->artisan('academy:check-storage')->assertSuccessful();
        $this->assertSame(2, $admin->notifications()->count());
    }

    public function test_backup_refuses_non_mysql_databases(): void
    {
        $this->artisan('academy:backup')->assertFailed();
    }
}
